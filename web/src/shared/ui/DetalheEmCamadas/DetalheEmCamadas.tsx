import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Drawer from '@mui/material/Drawer'
import Typography from '@mui/material/Typography'
import { useId, type ReactNode } from 'react'
import { registrarEventoUi } from '../../observabilidade'
import { DetalhesTecnicos, type DetalhesTecnicosProps, type ItemTecnico } from '../DetalhesTecnicos/DetalhesTecnicos'

export interface DetalheEmCamadasProps {
  aberto: boolean
  titulo: string
  /** Camada 1: o que a pessoa precisa entender, em linguagem da tarefa. */
  resumo: readonly ItemTecnico[]
  /** Camada 2: identificadores para o suporte, fechada até a pessoa pedir. */
  tecnico?: DetalhesTecnicosProps
  rotuloFechar: string
  aoFechar: () => void
}

/**
 * Painel de detalhe em camadas (OIK-ADR-0014): resumo primeiro, detalhe técnico sob demanda.
 * Abre como painel modal: Esc fecha e o foco volta ao botão que o abriu.
 */
export function DetalheEmCamadas({ aberto, titulo, resumo, tecnico, rotuloFechar, aoFechar }: DetalheEmCamadasProps): ReactNode {
  const idTitulo = useId()
  const fechar = (): void => {
    registrarEventoUi('ui.detalhe_fechado')
    aoFechar()
  }
  return (
    <Drawer
      anchor="right"
      open={aberto}
      onClose={fechar}
      slotProps={{ paper: { role: 'dialog', 'aria-modal': true, 'aria-labelledby': idTitulo, sx: { width: { xs: '100%', sm: 420 }, p: 3 } } }}
    >
      <Typography id={idTitulo} component="h2" variant="h5" sx={{ mb: 2 }}>
        {titulo}
      </Typography>
      <Box component="dl" sx={{ m: 0, display: 'grid', gridTemplateColumns: 'max-content 1fr', columnGap: 2, rowGap: 1 }}>
        {resumo.map((item) => (
          <Box key={item.rotulo} sx={{ display: 'contents' }}>
            <Box component="dt" sx={{ color: 'text.secondary' }}>
              {item.rotulo}
            </Box>
            <Box component="dd" sx={{ m: 0, overflowWrap: 'anywhere' }}>
              {item.valor}
            </Box>
          </Box>
        ))}
      </Box>
      {tecnico && (
        <Box sx={{ mt: 2 }}>
          <DetalhesTecnicos {...tecnico} />
        </Box>
      )}
      <Button variant="outlined" onClick={fechar} sx={{ mt: 3, alignSelf: 'flex-start' }}>
        {rotuloFechar}
      </Button>
    </Drawer>
  )
}
