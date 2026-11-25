import Alert from '@mui/material/Alert'
import AlertTitle from '@mui/material/AlertTitle'
import type { ReactNode } from 'react'

export interface EstadoAvisoProps {
  titulo: string
  texto?: string
}

/** Aviso: algo a rever sem impedir a tarefa; título em texto além do ícone e da cor. */
export function EstadoAviso({ titulo, texto }: EstadoAvisoProps): ReactNode {
  return (
    <Alert severity="warning" role="status">
      <AlertTitle>{titulo}</AlertTitle>
      {texto}
    </Alert>
  )
}
