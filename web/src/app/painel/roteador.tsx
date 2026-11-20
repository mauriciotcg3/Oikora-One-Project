import { createRootRoute, createRoute, createRouter, Outlet, useNavigate, type RouterHistory } from '@tanstack/react-router'
import type { ReactNode } from 'react'
import { PaginaLogin } from '../../features/Identidade'
import { InicioPainel } from './InicioPainel'

// Rotas da entrada `painel` (OIK-ADR-0006 §3), servidas sob /painel.

/** Volta só para dentro do painel: o parâmetro vem da URL e não pode levar a outro site. */
export function destinoDepoisDoLogin(busca: string): string | null {
  const voltar = new URLSearchParams(busca).get('voltar')
  return voltar !== null && voltar.startsWith('/painel/') && !voltar.startsWith('//') ? voltar : null
}

function TelaLogin(): ReactNode {
  const navegar = useNavigate()
  const aoEntrar = (): void => {
    const destino = destinoDepoisDoLogin(window.location.search)
    if (destino) window.location.assign(destino)
    else void navegar({ to: '/' })
  }
  return <PaginaLogin aoEntrar={aoEntrar} />
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
