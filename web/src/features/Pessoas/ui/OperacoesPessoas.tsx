import Alert from '@mui/material/Alert'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import FormControl from '@mui/material/FormControl'
import FormControlLabel from '@mui/material/FormControlLabel'
import FormLabel from '@mui/material/FormLabel'
import Radio from '@mui/material/Radio'
import RadioGroup from '@mui/material/RadioGroup'
import Stack from '@mui/material/Stack'
import Tab from '@mui/material/Tab'
import Tabs from '@mui/material/Tabs'
import TextField from '@mui/material/TextField'
import Typography from '@mui/material/Typography'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type ReactNode } from 'react'
import { ErroHttp } from '../../../shared/http'
import { EstadoCarregando, EstadoErro, EstadoVazio } from '../../../shared/ui'
import { enviarRecurso, listarRecurso, obterFicha, type RegistroGenerico } from '../api/pessoas'

interface PessoaDoPar { uuid: string; nome: string; nascimento?: string | null; vinculo?: string; igreja?: { nome?: string } }
interface ParDuplicado extends RegistroGenerico { pessoas: PessoaDoPar[]; criterios: string[]; meta: { acoes: string[] } }
interface Transferencia extends RegistroGenerico { pessoa: { uuid: string; nome: string }; origem?: { nome?: string }; destino?: { nome?: string }; estado: string; justificativa?: string | null; acoes: { decidir: boolean } }
interface ItemLixeira extends RegistroGenerico { nome: string; igreja?: { nome?: string }; motivo: string; excluida_em: string; recuperavel_ate: string; meta: { acoes: string[] } }

const mensagemErro = (erro: unknown): string => erro instanceof ErroHttp ? (erro.codigo ?? 'Não foi possível concluir a operação.') : 'Não foi possível concluir a operação.'

const mostrarValor = (valor: unknown): string => {
  if (valor === null || valor === undefined || valor === '') return 'Não informado'
  if (typeof valor === 'object') return JSON.stringify(valor)
  return String(valor)
}

