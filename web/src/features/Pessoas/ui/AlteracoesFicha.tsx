import Alert from '@mui/material/Alert'
import Button from '@mui/material/Button'
import Dialog from '@mui/material/Dialog'
import DialogActions from '@mui/material/DialogActions'
import DialogContent from '@mui/material/DialogContent'
import DialogTitle from '@mui/material/DialogTitle'
import MenuItem from '@mui/material/MenuItem'
import Stack from '@mui/material/Stack'
import TextField from '@mui/material/TextField'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useMemo, useState, type ReactNode } from 'react'
import { ErroHttp } from '../../../shared/http'
import { enviarRecurso, listarRecurso, type RegistroGenerico } from '../api/pessoas'

interface Props {
  uuid: string
  vinculo?: string
  situacao?: string
  podeEditar: boolean
}

interface ItemMotivo extends RegistroGenerico { nome: string; ativo?: boolean }

const rotuloVinculo: Record<string, string> = { visitante: 'Visitante', frequentador: 'Frequentador', membro: 'Membro' }
const proximoVinculo: Record<string, string | undefined> = { visitante: 'frequentador', frequentador: 'membro', membro: undefined }

function mensagem(erro: unknown): string {
  if (erro instanceof ErroHttp) {
    if (erro.codigo === 'matricula_automatica_indisponivel') return 'A matrícula é automática nesta organização e deve ser deixada em branco.'
    return erro.codigo ?? 'Não foi possível salvar a alteração.'
  }
  return 'Não foi possível salvar a alteração.'
}

export function AlteracoesFicha({ uuid, vinculo, situacao, podeEditar }: Props): ReactNode {
  const cliente = useQueryClient()
  const [dialogo, setDialogo] = useState<'vinculo' | 'situacao' | null>(null)
  const [motivo, setMotivo] = useState('')
  const [matricula, setMatricula] = useState('')
  const destinoVinculo = vinculo ? proximoVinculo[vinculo] : undefined
  const destinoSituacao = situacao === 'ativa' ? 'inativa' : 'ativa'
  const listaMotivo = dialogo === 'vinculo' ? 'motivo_vinculo' : destinoSituacao === 'inativa' ? 'motivo_inativacao' : 'motivo_reativacao'
  const motivos = useQuery({
    queryKey: ['listas', listaMotivo],
    queryFn: () => listarRecurso(`/painel/listas/${listaMotivo}`),
    enabled: dialogo !== null,
  })
  const organizacao = useQuery({
    queryKey: ['organizacao', 'modo-matricula'],
    queryFn: () => listarRecurso('/painel/organizacao'),
    enabled: dialogo === 'vinculo' && destinoVinculo === 'membro',
  })
  const modoMatricula = useMemo(() => {
    const resposta = organizacao.data as unknown as { data?: { modo_matricula?: string } } | undefined
    return resposta?.data?.modo_matricula
  }, [organizacao.data])
  const salvar = useMutation({
    mutationFn: () => dialogo === 'vinculo'
      ? enviarRecurso('POST', `/painel/pessoas/${uuid}/vinculo`, { vinculo: destinoVinculo, motivo, ...(modoMatricula === 'manual' ? { matricula } : {}) })
      : enviarRecurso('POST', `/painel/pessoas/${uuid}/situacao`, { situacao: destinoSituacao, motivo }),
    onSuccess: async () => {
      setDialogo(null)
      setMotivo('')
      setMatricula('')
      await cliente.invalidateQueries({ queryKey: ['ficha', uuid] })
    },
  })
  if (!podeEditar) return null
  const itens = (motivos.data?.data ?? []) as ItemMotivo[]
  const precisaMatricula = dialogo === 'vinculo' && destinoVinculo === 'membro' && modoMatricula === 'manual'
  const podeSalvar = motivo !== '' && (!precisaMatricula || matricula.trim() !== '')
  const abrir = (tipo: 'vinculo' | 'situacao'): void => { setDialogo(tipo); setMotivo(''); setMatricula(''); salvar.reset() }
  return <>
    <Stack direction={{ xs: 'column', sm: 'row' }} gap={1}>
      {destinoVinculo && <Button variant="outlined" onClick={() => abrir('vinculo')}>Avançar para {rotuloVinculo[destinoVinculo]}</Button>}
      {situacao && <Button variant="outlined" onClick={() => abrir('situacao')}>{destinoSituacao === 'inativa' ? 'Inativar pessoa' : 'Reativar pessoa'}</Button>}
    </Stack>
    <Dialog open={dialogo !== null} onClose={() => setDialogo(null)} fullWidth maxWidth="sm">
      <DialogTitle>{dialogo === 'vinculo' ? `Alterar vínculo para ${rotuloVinculo[destinoVinculo ?? ''] ?? destinoVinculo}` : `${destinoSituacao === 'inativa' ? 'Inativar' : 'Reativar'} pessoa`}</DialogTitle>
      <DialogContent><Stack spacing={2} sx={{ pt: 1 }}>
        {salvar.isError && <Alert severity="error">{mensagem(salvar.error)}</Alert>}
        <TextField select label="Motivo" value={motivo} onChange={(evento) => setMotivo(evento.target.value)} disabled={motivos.isPending} required>
          {itens.filter((item) => item.ativo !== false).map((item) => <MenuItem key={item.uuid} value={item.uuid}>{item.nome}</MenuItem>)}
        </TextField>
        {precisaMatricula && <TextField label="Matrícula" value={matricula} onChange={(evento) => setMatricula(evento.target.value)} required helperText="A organização usa matrícula manual para membros." />}
      </Stack></DialogContent>
      <DialogActions><Button onClick={() => setDialogo(null)}>Cancelar</Button><Button variant="contained" disabled={!podeSalvar || salvar.isPending} onClick={() => salvar.mutate()}>Confirmar</Button></DialogActions>
    </Dialog>
  </>
}
