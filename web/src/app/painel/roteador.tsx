import { createRootRoute, createRoute, createRouter, Outlet, useNavigate, type RouterHistory } from '@tanstack/react-router'
import type { ReactNode } from 'react'
import { PaginaLogin } from '../../features/Identidade'
import { InicioPainel } from './InicioPainel'

// Rotas da entrada `painel` (OIK-ADR-0006 §3), servidas sob /painel.

function TelaLogin(): ReactNode {
  const navegar = useNavigate()
  return <PaginaLogin aoEntrar={() => void navegar({ to: '/' })} />
}

const raiz = createRootRoute({ component: Outlet })
const rotaInicio = createRoute({ getParentRoute: () => raiz, path: '/', component: InicioPainel })
const rotaLogin = createRoute({ getParentRoute: () => raiz, path: 'login', component: TelaLogin })
const arvore = raiz.addChildren([rotaInicio, rotaLogin])

export function criarRoteador(historia?: RouterHistory) {
  return createRouter({ routeTree: arvore, basepath: '/painel', history: historia })
}

declare module '@tanstack/react-router' {
  interface Register {
    router: ReturnType<typeof criarRoteador>
  }
}
