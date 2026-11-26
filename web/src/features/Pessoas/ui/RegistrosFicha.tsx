import Alert from '@mui/material/Alert'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import MenuItem from '@mui/material/MenuItem'
import Stack from '@mui/material/Stack'
import TextField from '@mui/material/TextField'
import Typography from '@mui/material/Typography'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type ReactNode } from 'react'
import { ErroHttp } from '../../../shared/http'
import { EstadoCarregando, EstadoErro, EstadoVazio } from '../../../shared/ui'
import { enviarRecurso, listarRecurso, type RegistroGenerico } from '../api/pessoas'

type TipoRegistro = 'linha-do-tempo' | 'relacionamentos' | 'funcoes'
const erroDe = (erro: unknown): string => erro instanceof ErroHttp ? (erro.codigo ?? 'Não foi possível salvar.') : 'Não foi possível salvar.'
const texto = (valor: unknown): string => typeof valor === 'string' || typeof valor === 'number' ? String(valor) : '—'

function FormularioLinhaDoTempo({ uuid, aoSalvar }: { uuid: string; aoSalvar: () => void }): ReactNode {
  const [ocorridoEm, setOcorridoEm] = useState('')
  const [descricao, setDescricao] = useState('')
  const salvar = useMutation({ mutationFn: () => enviarRecurso('POST', `/painel/pessoas/${uuid}/linha-do-tempo`, { ocorrido_em: ocorridoEm, descricao }), onSuccess: () => { setDescricao(''); aoSalvar() } })
  return <Stack spacing={1}><TextField required type="datetime-local" label="Data e hora" value={ocorridoEm} onChange={(e) => setOcorridoEm(e.target.value)} slotProps={{ inputLabel: { shrink: true } }} /><TextField multiline label="Descrição do registro" value={descricao} onChange={(e) => setDescricao(e.target.value)} /><Button variant="contained" onClick={() => salvar.mutate()} disabled={!ocorridoEm || !descricao.trim() || salvar.isPending}>Registrar evento</Button>{salvar.isError && <Alert severity="error">{erroDe(salvar.error)}</Alert>}</Stack>
}

function FormularioRelacionamento({ uuid, aoSalvar }: { uuid: string; aoSalvar: () => void }): ReactNode {
  const [relacionada, setRelacionada] = useState('')
  const [tipo, setTipo] = useState('conjuge')
  const [descricao, setDescricao] = useState('')
  const salvar = useMutation({ mutationFn: () => enviarRecurso('POST', `/painel/pessoas/${uuid}/relacionamentos`, { relacionada, tipo, ...(descricao.trim() ? { descricao } : {}) }), onSuccess: () => { setRelacionada(''); setDescricao(''); aoSalvar() } })
  return <Stack spacing={1}><TextField label="UUID da pessoa relacionada" value={relacionada} onChange={(e) => setRelacionada(e.target.value)} /><TextField select label="Tipo" value={tipo} onChange={(e) => setTipo(e.target.value)}>{['conjuge', 'pai_mae', 'filho', 'irmao', 'avo', 'neto', 'responsavel_legal', 'dependente', 'outro'].map((valor) => <MenuItem key={valor} value={valor}>{valor.replaceAll('_', ' ')}</MenuItem>)}</TextField>{tipo === 'outro' && <TextField required label="Descrição" value={descricao} onChange={(e) => setDescricao(e.target.value)} />}<Button variant="contained" onClick={() => salvar.mutate()} disabled={!relacionada || (tipo === 'outro' && !descricao.trim()) || salvar.isPending}>Adicionar relacionamento</Button>{salvar.isError && <Alert severity="error">{erroDe(salvar.error)}</Alert>}</Stack>
}

