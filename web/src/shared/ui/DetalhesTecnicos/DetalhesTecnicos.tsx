import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import { useId, useState, type ReactNode } from 'react'
import { registrarEventoUi } from '../../observabilidade'

export interface ItemTecnico {
  rotulo: string
  valor: string
}

export interface DetalhesTecnicosProps {
  /** Textos já traduzidos pela feature (OIK-ADR-0006 §13). */
  rotulo: string
  rotuloCopiar: string
  itens: readonly ItemTecnico[]
}

/** Camada técnica sob demanda: identificadores para o suporte, fechada por padrão (OIK-ADR-0014). */
export function DetalhesTecnicos({ rotulo, rotuloCopiar, itens }: DetalhesTecnicosProps): ReactNode {
  const [aberto, setAberto] = useState(false)
  const idRegiao = useId()

  const alternar = (): void => {
    if (!aberto) registrarEventoUi('ui.detalhes_tecnicos_abertos', { itens: itens.length })
    setAberto(!aberto)
  }
  const copiar = (): void => {
    void navigator.clipboard.writeText(itens.map((item) => `${item.rotulo}: ${item.valor}`).join('\n'))
  }

  return (
    <Box>
      <Button size="small" variant="text" aria-expanded={aberto} aria-controls={idRegiao} onClick={alternar}>
        {rotulo}
      </Button>
      {aberto && (
        <Box id={idRegiao} role="group" aria-label={rotulo} sx={{ mt: 1 }}>
          <Box component="dl" sx={{ m: 0, display: 'grid', gridTemplateColumns: 'max-content 1fr', columnGap: 2, rowGap: 0.5 }}>
            {itens.map((item) => (
              <Box key={item.rotulo} sx={{ display: 'contents' }}>
                <Box component="dt" sx={{ color: 'text.secondary' }}>
                  {item.rotulo}
                </Box>
                <Box component="dd" sx={{ m: 0, fontFamily: 'monospace', overflowWrap: 'anywhere' }}>
                  {item.valor}
                </Box>
              </Box>
            ))}
          </Box>
          <Button size="small" variant="outlined" sx={{ mt: 1 }} onClick={copiar}>
            {rotuloCopiar}
          </Button>
        </Box>
      )}
    </Box>
  )
}
