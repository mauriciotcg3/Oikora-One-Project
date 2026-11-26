import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import { useId, useState, type ReactNode } from 'react'

export interface AjudaContextualProps {
  /** Rótulo do botão, já traduzido (ex.: "Ajuda sobre filtros"). */
  rotulo: string
  children: ReactNode
}

/** Ajuda no ponto da tarefa, aberta e fechada por teclado; mesma posição em toda tela (OIK-ADR-0014). */
export function AjudaContextual({ rotulo, children }: AjudaContextualProps): ReactNode {
  const [aberta, setAberta] = useState(false)
  const idRegiao = useId()
  return (
    <Box>
      <Button size="small" variant="text" aria-expanded={aberta} aria-controls={idRegiao} onClick={() => setAberta(!aberta)}>
        {rotulo}
      </Button>
      {aberta && (
        <Box id={idRegiao} role="note" sx={{ mt: 1, p: 2, borderRadius: 1, bgcolor: 'background.default' }}>
          {children}
        </Box>
      )}
    </Box>
  )
}
