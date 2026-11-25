import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Typography from '@mui/material/Typography'
import type { ReactNode } from 'react'

export interface AcaoDoEstado {
  rotulo: string
  aoClicar: () => void
}

export interface EstadoVazioProps {
  titulo: string
  texto: string
  acao?: AcaoDoEstado
}

/** Vazio: diz o que aconteceu e qual é o próximo passo. */
export function EstadoVazio({ titulo, texto, acao }: EstadoVazioProps): ReactNode {
  return (
    <Box sx={{ display: 'grid', justifyItems: 'center', gap: 1, p: 4, textAlign: 'center' }}>
      <Typography component="h2" variant="h6">
        {titulo}
      </Typography>
      <Typography color="text.secondary">{texto}</Typography>
      {acao && (
        <Button variant="outlined" onClick={acao.aoClicar}>
          {acao.rotulo}
        </Button>
      )}
    </Box>
  )
}
