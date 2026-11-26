import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Checkbox from '@mui/material/Checkbox'
import FormControl from '@mui/material/FormControl'
import InputLabel from '@mui/material/InputLabel'
import LinearProgress from '@mui/material/LinearProgress'
import MenuItem from '@mui/material/MenuItem'
import Select from '@mui/material/Select'
import Stack from '@mui/material/Stack'
import Table from '@mui/material/Table'
import TableBody from '@mui/material/TableBody'
import TableCell from '@mui/material/TableCell'
import TableHead from '@mui/material/TableHead'
import TableRow from '@mui/material/TableRow'
import Tab from '@mui/material/Tab'
import Tabs from '@mui/material/Tabs'
import TextField from '@mui/material/TextField'
import Typography from '@mui/material/Typography'
import { useMutation, useQuery } from '@tanstack/react-query'
import { useParams } from '@tanstack/react-router'
import { useState, type FormEvent, type ReactNode } from 'react'
import { ErroHttp } from '../../../shared/http'
import { EstadoCarregando, EstadoErro, EstadoVazio } from '../../../shared/ui'
import { buscarPessoas, cadastrarPessoa, criarExportacao, enviarRecurso, listarPessoas, listarRecurso, obterExportacao, obterFicha, verificarDuplicidade } from '../api/pessoas'
import { TelaOperacoesPessoasCompleta } from './OperacoesPessoas'
import { RegistrosFicha } from './RegistrosFicha'
import { AlteracoesFicha } from './AlteracoesFicha'

const erroDe = (erro: unknown): string => erro instanceof ErroHttp ? (erro.codigo ?? 'Não foi possível concluir a operação.') : 'Não foi possível concluir a operação.'
const valorTexto = (valor: unknown): string => typeof valor === 'string' || typeof valor === 'number' ? String(valor) : '—'

