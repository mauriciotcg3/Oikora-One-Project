import Alert from '@mui/material/Alert'
import Button from '@mui/material/Button'
import Dialog from '@mui/material/Dialog'
import DialogActions from '@mui/material/DialogActions'
import DialogContent from '@mui/material/DialogContent'
import DialogContentText from '@mui/material/DialogContentText'
import DialogTitle from '@mui/material/DialogTitle'
import TextField from '@mui/material/TextField'
import { useState, type FormEvent, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { elevar } from '../api/elevacao'
import type { DesafioDoisFatores } from '../api/autenticacao'

interface Props {
  aberto: boolean
  /** `true` quando a elevação abriu e o pedido que a exigiu pode ser repetido. */
  aoConcluir: (elevou: boolean) => void
}

/**
 * Diálogo de elevação sobre a tela atual (OIK-ADR-0006 §10): senha e, quando a conta tem 2FA, o
 * código. A tela de baixo continua montada, com o formulário preenchido.
 */
export function DialogoElevacao({ aberto, aoConcluir }: Props): ReactNode {
  const { t } = useTranslation('identidade')
  const [senha, setSenha] = useState('')
  const [codigo, setCodigo] = useState('')
  const [desafio, setDesafio] = useState<DesafioDoisFatores | null>(null)
  const [erro, setErro] = useState<string | null>(null)
  const [enviando, setEnviando] = useState(false)

  const fechar = (elevou: boolean): void => {
    setSenha('')
    setCodigo('')
    setDesafio(null)
    setErro(null)
    aoConcluir(elevou)
  }

  const enviar = async (evento: FormEvent): Promise<void> => {
    evento.preventDefault()
    setEnviando(true)
    const resultado = await elevar(senha, desafio ? codigo : undefined)
    setEnviando(false)

    if (resultado.tipo === 'elevado') return fechar(true)
    if (resultado.tipo === 'dois_fatores') {
      setDesafio(resultado.desafio)
      setErro(null)
      return
    }
    setErro(t(resultado.tipo === 'cadastro_necessario' ? 'elevacao.cadastroNecessario' : `elevacao.erro.${resultado.motivo}`))
  }

  return (
    <Dialog open={aberto} onClose={() => fechar(false)} aria-labelledby="titulo-elevacao">
      <form onSubmit={(evento) => void enviar(evento)} noValidate>
        <DialogTitle id="titulo-elevacao">{t('elevacao.titulo')}</DialogTitle>
        <DialogContent sx={{ display: 'grid', gap: 2 }}>
          <DialogContentText>
            {desafio ? t('elevacao.textoCodigo', { destino: desafio.destino }) : t('elevacao.texto')}
          </DialogContentText>
          {erro && <Alert severity="error">{erro}</Alert>}
          <TextField
            label={t('elevacao.senha')}
            type="password"
            autoComplete="current-password"
            value={senha}
            onChange={(evento) => setSenha(evento.target.value)}
            required
          />
          {desafio && (
            <TextField
              label={t('elevacao.codigo')}
              inputMode="numeric"
              autoComplete="one-time-code"
              value={codigo}
              onChange={(evento) => setCodigo(evento.target.value)}
              required
            />
          )}
        </DialogContent>
        <DialogActions>
          <Button onClick={() => fechar(false)}>{t('elevacao.cancelar')}</Button>
          <Button type="submit" variant="contained" disabled={enviando || senha === ''}>
            {t('elevacao.confirmar')}
          </Button>
        </DialogActions>
      </form>
    </Dialog>
  )
}
