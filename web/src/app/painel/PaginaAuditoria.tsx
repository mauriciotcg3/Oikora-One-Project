import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { EstadoAviso, EstadoVazio } from '../../shared/ui'
import { useCapacidades } from './capacidades'

/** Rota do menu "Auditoria". A consulta chega na T-55; até lá, o acesso direto já respeita a capacidade. */
export function PaginaAuditoria(): ReactNode {
  const { t } = useTranslation('painel')
  const { tem } = useCapacidades()

  if (!tem('auditoria.ver')) return <EstadoAviso titulo={t('auditoria.negadoTitulo')} texto={t('auditoria.negadoTexto')} />
  return <EstadoVazio titulo={t('auditoria.titulo')} texto={t('auditoria.emBreve')} />
}
