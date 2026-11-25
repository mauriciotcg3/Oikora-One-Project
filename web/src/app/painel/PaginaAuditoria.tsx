import { lazy, Suspense, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { EstadoAviso, EstadoCarregando } from '../../shared/ui'
import { useCapacidades } from './capacidades'
import { i18nPainel } from './i18n'

const TelaAuditoria = lazy(async () => {
  const modulo = await import('../../features/Auditoria')
  i18nPainel.addResourceBundle('pt-BR', 'auditoria', modulo.auditoriaPtBR, true, true)

  return { default: modulo.TelaAuditoria }
})

/** Rota protegida por capacidade; a feature e o DataGrid permanecem fora do chunk de entrada. */
export function PaginaAuditoria(): ReactNode {
  const { t } = useTranslation('painel')
  const { tem } = useCapacidades()

  if (!tem('auditoria.ver')) return <EstadoAviso titulo={t('auditoria.negadoTitulo')} texto={t('auditoria.negadoTexto')} />
  return <Suspense fallback={<EstadoCarregando texto={t('auditoria.carregando')} />}><TelaAuditoria /></Suspense>
}