export function TelaPessoas(): ReactNode {
  const parametros = new URLSearchParams(window.location.search)
  const [nome, setNome] = useState(parametros.get('nome') ?? '')
  const [vinculo, setVinculo] = useState(parametros.get('vinculo') ?? '')
  const [situacao, setSituacao] = useState(parametros.get('situacao') ?? '')
  const [selecionadas, setSelecionadas] = useState<Set<string>>(new Set())
  const pagina = Number(parametros.get('pagina') ?? 1)
  const porPagina = Number(parametros.get('por_pagina') ?? 25)
  const consulta = useQuery({ queryKey: ['pessoas', pagina, porPagina, vinculo, situacao], queryFn: () => listarPessoas({ pagina, por_pagina: porPagina, vinculo, situacao }) })
  const exportar = useMutation({ mutationFn: () => criarExportacao({ tipo: 'pessoas', formato: 'xlsx', selecionados: [...selecionadas], categorias: ['gerais', 'contatos', 'fe'] }) })
  const atualizarUrl = (mudancas: Record<string, string | number>): void => {
    const atual = new URLSearchParams(window.location.search)
    Object.entries(mudancas).forEach(([chave, valor]) => atual.set(chave, String(valor)))
    window.history.pushState({}, '', `${window.location.pathname}?${atual.toString()}`)
    window.dispatchEvent(new PopStateEvent('popstate'))
  }
  return <Stack spacing={3}>
    <Stack direction={{ xs: 'column', md: 'row' }} justifyContent="space-between" gap={2}>
      <Box><Typography variant="h4">Pessoas</Typography><Typography color="text.secondary">Cadastros da organização conforme seu alcance.</Typography></Box>
      <Stack direction="row" gap={1}><Button href="/painel/pessoas/busca">Buscar</Button><Button href="/painel/pessoas/nova" variant="contained">Cadastrar pessoa</Button></Stack>
    </Stack>
    <Stack direction={{ xs: 'column', md: 'row' }} gap={1}><TextField label="Filtrar por nome nesta página" value={nome} onChange={(e) => setNome(e.target.value)} onBlur={() => atualizarUrl({ nome, pagina: 1 })} sx={{ flex: 1 }} /><TextField select label="Vínculo" value={vinculo} onChange={(e) => { setVinculo(e.target.value); atualizarUrl({ vinculo: e.target.value, pagina: 1 }) }} sx={{ minWidth: 170 }}><MenuItem value="">Todos</MenuItem><MenuItem value="visitante">Visitante</MenuItem><MenuItem value="frequentador">Frequentador</MenuItem><MenuItem value="membro">Membro</MenuItem></TextField><TextField select label="Situação" value={situacao} onChange={(e) => { setSituacao(e.target.value); atualizarUrl({ situacao: e.target.value, pagina: 1 }) }} sx={{ minWidth: 150 }}><MenuItem value="">Todas</MenuItem><MenuItem value="ativa">Ativa</MenuItem><MenuItem value="inativa">Inativa</MenuItem></TextField><TextField select label="Por página" value={porPagina} onChange={(e) => atualizarUrl({ por_pagina: e.target.value, pagina: 1 })} sx={{ minWidth: 130 }}>{[25, 50, 100].map((valor) => <MenuItem key={valor} value={valor}>{valor}</MenuItem>)}</TextField></Stack>
    {selecionadas.size > 0 && <Stack direction="row" gap={1} alignItems="center"><Typography>{selecionadas.size} selecionada(s)</Typography><Button onClick={() => exportar.mutate()} disabled={exportar.isPending}>Exportar seleção</Button><Button onClick={() => setSelecionadas(new Set())}>Limpar seleção</Button></Stack>}
    {consulta.isPending && <EstadoCarregando texto="Carregando pessoas…" />}
    {consulta.isError && <EstadoErro titulo="Não foi possível carregar" texto={erroDe(consulta.error)} rotuloTentar="Tentar novamente" aoTentarDeNovo={() => void consulta.refetch()} />}
    {consulta.data?.data.length === 0 && <EstadoVazio titulo="Nenhuma pessoa encontrada" texto="Ajuste os filtros ou faça o primeiro cadastro." />}
    {consulta.data && consulta.data.data.length > 0 && <Table aria-label="Pessoas"><TableHead><TableRow><TableCell padding="checkbox"><Checkbox inputProps={{ 'aria-label': 'Selecionar pessoas exportáveis da página' }} checked={consulta.data.data.filter((p) => p.meta?.acoes?.includes('exportar')).length > 0 && consulta.data.data.filter((p) => p.meta?.acoes?.includes('exportar')).every((p) => selecionadas.has(p.uuid))} onChange={(_, marcado) => setSelecionadas(marcado ? new Set(consulta.data.data.filter((p) => p.meta?.acoes?.includes('exportar')).map((p) => p.uuid)) : new Set())} /></TableCell>{['Nome', 'Igreja', 'Vínculo', 'Situação', 'Ações'].map((titulo) => <TableCell key={titulo}>{titulo}</TableCell>)}</TableRow></TableHead><TableBody>{consulta.data.data.filter((p) => p.nome.toLocaleLowerCase().includes(nome.toLocaleLowerCase())).map((p) => <TableRow key={p.uuid}><TableCell padding="checkbox"><Checkbox inputProps={{ 'aria-label': `Selecionar ${p.nome}` }} disabled={!p.meta?.acoes?.includes('exportar')} checked={selecionadas.has(p.uuid)} onChange={(_, marcado) => setSelecionadas((atuais) => { const novas = new Set(atuais); if (marcado) novas.add(p.uuid); else novas.delete(p.uuid); return novas })} /></TableCell><TableCell>{p.nome}</TableCell><TableCell>{p.igreja?.nome ?? '—'}</TableCell><TableCell>{p.vinculo ?? '—'}</TableCell><TableCell>{p.situacao ?? '—'}</TableCell><TableCell><Button href={`/painel/pessoas/${p.uuid}`}>Abrir ficha</Button></TableCell></TableRow>)}</TableBody></Table>}
    {consulta.data && <Stack direction="row" justifyContent="space-between" alignItems="center"><Typography>{consulta.data.meta.total} registro(s)</Typography><Stack direction="row" gap={1}><Button disabled={pagina <= 1} onClick={() => atualizarUrl({ pagina: pagina - 1 })}>Anterior</Button><Button disabled={pagina * porPagina >= consulta.data.meta.total} onClick={() => atualizarUrl({ pagina: pagina + 1 })}>Próxima</Button></Stack></Stack>}
  </Stack>
}

