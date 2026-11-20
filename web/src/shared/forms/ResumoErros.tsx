import Alert from '@mui/material/Alert'
import AlertTitle from '@mui/material/AlertTitle'
import type { ReactNode } from 'react'
import type { ErroForaDaTela } from './erros422'

interface Props {
  erros: readonly ErroForaDaTela[]
  /** Título já traduzido pela feature (nenhum texto fixo em componente, OIK-ADR-0006 §13). */
  titulo: string
}

/** Resumo dos erros de campos que não estão na tela (OIK-ADR-0006 §5). */
export function ResumoErros({ erros, titulo }: Props): ReactNode {
  if (erros.length === 0) return null

  return (
    <Alert severity="error" role="alert">
      <AlertTitle>{titulo}</AlertTitle>
      <ul>
        {erros.map((erro) => (
          <li key={erro.campo}>{erro.mensagem}</li>
        ))}
      </ul>
    </Alert>
  )
}
