import Alert from '@mui/material/Alert'
import Snackbar from '@mui/material/Snackbar'
import { useState, type ReactNode } from 'react'

const TEMPO_MS = 6000

/**
 * Retorno curto depois de uma ação que não muda de tela (ex.: "Função encerrada."): anunciado de forma
 * educada (`status`) e some sozinho; não tira o foco de quem usa.
 */
export function useRetorno(): { retorno: ReactNode; avisar: (texto: string) => void } {
  const [texto, setTexto] = useState<string | null>(null)
  const retorno = (
    <Snackbar open={texto !== null} autoHideDuration={TEMPO_MS} onClose={() => setTexto(null)} anchorOrigin={{ vertical: 'bottom', horizontal: 'center' }}>
      <Alert severity="success" role="status" variant="filled" onClose={() => setTexto(null)} sx={{ width: '100%', maxWidth: 560 }}>
        {texto}
      </Alert>
    </Snackbar>
  )
  return { retorno, avisar: setTexto }
}
