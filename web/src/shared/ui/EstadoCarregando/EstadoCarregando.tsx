import Box from '@mui/material/Box'
import CircularProgress from '@mui/material/CircularProgress'
import Typography from '@mui/material/Typography'
import type { ReactNode } from 'react'

export interface EstadoCarregandoProps {
  texto: string
}

/** Carregando: região `status` ocupada, com texto da tarefa (nada de spinner mudo). */
export function EstadoCarregando({ texto }: EstadoCarregandoProps): ReactNode {
  return (
    <Box role="status" aria-busy="true" sx={{ display: 'flex', alignItems: 'center', gap: 2, p: 3 }}>
      <CircularProgress size={24} aria-hidden="true" />
      <Typography>{texto}</Typography>
    </Box>
  )
}