function FormularioFuncao({ uuid, aoSalvar }: { uuid: string; aoSalvar: () => void }): ReactNode {
  const catalogo = useQuery({ queryKey: ['lista-config', 'funcao'], queryFn: () => listarRecurso('/painel/listas/funcao') })
  const [funcao, setFuncao] = useState('')
  const [desde, setDesde] = useState('')
  const salvar = useMutation({ mutationFn: () => enviarRecurso('POST', `/painel/pessoas/${uuid}/funcoes`, { funcao, desde }), onSuccess: () => { setFuncao(''); aoSalvar() } })
  return <Stack spacing={1}><TextField select label="Função" value={funcao} onChange={(e) => setFuncao(e.target.value)}>{catalogo.data?.data.map((item) => <MenuItem key={item.uuid} value={item.uuid}>{texto(item.nome)}</MenuItem>)}</TextField><TextField required type="date" label="Desde" value={desde} onChange={(e) => setDesde(e.target.value)} slotProps={{ inputLabel: { shrink: true } }} /><Button variant="contained" onClick={() => salvar.mutate()} disabled={!funcao || !desde || salvar.isPending}>Atribuir função</Button>{salvar.isError && <Alert severity="error">{erroDe(salvar.error)}</Alert>}</Stack>
}

export function RegistrosFicha({ uuid, tipo, podeEditar }: { uuid: string; tipo: TipoRegistro; podeEditar: boolean }): ReactNode {
  const cliente = useQueryClient()
  const caminho = `/painel/pessoas/${uuid}/${tipo}`
  const consulta = useQuery({ queryKey: ['recurso', caminho], queryFn: () => listarRecurso(caminho) })
  const remover = useMutation({ mutationFn: ({ caminhoAcao, metodo, dados }: { caminhoAcao: string; metodo: 'DELETE' | 'PATCH'; dados?: unknown }) => enviarRecurso(metodo, caminhoAcao, dados), onSuccess: () => cliente.invalidateQueries({ queryKey: ['recurso', caminho] }) })
  const atualizar = (): void => { void cliente.invalidateQueries({ queryKey: ['recurso', caminho] }) }
  if (consulta.isPending) return <EstadoCarregando texto="Carregando registros…" />
  if (consulta.isError) return <EstadoErro titulo="Não foi possível carregar" texto={erroDe(consulta.error)} rotuloTentar="Tentar novamente" aoTentarDeNovo={() => void consulta.refetch()} />
  return <Stack spacing={2}>
    {podeEditar && tipo === 'linha-do-tempo' && <FormularioLinhaDoTempo uuid={uuid} aoSalvar={atualizar} />}
    {podeEditar && tipo === 'relacionamentos' && <FormularioRelacionamento uuid={uuid} aoSalvar={atualizar} />}
    {podeEditar && tipo === 'funcoes' && <FormularioFuncao uuid={uuid} aoSalvar={atualizar} />}
    {remover.isError && <Alert severity="error">{erroDe(remover.error)}</Alert>}
    {consulta.data.data.length === 0 && <EstadoVazio titulo="Nenhum registro" texto="Ainda não há informações nesta seção." />}
    {consulta.data.data.map((item: RegistroGenerico) => <Card key={item.uuid} variant="outlined"><CardContent><Stack spacing={0.5}>{Object.entries(item).filter(([chave]) => chave !== 'uuid').slice(0, 6).map(([chave, valor]) => <Typography key={chave}><b>{chave.replaceAll('_', ' ')}:</b> {typeof valor === 'object' && valor !== null ? Object.values(valor).map(texto).join(' · ') : texto(valor)}</Typography>)}{podeEditar && tipo === 'relacionamentos' && <Button color="error" onClick={() => remover.mutate({ caminhoAcao: `${caminho}/${item.uuid}`, metodo: 'DELETE' })}>Remover relacionamento</Button>}{podeEditar && tipo === 'funcoes' && !item.ate && <Button onClick={() => remover.mutate({ caminhoAcao: `${caminho}/${item.uuid}`, metodo: 'PATCH', dados: { ate: new Date().toISOString().slice(0, 10) } })}>Encerrar hoje</Button>}</Stack></CardContent></Card>)}
  </Stack>
}
