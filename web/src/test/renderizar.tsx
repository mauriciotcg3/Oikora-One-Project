import { QueryClient } from '@tanstack/react-query'
import { render, type RenderResult } from '@testing-library/react'
import type { ReactElement } from 'react'
import { criarI18n, type RecursosI18n } from '../shared/i18n'
import { ProvedoresBase } from '../shared/ui'

/** Renderiza com tema, i18n e um QueryClient novo, sem repetição automática. */
export function renderizarComProvedores(ui: ReactElement, recursos: RecursosI18n): RenderResult {
  const clienteConsulta = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  })
  return render(
    <ProvedoresBase i18n={criarI18n(recursos)} clienteConsulta={clienteConsulta}>
      {ui}
    </ProvedoresBase>,
  )
}
