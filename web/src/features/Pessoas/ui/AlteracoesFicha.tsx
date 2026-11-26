import Alert from '@mui/material/Alert'
import Button from '@mui/material/Button'
import Dialog from '@mui/material/Dialog'
import DialogActions from '@mui/material/DialogActions'
import DialogContent from '@mui/material/DialogContent'
import DialogContentText from '@mui/material/DialogContentText'
import DialogTitle from '@mui/material/DialogTitle'
import MenuItem from '@mui/material/MenuItem'
import TextField from '@mui/material/TextField'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useId, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { registrarEventoUi } from '../../../shared/observabilidade'
import { enviarRecurso, listarRecurso, type RegistroGenerico } from '../api/pessoas'
import { useMensagemDeErro } from './comum'
import { useRetorno } from './Retorno'

interface Props {
  uuid: string
  vinculo?: string
  situacao?: string
  podeEditar: boolean
}

interface ItemMotivo extends RegistroGenerico {
  nome: string
  ativo?: boolean
}

const PROXIMO_VINCULO: Readonly<Record<string, string | undefined>> = { visitante: 'frequentador', frequentador: 'membro', membro: undefined }

/** Vínculo só avança um passo e situação alterna; as duas mudanças pedem um motivo da lista configurada. */
export function AlteracoesFicha({ uuid, vinculo, situacao, podeEditar }: Props): ReactNode {
  const { t } = useTranslation('pessoas')
  const mensagemDeErro = useMensagemDeErro()
  const cliente = useQueryClient()
  const { retorno, avisar } = useRetorno()
  const idTitulo = useId()
  const [dialogo, setDialogo] = useState<'vinculo' | 'situacao' | null>(null)
  const [motivo, setMotivo] = useState('')
  const [matricula, setMatricula] = useState('')
  const destinoVinculo = vinculo ? PROXIMO_VINCULO[vinculo] : undefined
  const destinoSituacao = situacao === 'ativa' ? 'inativa' : 'ativa'
  const listaMotivo = dialogo === 'vinculo' ? 'motivo_vinculo' : destinoSituacao === 'inativa' ? 'motivo_inativacao' : 'motivo_reativacao'
  const motivos = useQuery({ queryKey: ['listas', listaMotivo], queryFn: () => listarRecurso(`/painel/listas/${listaMotivo}`), enabled: dialogo !== null })
  const organizacao = useQuery({
    queryKey: ['organizacao', 'modo-matricula'],
    queryFn: () => listarRecurso('/painel/organizacao'),
    enabled: dialogo === 'vinculo' && destinoVinculo === 'membro',
  })
  const modoMatricula = (organizacao.data as unknown as { data?: { modo_matricula?: string } } | undefined)?.data?.modo_matricula
  const rotuloVinculo = destinoVinculo ? t(`vinculos.${destinoVinculo}` as never, { defaultValue: destinoVinculo }) : ''

  const salvar = useMutation({
    mutationFn: () =>
      dialogo === 'vinculo'
        ? enviarRecurso('POST', `/painel/pessoas/${uuid}/vinculo`, { vinculo: destinoVinculo, motivo, ...(modoMatricula === 'manual' ? { matricula } : {}) })
        : enviarRecurso('POST', `/painel/pessoas/${uuid}/situacao`, { situacao: destinoSituacao, motivo }),
    onSuccess: async () => {
      registrarEventoUi('ui.ficha_alterada', { tipo: dialogo ?? '' })
      avisar(
        dialogo === 'vinculo'
          ? t('alteracoes.sucessoVinculo', { vinculo: rotuloVinculo })
          : t('alteracoes.sucessoSituacao', { situacao: t(`situacoes.${destinoSituacao}`) }),
      )
      setDialogo(null)
      await cliente.invalidateQueries({ queryKey: ['ficha', uuid] })
    },
  })

  if (!podeEditar) return null
  const itens = ((motivos.data?.data ?? []) as ItemMotivo[]).filter((item) => item.ativo !== false)
  const precisaMatricula = dialogo === 'vinculo' && destinoVinculo === 'membro' && modoMatricula === 'manual'
  const podeSalvar = motivo !== '' && (!precisaMatricula || matricula.trim() !== '')
  const abrir = (tipo: 'vinculo' | 'situacao'): void => {
    setDialogo(tipo)
    setMotivo('')
    setMatricula('')
    salvar.reset()
  }
  const titulo =
    dialogo === 'vinculo' ? t('alteracoes.tituloVinculo', { vinculo: rotuloVinculo }) : destinoSituacao === 'inativa' ? t('alteracoes.tituloInativar') : t('alteracoes.tituloReativar')

  return (
    <>
      {destinoVinculo && (
        <Button variant="contained" onClick={() => abrir('vinculo')}>
          {t('alteracoes.avancar', { vinculo: rotuloVinculo })}
        </Button>
      )}
      {situacao && (
        <Button variant="outlined" color={destinoSituacao === 'inativa' ? 'error' : 'primary'} onClick={() => abrir('situacao')}>
          {destinoSituacao === 'inativa' ? t('alteracoes.inativar') : t('alteracoes.reativar')}
        </Button>
      )}
      <Dialog open={dialogo !== null} onClose={() => setDialogo(null)} fullWidth maxWidth="sm" aria-labelledby={idTitulo}>
        <DialogTitle id={idTitulo}>{titulo}</DialogTitle>
        <DialogContent sx={{ display: 'grid', gap: 2.5 }}>
          <DialogContentText>{dialogo === 'vinculo' ? t('alteracoes.textoVinculo') : t('alteracoes.textoSituacao')}</DialogContentText>
          <TextField
            select
            required
            label={t('alteracoes.motivo')}
            value={motivo}
            onChange={(evento) => setMotivo(evento.target.value)}
            disabled={motivos.isPending}
            helperText={motivos.isSuccess && itens.length === 0 ? t('alteracoes.semMotivos') : undefined}
          >
            {itens.map((item) => (
              <MenuItem key={item.uuid} value={item.uuid}>
                {item.nome}
              </MenuItem>
            ))}
          </TextField>
          {precisaMatricula && (
            <TextField label={t('alteracoes.matricula')} value={matricula} onChange={(evento) => setMatricula(evento.target.value)} required helperText={t('alteracoes.matriculaAjuda')} />
          )}
          {salvar.isError && <Alert severity="error">{mensagemDeErro(salvar.error)}</Alert>}
        </DialogContent>
        <DialogActions sx={{ px: 3, pb: 2.5 }}>
          <Button onClick={() => setDialogo(null)}>{t('comum.cancelar')}</Button>
          <Button variant="contained" disabled={!podeSalvar || salvar.isPending} onClick={() => salvar.mutate()}>
            {t('alteracoes.confirmar')}
          </Button>
        </DialogActions>
      </Dialog>
      {retorno}
    </>
  )
}