export function TelaCadastroPessoa(): ReactNode {
  const [dados, setDados] = useState({ nome: '', nascimento: '', igreja: '', vinculo: 'visitante', celular: '' })
  const [continuar, setContinuar] = useState(false)
  const igrejas = useQuery({ queryKey: ['pessoas', 'igrejas-cadastro'], queryFn: () => listarRecurso('/painel/igrejas') })
  const duplicidade = useQuery({ queryKey: ['duplicidade', dados.nome, dados.nascimento], queryFn: () => verificarDuplicidade(dados.nome, dados.nascimento), enabled: dados.nome.trim().length >= 3 && Boolean(dados.nascimento) })
  const cadastro = useMutation({ mutationFn: () => cadastrarPessoa(dados), onSuccess: (resposta) => { if (continuar) setDados((atual) => ({ ...atual, nome: '', nascimento: '' })); else window.location.assign(`/painel/pessoas/${resposta.data.uuid}`) } })
  const enviar = (evento: FormEvent): void => { evento.preventDefault(); cadastro.mutate() }
  return <Stack component="form" spacing={2} onSubmit={enviar} maxWidth={720}>
    <Typography variant="h4">Cadastro rápido</Typography>
    <TextField required label="Nome completo" value={dados.nome} onChange={(e) => setDados({ ...dados, nome: e.target.value })} />
    <TextField type="date" label="Nascimento" slotProps={{ inputLabel: { shrink: true } }} value={dados.nascimento} onChange={(e) => setDados({ ...dados, nascimento: e.target.value })} />
    <TextField required select label="Igreja" value={dados.igreja} onChange={(e) => setDados({ ...dados, igreja: e.target.value })}>{igrejas.data?.data.map((igreja) => <MenuItem key={igreja.uuid} value={igreja.uuid}>{valorTexto(igreja.nome)}</MenuItem>)}</TextField>
    <TextField required label="Celular" value={dados.celular} onChange={(e) => setDados({ ...dados, celular: e.target.value })} />
    {duplicidade.data?.data.map((p) => <Card key={p.uuid} variant="outlined"><CardContent><Typography fontWeight={700}>Possível duplicidade: {p.nome}</Typography><Typography>{p.igreja?.nome ?? 'Sem igreja'} · {p.vinculo ?? '—'}</Typography><Button href={`/painel/pessoas/${p.uuid}`}>{p.na_lixeira ? 'Recuperar ficha' : 'Abrir ficha'}</Button></CardContent></Card>)}
    {cadastro.isError && <Typography color="error" role="alert">{erroDe(cadastro.error)}</Typography>}
    <Stack direction="row" gap={1}><Button type="submit" variant="contained" disabled={cadastro.isPending} onClick={() => setContinuar(false)}>Salvar</Button><Button type="submit" disabled={cadastro.isPending} onClick={() => setContinuar(true)}>Salvar e cadastrar outra</Button></Stack>
  </Stack>
}

