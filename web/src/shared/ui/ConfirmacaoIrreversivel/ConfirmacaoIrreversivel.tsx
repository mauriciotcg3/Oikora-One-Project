import Button from '@mui/material/Button'
import Dialog from '@mui/material/Dialog'
import DialogActions from '@mui/material/DialogActions'
import DialogContent from '@mui/material/DialogContent'
import DialogContentText from '@mui/material/DialogContentText'
import DialogTitle from '@mui/material/DialogTitle'
import { useId, useRef, type ReactNode } from 'react'
import { registrarEventoUi } from '../../observabilidade'

export interface ConfirmacaoIrreversivelProps {
  aberta: boolean
  /** Textos já traduzidos pela feature (OIK-ADR-0006 §13); diga o que será perdido ou feito. */
  titulo: string
  texto: string
  rotuloConfirmar: string
  rotuloCancelar: string
  aoConfirmar: () => void
  aoCancelar: () => void
}

/**
 * Confirmação antes de ação irreversível (OIK-ADR-0014). O foco começa em "cancelar"; Esc e o
 * fundo cancelam sem executar, e o foco volta ao controle que abriu o diálogo.
 */
export function ConfirmacaoIrreversivel({
  aberta,
  titulo,
  texto,
  rotuloConfirmar,
  rotuloCancelar,
  aoConfirmar,
  aoCancelar,
}: ConfirmacaoIrreversivelProps): ReactNode {
  const idTitulo = useId()
  const idTexto = useId()
  const botaoCancelar = useRef<HTMLButtonElement>(null)

  const confirmar = (): void => {
    registrarEventoUi('ui.confirmacao_irreversivel', { resultado: 'confirmada' })
    aoConfirmar()
  }
  const cancelar = (): void => {
    registrarEventoUi('ui.confirmacao_irreversivel', { resultado: 'cancelada' })
    aoCancelar()
  }

  return (
    <Dialog
      open={aberta}
      onClose={cancelar}
      role="alertdialog"
      aria-labelledby={idTitulo}
      aria-describedby={idTexto}
      slotProps={{ transition: { onEntered: () => botaoCancelar.current?.focus() } }}
    >
      <DialogTitle id={idTitulo}>{titulo}</DialogTitle>
      <DialogContent>
        <DialogContentText id={idTexto}>{texto}</DialogContentText>
      </DialogContent>
      <DialogActions>
        <Button ref={botaoCancelar} onClick={cancelar}>
          {rotuloCancelar}
        </Button>
        <Button variant="contained" color="error" onClick={confirmar}>
          {rotuloConfirmar}
        </Button>
      </DialogActions>
    </Dialog>
  )
}
