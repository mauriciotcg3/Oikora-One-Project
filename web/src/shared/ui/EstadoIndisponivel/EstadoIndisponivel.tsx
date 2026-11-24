import Alert from '@mui/material/Alert'
import AlertTitle from '@mui/material/AlertTitle'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import SvgIcon from '@mui/material/SvgIcon'
import type { ReactNode } from 'react'
import { DetalhesTecnicos, type DetalhesTecnicosProps } from '../DetalhesTecnicos/DetalhesTecnicos'

export interface EstadoIndisponivelProps {
  titulo: string
  texto: string
  rotuloTentar?: string
  aoTentarDeNovo?: () => void
  detalhes?: DetalhesTecnicosProps
}

function IconeIndisponivel(): ReactNode {
  return (
    <SvgIcon fontSize="inherit" aria-hidden="true">
      <path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm5 11H7v-2h10v2z" />
    </SvgIcon>
  )
}

/** Indisponível: o serviço não responde; ícone próprio e título, não só cor. */
export function EstadoIndisponivel({ titulo, texto, rotuloTentar, aoTentarDeNovo, detalhes }: EstadoIndisponivelProps): ReactNode {
  return (
    <Alert severity="warning" role="alert" icon={<IconeIndisponivel />}>
      <AlertTitle>{titulo}</AlertTitle>
      {texto}
      <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1, mt: 1 }}>
        {rotuloTentar && aoTentarDeNovo && (
          <Button size="small" variant="outlined" color="inherit" onClick={aoTentarDeNovo}>
            {rotuloTentar}
          </Button>
        )}
        {detalhes && <DetalhesTecnicos {...detalhes} />}
      </Box>
    </Alert>
  )
}