export function TelaBuscaPessoas(): ReactNode {
  const [termo, setTermo] = useState('')
  const [igrejaDestino, setIgrejaDestino] = useState('')
  const busca = useQuery({ queryKey: ['busca-pessoas', termo], queryFn: () => buscarPessoas(termo), enabled: termo.trim().length >= 3 })
  return <Stack spacing={2}><Typography variant="h4">Busca na denominação</Typography><TextField label="Nome, CPF, e-mail ou telefone" value={termo} onChange={(e) => setTermo(e.target.value)} helperText="Digite pelo menos 3 caracteres." /><TextField label="UUID da igreja de destino" value={igrejaDestino} onChange={(e) => setIgrejaDestino(e.target.value)} helperText="Necessário apenas para solicitar transferência." />{busca.isFetching && <LinearProgress />}{busca.data?.data.map((p) => <Card key={p.uuid} variant="outlined"><CardContent><Typography variant="h6">{p.nome}</Typography><Typography>{p.igreja?.nome ?? '—'} · {p.vinculo ?? '—'}</Typography>{p.meta?.acoes?.includes('abrir') ? <Button href={`/painel/pessoas/${p.uuid}`}>Abrir ficha</Button> : <Button disabled={!igrejaDestino} onClick={() => void enviarRecurso('POST', '/painel/transferencias', { pessoa: p.uuid, igreja_destino: igrejaDestino })}>Pedir transferência</Button>}</CardContent></Card>)}</Stack>
}

export function TelaFichaPessoa({ uuid }: { uuid: string }): ReactNode {
  const ficha = useQuery({ queryKey: ['ficha', uuid], queryFn: () => obterFicha(uuid) })
  const [aba, setAba] = useState(0)
  if (ficha.isPending) return <EstadoCarregando texto="Carregando ficha…" />
  if (ficha.isError) return <EstadoErro titulo="Ficha indisponível" texto={erroDe(ficha.error)} rotuloTentar="Tentar novamente" aoTentarDeNovo={() => void ficha.refetch()} />
  const pessoa = ficha.data.data
  const percentual = typeof pessoa.completude === 'object' && pessoa.completude ? pessoa.completude.percentual : Number(pessoa.completude ?? 0)
  const grupos = Object.entries(pessoa.campos ?? {}).reduce<Record<string, Array<[string, unknown]>>>((acc, [chave, valor]) => { const grupo = chave.includes('.') ? (chave.split('.')[0] ?? 'pessoais') : 'pessoais'; (acc[grupo] ??= []).push([chave, valor]); return acc }, {})
  const secoes = Object.entries(grupos)
  const secao = secoes[aba]
  const podeEditar = ficha.data.meta.acoes.includes('editar')
  return <Stack spacing={3}><Stack direction="row" gap={2} alignItems="center">{pessoa.foto_url && <Box component="img" src={pessoa.foto_url} alt="Foto" sx={{ width: 88, height: 88, borderRadius: '50%', objectFit: 'cover' }} />}<Box><Typography variant="h4">{valorTexto(pessoa.campos.nome ?? pessoa.nome)}</Typography><Typography>{pessoa.vinculo} · {pessoa.situacao}</Typography></Box></Stack><AlteracoesFicha uuid={uuid} vinculo={pessoa.vinculo} situacao={pessoa.situacao} podeEditar={podeEditar} /><Box><Typography>Completude da ficha: {percentual}%</Typography><LinearProgress variant="determinate" value={percentual} /></Box><Tabs value={aba} onChange={(_, valor: number) => setAba(valor)} variant="scrollable">{secoes.map(([nome]) => <Tab key={nome} label={nome} />)}<Tab label="Linha do tempo" /><Tab label="Relacionamentos" /><Tab label="Funções" /></Tabs>{secao && <Card><CardContent><Stack spacing={1}>{secao[1].map(([chave, valor]) => <Box key={chave}><Typography variant="caption" color="text.secondary">{chave.split('.').at(-1)}</Typography><Typography>{valorTexto(valor)}</Typography></Box>)}</Stack></CardContent></Card>}{aba === secoes.length && <RegistrosFicha uuid={uuid} tipo="linha-do-tempo" podeEditar={podeEditar} />}{aba === secoes.length + 1 && <RegistrosFicha uuid={uuid} tipo="relacionamentos" podeEditar={podeEditar} />}{aba === secoes.length + 2 && <RegistrosFicha uuid={uuid} tipo="funcoes" podeEditar={podeEditar} />}</Stack>
}