function ComparacaoDuplicado({ par, indice, aoConcluir }: { par: ParDuplicado; indice: number; aoConcluir: () => void }): ReactNode {
  const [mantida, absorvida] = par.pessoas
  const [escolhas, setEscolhas] = useState<Record<string, 'mantida' | 'absorvida'>>({})
  const [operacao, setOperacao] = useState<string | null>(null)
  const [conflitos, setConflitos] = useState<string[]>([])
  const podeMesclar = par.meta.acoes.includes('mesclar')
  const fichaMantida = useQuery({ queryKey: ['comparacao-mesclagem', mantida?.uuid], queryFn: () => obterFicha(mantida?.uuid ?? ''), enabled: Boolean(mantida) && podeMesclar })
  const fichaAbsorvida = useQuery({ queryKey: ['comparacao-mesclagem', absorvida?.uuid], queryFn: () => obterFicha(absorvida?.uuid ?? ''), enabled: Boolean(absorvida) && podeMesclar })
  const mesclar = useMutation({
    mutationFn: () => enviarRecurso<{ operacao: string }>('POST', '/painel/mesclagens', { mantida: mantida?.uuid, absorvida: absorvida?.uuid, valores: escolhas }),
    onSuccess: (resposta) => setOperacao(resposta.operacao),
  })
  const desfazer = useMutation({
    mutationFn: () => enviarRecurso<{ conflitos?: string[] }>('POST', `/painel/mesclagens/${operacao}/desfazer`),
    onSuccess: (resposta) => { setConflitos(resposta.conflitos ?? []); setOperacao(null); aoConcluir() },
  })
  if (!mantida || !absorvida) return null
  const camposMantida = fichaMantida.data?.data.campos ?? {}
  const camposAbsorvida = fichaAbsorvida.data?.data.campos ?? {}
  const divergentes = [...new Set([...Object.keys(camposMantida), ...Object.keys(camposAbsorvida)])]
    .filter((campo) => JSON.stringify(camposMantida[campo]) !== JSON.stringify(camposAbsorvida[campo]))
  const podeIgnorar = par.meta.acoes.includes('nao_duplicadas')
  return <Card variant="outlined"><CardContent><Stack spacing={2}>
    <Typography variant="h6">{mantida.nome} × {absorvida.nome}</Typography>
    <Typography color="text.secondary">Critérios: {par.criterios.join(', ')}</Typography>
    <Stack direction={{ xs: 'column', md: 'row' }} gap={2}>
      {[mantida, absorvida].map((pessoa) => <Card key={pessoa.uuid} variant="outlined" sx={{ flex: 1 }}><CardContent><Typography fontWeight={700}>{pessoa.nome}</Typography><Typography>{pessoa.igreja?.nome ?? 'Igreja não informada'}</Typography><Typography>{pessoa.nascimento ?? 'Nascimento não informado'} · {pessoa.vinculo ?? 'Sem vínculo'}</Typography><Button href={`/painel/pessoas/${pessoa.uuid}`}>Abrir ficha</Button></CardContent></Card>)}
    </Stack>
    {(fichaMantida.isPending || fichaAbsorvida.isPending) && <Typography>Carregando comparação campo a campo…</Typography>}
    {divergentes.map((campo) => <FormControl key={campo} component="fieldset"><FormLabel component="legend">{campo.replaceAll('_', ' ')}</FormLabel><RadioGroup row value={escolhas[campo] ?? 'mantida'} onChange={(evento) => setEscolhas((atuais) => ({ ...atuais, [campo]: evento.target.value as 'mantida' | 'absorvida' }))}><FormControlLabel value="mantida" control={<Radio />} label={`${mantida.nome}: ${mostrarValor(camposMantida[campo])}`} /><FormControlLabel value="absorvida" control={<Radio />} label={`${absorvida.nome}: ${mostrarValor(camposAbsorvida[campo])}`} /></RadioGroup></FormControl>)}
    {mesclar.isError && <Alert severity="error">{mensagemErro(mesclar.error) === 'mesclagem_contas_conflitantes' ? 'As duas fichas têm acesso ao painel. Revogue um dos acessos antes de mesclar.' : mensagemErro(mesclar.error)}</Alert>}
    {desfazer.isError && <Alert severity="error">{mensagemErro(desfazer.error)}</Alert>}
    {conflitos.length > 0 && <Alert severity="warning">A mesclagem foi desfeita, mas os valores atuais foram preservados em: {conflitos.join(', ')}.</Alert>}
    {operacao && <Alert severity="success" action={<Button color="inherit" disabled={desfazer.isPending} onClick={() => desfazer.mutate()}>Desfazer</Button>}>Mesclagem concluída. É possível desfazer esta operação.</Alert>}
    <Stack direction="row" gap={1} flexWrap="wrap">
      {podeMesclar && !operacao && <Button variant="contained" disabled={mesclar.isPending || fichaMantida.isPending || fichaAbsorvida.isPending} onClick={() => { if (window.confirm(`Manter ${mantida.nome} e absorver ${absorvida.nome}?`)) mesclar.mutate() }}>Mesclar com os valores escolhidos</Button>}
      {podeIgnorar && !operacao && <Button disabled={mesclar.isPending} onClick={() => void enviarRecurso('POST', '/painel/duplicados/nao-duplicados', { pessoa: mantida.uuid, outra: absorvida.uuid }).then(aoConcluir)}>São pessoas diferentes</Button>}
    </Stack>
    <Typography variant="caption">Par {indice + 1}</Typography>
  </Stack></CardContent></Card>
}

function Duplicados(): ReactNode {
  const cliente = useQueryClient()
  const consulta = useQuery({ queryKey: ['operacoes-pessoas', 'duplicados'], queryFn: () => listarRecurso('/painel/duplicados') })
  if (consulta.isPending) return <EstadoCarregando texto="Carregando possíveis duplicidades…" />
  if (consulta.isError) return <EstadoErro titulo="Não foi possível carregar" texto={mensagemErro(consulta.error)} rotuloTentar="Tentar novamente" aoTentarDeNovo={() => void consulta.refetch()} />
  const pares = consulta.data.data as ParDuplicado[]
  if (pares.length === 0) return <EstadoVazio titulo="Nenhuma possível duplicidade" texto="Os pares já foram tratados ou não há coincidências." />
  return <Stack spacing={2}>
    {pares.map((par, indice) => <ComparacaoDuplicado key={par.pessoas.map((pessoa) => pessoa.uuid).join('-')} par={par} indice={indice} aoConcluir={() => void cliente.invalidateQueries({ queryKey: ['operacoes-pessoas', 'duplicados'] })} />)}
  </Stack>
}

