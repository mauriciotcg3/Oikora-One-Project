import { createRootRoute, createRoute, createRouter, Outlet, useNavigate, type RouterHistory } from '@tanstack/react-router'
import type { ReactNode } from 'react'
import { PaginaLogin } from '../../features/Identidade'
import { InicioPainel } from './InicioPainel'
import { PaginaAuditoria } from './PaginaAuditoria'
import { Shell } from './Shell'

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
// Telas autenticadas ficam dentro do shell; o login fica fora dele.
const rotaShell = createRoute({ getParentRoute: () => raiz, id: 'shell', component: Shell })
const rotaInicio = createRoute({ getParentRoute: () => rotaShell, path: '/', component: InicioPainel })
const rotaAuditoria = createRoute({ getParentRoute: () => rotaShell, path: 'auditoria', component: PaginaAuditoria })
const rotaLogin = createRoute({ getParentRoute: () => raiz, path: 'login', component: TelaLogin })
const arvore = raiz.addChildren([rotaShell.addChildren([rotaInicio, rotaAuditoria]), rotaLogin])

export function criarRoteador(historia?: RouterHistory) {
  return createRouter({ routeTree: arvore, basepath: '/painel', history: historia })
}

declare module '@tanstack/react-router' {
  interface Register {
    router: ReturnType<typeof criarRoteador>
  }
}