export function TelaFichaPessoaRoteada(): ReactNode {
  const { uuid } = useParams({ strict: false })
  return uuid ? <TelaFichaPessoa uuid={uuid} /> : <EstadoErro titulo="Pessoa não informada" texto="A rota não contém um identificador válido." rotuloTentar="Voltar" aoTentarDeNovo={() => window.history.back()} />
}

function PainelRecurso({ caminho, vazio }: { caminho: string; vazio: string }): ReactNode {
  const consulta = useQuery({ queryKey: ['recurso', caminho], queryFn: () => listarRecurso(caminho) })
  if (consulta.isPending) return <EstadoCarregando texto="Carregando…" />
  if (consulta.isError) return <EstadoErro titulo="Não foi possível carregar" texto={erroDe(consulta.error)} rotuloTentar="Tentar novamente" aoTentarDeNovo={() => void consulta.refetch()} />
  if (consulta.data.data.length === 0) return <EstadoVazio titulo={vazio} texto="Não há registros para exibir." />
  return <Stack spacing={1}>{consulta.data.data.map((item) => <Card key={item.uuid} variant="outlined"><CardContent>{Object.entries(item).slice(0, 6).map(([chave, valor]) => <Typography key={chave}><b>{chave}:</b> {valorTexto(valor)}</Typography>)}</CardContent></Card>)}</Stack>
}

export function TelaOperacoesPessoas(): ReactNode {
  return <TelaOperacoesPessoasCompleta />
}

export function TelaDatasPessoas(): ReactNode {
  const [aba, setAba] = useState(0)
  const [periodo, setPeriodo] = useState('mes')
  const [exportacao, setExportacao] = useState<string | null>(null)
  const caminhos = ['/painel/novos-convertidos', `/painel/aniversarios?periodo=${periodo}`]
  const estado = useQuery({ queryKey: ['exportacao', exportacao], queryFn: () => obterExportacao(exportacao ?? ''), enabled: exportacao !== null, refetchInterval: 5000 })
  const exportar = useMutation({ mutationFn: (formato: string) => criarExportacao({ tipo: aba === 0 ? 'novos_convertidos' : 'aniversarios', formato, filtro: aba === 1 && periodo === 'mes' ? { mes: new Date().getMonth() + 1 } : {}, categorias: ['gerais', 'contatos', 'fe'] }), onSuccess: (resposta) => { setExportacao(String(resposta.data.uuid)); setAba(2) } })
  return <Stack spacing={2}><Typography variant="h4">Acompanhamentos e exportações</Typography><Tabs value={aba} onChange={(_, valor: number) => setAba(valor)}><Tab label="Novos convertidos" /><Tab label="Datas comemorativas" /><Tab label="Exportações" /></Tabs>{aba === 1 && <FormControl><InputLabel>Período</InputLabel><Select label="Período" value={periodo} onChange={(e) => setPeriodo(e.target.value)}><MenuItem value="dia">Dia</MenuItem><MenuItem value="semana">Semana</MenuItem><MenuItem value="mes">Mês</MenuItem></Select></FormControl>}{aba < 2 && <><Stack direction="row" gap={1}><Button onClick={() => exportar.mutate('xlsx')}>Exportar XLSX</Button><Button onClick={() => exportar.mutate('csv')}>Exportar CSV</Button></Stack><PainelRecurso caminho={caminhos[aba] ?? '/painel/novos-convertidos'} vazio="Nenhum registro no período." /></>}{aba === 2 && (estado.data ? <Card><CardContent><Typography>Estado: {valorTexto(estado.data.data.estado)}</Typography>{typeof estado.data.data.url === 'string' && <Button href={estado.data.data.url}>Baixar arquivo</Button>}</CardContent></Card> : <EstadoVazio titulo="Nenhuma exportação nesta sessão" texto="Gere uma exportação nas abas ao lado." />)}</Stack>
}
