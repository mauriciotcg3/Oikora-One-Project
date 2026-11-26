import { QueryClient } from '@tanstack/react-query'
import { createMemoryHistory, createRootRoute, createRoute, createRouter, Outlet, RouterProvider } from '@tanstack/react-router'
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

/**
 * Renderiza a tela dentro de um roteador em memória (qualquer caminho mostra a tela), para telas que usam
 * `Link`, `useNavigate` ou a busca da URL. Devolve o roteador para conferir a navegação.
 */
export function renderizarNaRota(ui: ReactElement, recursos: RecursosI18n, caminho = '/') {
  const raiz = createRootRoute({ component: Outlet })
  const qualquer = createRoute({ getParentRoute: () => raiz, path: '$', component: () => ui })
  const inicio = createRoute({ getParentRoute: () => raiz, path: '/', component: () => ui })
  const roteador = createRouter({ routeTree: raiz.addChildren([inicio, qualquer]), history: createMemoryHistory({ initialEntries: [caminho] }) })
  const resultado = renderizarComProvedores(<RouterProvider router={roteador} />, recursos)
  return { ...resultado, roteador }
}
