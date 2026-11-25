import Alert from '@mui/material/Alert'
import AlertTitle from '@mui/material/AlertTitle'
import type { ReactNode } from 'react'

export interface EstadoSucessoProps {
  titulo: string
  texto?: string
}

/** Sucesso: anunciado de forma educada (`status`), sem tirar o foco de quem usa. */
export function EstadoSucesso({ titulo, texto }: EstadoSucessoProps): ReactNode {
  return (
    <Alert severity="success" role="status">
      <AlertTitle>{titulo}</AlertTitle>
      {texto}
    </Alert>
  )
}
