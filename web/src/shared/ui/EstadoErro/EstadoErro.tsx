import Alert from '@mui/material/Alert'
import AlertTitle from '@mui/material/AlertTitle'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import type { ReactNode } from 'react'
import { DetalhesTecnicos, type DetalhesTecnicosProps } from '../DetalhesTecnicos/DetalhesTecnicos'

export interface EstadoErroProps {
  titulo: string
  texto: string
  /** Ação de repetir: só aparece com os dois. */
  rotuloTentar?: string
  aoTentarDeNovo?: () => void
  /** `request_id` e `trace_id`, atrás de "Detalhes técnicos". */
  detalhes?: DetalhesTecnicosProps
}

/** Erro recuperável: mensagem da tarefa, ação de repetir e identificadores só sob demanda. */
export function EstadoErro({ titulo, texto, rotuloTentar, aoTentarDeNovo, detalhes }: EstadoErroProps): ReactNode {
  return (
    <Alert severity="error" role="alert">
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
