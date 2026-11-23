import {
  createRootRoute,
  createRoute,
  createRouter,
  lazyRouteComponent,
  Outlet,
  useNavigate,
  useParams,
  type RouterHistory,
} from '@tanstack/react-router'
import type { ReactNode } from 'react'
import { CadastroDoisFatores, PaginaConvite, PaginaLogin } from '../../features/Identidade'
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

function TelaConvite(): ReactNode {
  const { token } = useParams({ from: '/convites/$token' })
  const voltar = encodeURIComponent(`/painel/convites/${token}`)
  return <PaginaConvite token={token} linkLogin={`/painel/login?voltar=${voltar}`} />
}

function TelaCadastroDoisFatores(): ReactNode {
  const navegar = useNavigate()
  return <CadastroDoisFatores aoConcluir={() => void navegar({ to: '/' })} />
}

const raiz = createRootRoute({ component: Outlet })
const rotaInicio = createRoute({ getParentRoute: () => raiz, path: '/', component: InicioPainel })
const rotaLogin = createRoute({ getParentRoute: () => raiz, path: 'login', component: TelaLogin })
const rotaConvite = createRoute({ getParentRoute: () => raiz, path: 'convites/$token', component: TelaConvite })
const rotaDoisFatores = createRoute({ getParentRoute: () => raiz, path: 'dois-fatores', component: TelaCadastroDoisFatores })
// Telas de gestão carregadas sob demanda (FE-PERF-002), pelo index de cada feature.
const rotaAcessos = createRoute({
  getParentRoute: () => raiz,
  path: 'acessos',
  component: lazyRouteComponent(() => import('../../features/Autorizacao'), 'TelaAcessos'),
})
const rotaPapeis = createRoute({
  getParentRoute: () => raiz,
  path: 'papeis',
  component: lazyRouteComponent(() => import('../../features/Autorizacao'), 'TelaPapeis'),
})
const rotaAuditoria = createRoute({
  getParentRoute: () => raiz,
  path: 'auditoria',
  component: lazyRouteComponent(() => import('../../features/Auditoria'), 'TelaAuditoria'),
})
const arvore = raiz.addChildren([rotaInicio, rotaLogin, rotaConvite, rotaDoisFatores, rotaAcessos, rotaPapeis, rotaAuditoria])

export function criarRoteador(historia?: RouterHistory) {
  return createRouter({ routeTree: arvore, basepath: '/painel', history: historia })
}

declare module '@tanstack/react-router' {
  interface Register {
    router: ReturnType<typeof criarRoteador>
  }
}
