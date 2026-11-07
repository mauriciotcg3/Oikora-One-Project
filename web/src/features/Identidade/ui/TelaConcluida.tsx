import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Typography from '@mui/material/Typography'
import type { ReactNode } from 'react'
import { Cabecalho, Tela } from './partes'

interface Props {
  id: string
  icone: ReactNode
  titulo: string
  texto: string
  acao?: { rotulo: string; aoClicar: () => void }
}

/** Fim de um fluxo: marca, título focado, texto e, se houver, uma ação secundária. */
export function TelaConcluida({ id, icone, titulo, texto, acao }: Props): ReactNode {
  return (
    <Tela rotuloId={id}>
      <Box sx={{ display: 'grid', justifyItems: 'start', gap: 1.5 }}>
        <Box
          sx={{
            width: 64,
            height: 64,
            borderRadius: '50%',
            display: 'grid',
            placeItems: 'center',
            bgcolor: 'afundado',
            color: 'primary.main',
            '& svg': { fontSize: 30 },
          }}
        >
          {icone}
        </Box>
        <Cabecalho id={id} titulo={titulo} focar />
        <Typography color="text.secondary" sx={{ maxWidth: '36ch' }}>
          {texto}
        </Typography>
      </Box>
      {acao ? (
        <Button
          variant="contained"
          onClick={acao.aoClicar}
          sx={{ bgcolor: 'afundado', color: 'text.primary', '&:hover': { bgcolor: 'divider' } }}
        >
          {acao.rotulo}
        </Button>
      ) : null}
    </Tela>
  )
}