function Transferencias(): ReactNode {
  const cliente = useQueryClient()
  const [justificativas, setJustificativas] = useState<Record<string, string>>({})
  const consulta = useQuery({ queryKey: ['operacoes-pessoas', 'transferencias'], queryFn: () => listarRecurso('/painel/transferencias') })
  const decidir = useMutation({
    mutationFn: ({ uuid, decisao }: { uuid: string; decisao: 'aprovar' | 'recusar' }) => enviarRecurso('POST', `/painel/transferencias/${uuid}/${decisao}`, decisao === 'recusar' ? { justificativa: justificativas[uuid] ?? '' } : undefined),
    onSuccess: () => cliente.invalidateQueries({ queryKey: ['operacoes-pessoas', 'transferencias'] }),
  })
  if (consulta.isPending) return <EstadoCarregando texto="Carregando transferências…" />
  if (consulta.isError) return <EstadoErro titulo="Não foi possível carregar" texto={mensagemErro(consulta.error)} rotuloTentar="Tentar novamente" aoTentarDeNovo={() => void consulta.refetch()} />
  const itens = consulta.data.data as Transferencia[]
  if (itens.length === 0) return <EstadoVazio titulo="Nenhuma transferência" texto="Não há solicitações visíveis para seu alcance." />
  return <Stack spacing={2}>
    {decidir.isError && <Alert severity="error">{mensagemErro(decidir.error)}</Alert>}
    {itens.map((item) => <Card key={item.uuid} variant="outlined"><CardContent><Stack spacing={1}>
      <Typography variant="h6">{item.pessoa.nome}</Typography>
      <Typography>{item.origem?.nome ?? 'Origem'} → {item.destino?.nome ?? 'Destino'}</Typography>
      <Typography>Estado: {item.estado}</Typography>
      {item.justificativa && <Typography>Justificativa: {item.justificativa}</Typography>}
      {item.acoes.decidir && <><TextField label="Justificativa para recusa" value={justificativas[item.uuid] ?? ''} onChange={(evento) => setJustificativas((atual) => ({ ...atual, [item.uuid]: evento.target.value }))} /><Stack direction="row" gap={1}><Button variant="contained" disabled={decidir.isPending} onClick={() => decidir.mutate({ uuid: item.uuid, decisao: 'aprovar' })}>Aprovar</Button><Button color="error" disabled={decidir.isPending || !(justificativas[item.uuid]?.trim())} onClick={() => decidir.mutate({ uuid: item.uuid, decisao: 'recusar' })}>Recusar</Button></Stack></>}
    </Stack></CardContent></Card>)}
  </Stack>
}

function Lixeira(): ReactNode {
  const cliente = useQueryClient()
  const consulta = useQuery({ queryKey: ['operacoes-pessoas', 'lixeira'], queryFn: () => listarRecurso('/painel/lixeira') })
  const recuperar = useMutation({ mutationFn: (uuid: string) => enviarRecurso('POST', `/painel/lixeira/${uuid}/recuperar`), onSuccess: () => cliente.invalidateQueries({ queryKey: ['operacoes-pessoas', 'lixeira'] }) })
  if (consulta.isPending) return <EstadoCarregando texto="Carregando lixeira…" />
  if (consulta.isError) return <EstadoErro titulo="Não foi possível carregar" texto={mensagemErro(consulta.error)} rotuloTentar="Tentar novamente" aoTentarDeNovo={() => void consulta.refetch()} />
  const itens = consulta.data.data as ItemLixeira[]
  if (itens.length === 0) return <EstadoVazio titulo="Lixeira vazia" texto="Nenhuma pessoa aguarda recuperação ou expurgo." />
  return <Stack spacing={2}>
    {recuperar.isError && <Alert severity="error">{mensagemErro(recuperar.error)}</Alert>}
    {itens.map((item) => <Card key={item.uuid} variant="outlined"><CardContent><Typography variant="h6">{item.nome}</Typography><Typography>{item.igreja?.nome ?? 'Igreja não informada'}</Typography><Typography>Motivo: {item.motivo}</Typography><Typography>Recuperável até {new Date(item.recuperavel_ate).toLocaleDateString('pt-BR')}</Typography>{item.meta.acoes.includes('recuperar') && <Button disabled={recuperar.isPending} onClick={() => { if (window.confirm(`Recuperar a ficha de ${item.nome}?`)) recuperar.mutate(item.uuid) }}>Recuperar</Button>}</CardContent></Card>)}
  </Stack>
}

export function TelaOperacoesPessoasCompleta(): ReactNode {
  const [aba, setAba] = useState(0)
  return <Stack spacing={2}><Typography variant="h4">Operações de pessoas</Typography><Tabs value={aba} onChange={(_, valor: number) => setAba(valor)} variant="scrollable"><Tab label="Possíveis duplicados" /><Tab label="Transferências" /><Tab label="Lixeira" /></Tabs>{aba === 0 && <Duplicados />}{aba === 1 && <Transferencias />}{aba === 2 && <Lixeira />}</Stack>
}
