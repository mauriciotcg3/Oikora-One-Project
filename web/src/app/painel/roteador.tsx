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
import { IDIOMA_PADRAO } from '../../shared/i18n'
import { useCapacidades } from './capacidades'
import { i18nPainel } from './i18n'
import { InicioPainel } from './InicioPainel'
import { PaginaAuditoria } from './PaginaAuditoria'
import { PaginaSolicitacoes } from './PaginaSolicitacoes'
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
// Telas autenticadas ficam dentro do shell; o login fica fora dele.
const rotaShell = createRoute({ getParentRoute: () => raiz, id: 'shell', component: Shell })
const rotaInicio = createRoute({ getParentRoute: () => rotaShell, path: '/', component: InicioPainel })
const rotaAuditoria = createRoute({ getParentRoute: () => rotaShell, path: 'auditoria', component: PaginaAuditoria })
const rotaLogin = createRoute({ getParentRoute: () => raiz, path: 'login', component: TelaLogin })
const rotaConvite = createRoute({ getParentRoute: () => raiz, path: 'convites/$token', component: TelaConvite })
const rotaDoisFatores = createRoute({ getParentRoute: () => raiz, path: 'dois-fatores', component: TelaCadastroDoisFatores })
// Telas de gestão carregadas sob demanda (FE-PERF-002), pelo index de cada feature.
const rotaAcessos = createRoute({
  getParentRoute: () => rotaShell,
  path: 'acessos',
  component: lazyRouteComponent(() => import('../../features/Autorizacao'), 'TelaAcessos'),
})
const rotaPapeis = createRoute({
  getParentRoute: () => rotaShell,
  path: 'papeis',
  component: lazyRouteComponent(() => import('../../features/Autorizacao'), 'TelaPapeis'),
})
const rotaSolicitacoes = createRoute({ getParentRoute: () => rotaShell, path: 'solicitacoes', component: PaginaSolicitacoes })
// Telas das features W9 carregadas sob demanda, com os textos do namespace da feature registrados na mesma hora.
const registrarTextos = (namespace: string, textos: object): void => {
  i18nPainel.addResourceBundle(IDIOMA_PADRAO, namespace, textos, true, true)
}
const carregarPessoas = async () => {
  const modulo = await import('../../features/Pessoas')
  registrarTextos('pessoas', modulo.pessoasPtBR)
  return modulo
}
const carregarOrganizacao = async () => {
  const modulo = await import('../../features/Organizacao')
  registrarTextos('organizacao', modulo.organizacaoPtBR)
  return modulo
}
const carregarAvisos = async () => {
  const modulo = await import('../../features/Avisos')
  registrarTextos('avisos', modulo.avisosPtBR)
  return modulo
}
const telaPessoas = (nome: 'TelaPessoas' | 'TelaCadastroPessoa' | 'TelaBuscaPessoas' | 'TelaFichaPessoaRoteada' | 'TelaDatasPessoas') => lazyRouteComponent(carregarPessoas, nome)
/** Operações recebem do shell se a pessoa pode decidir transferências; a API continua decidindo cada pedido. */
const telaOperacoesPessoas = lazyRouteComponent(async () => {
  const { TelaOperacoesPessoas } = await carregarPessoas()
  return {
    default: function PaginaOperacoesPessoas(): ReactNode {
      const { tem } = useCapacidades()
      return <TelaOperacoesPessoas podeDecidir={tem('transferencias.aprovar')} />
    },
  }
})
const rotaPessoas = createRoute({ getParentRoute: () => rotaShell, path: 'pessoas', component: telaPessoas('TelaPessoas') })
const rotaNovaPessoa = createRoute({ getParentRoute: () => rotaShell, path: 'pessoas/nova', component: telaPessoas('TelaCadastroPessoa') })
const rotaBuscaPessoas = createRoute({ getParentRoute: () => rotaShell, path: 'pessoas/busca', component: telaPessoas('TelaBuscaPessoas') })
const rotaFichaPessoa = createRoute({ getParentRoute: () => rotaShell, path: 'pessoas/$uuid', component: telaPessoas('TelaFichaPessoaRoteada') })
const rotaOperacoesPessoas = createRoute({ getParentRoute: () => rotaShell, path: 'pessoas/operacoes', component: telaOperacoesPessoas })
const rotaDatasPessoas = createRoute({ getParentRoute: () => rotaShell, path: 'pessoas/acompanhamentos', component: telaPessoas('TelaDatasPessoas') })
const rotaConfiguracao = createRoute({ getParentRoute: () => rotaShell, path: 'configuracao', component: lazyRouteComponent(carregarOrganizacao, 'TelaConfiguracao') })
const rotaWhatsapp = createRoute({ getParentRoute: () => rotaShell, path: 'whatsapp', component: lazyRouteComponent(carregarAvisos, 'TelaWhatsapp') })
const rotaAvisos = createRoute({ getParentRoute: () => rotaShell, path: 'avisos', component: lazyRouteComponent(carregarAvisos, 'TelaAvisos') })
// Galeria de componentes: só no `pnpm dev`. O `if` constante (`import.meta.env.DEV`) é removido do build,
// então nem a rota nem o chunk da galeria vão para produção.
const rotasDev = import.meta.env.DEV
  ? [createRoute({ getParentRoute: () => raiz, path: 'galeria', component: lazyRouteComponent(() => import('./galeria/Galeria'), 'Galeria') })]
  : []
const arvore = raiz.addChildren([
  rotaShell.addChildren([rotaInicio, rotaAuditoria, rotaAcessos, rotaPapeis, rotaSolicitacoes, rotaPessoas, rotaNovaPessoa, rotaBuscaPessoas, rotaFichaPessoa, rotaOperacoesPessoas, rotaDatasPessoas, rotaConfiguracao, rotaWhatsapp, rotaAvisos]),
  rotaLogin,
  rotaConvite,
  rotaDoisFatores,
  ...rotasDev,
])

export function criarRoteador(historia?: RouterHistory) {
  return createRouter({ routeTree: arvore, basepath: '/painel', history: historia })
}

declare module '@tanstack/react-router' {
  interface Register {
    router: ReturnType<typeof criarRoteador>
  }
}
