import type { ReactNode } from 'react'
import { TelaSolicitacoes } from '../../features/Autorizacao'
import { useCapacidades } from './capacidades'

/** Composição da feature com as capacidades do shell; a API continua sendo a autoridade. */
export function PaginaSolicitacoes(): ReactNode {
  const { tem } = useCapacidades()
  return <TelaSolicitacoes podeAprovar={tem('permissoes.solicitacoes.aprovar')} />
}
