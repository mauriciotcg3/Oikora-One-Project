// Public API da feature Identidade (FE-MOD-003).
export { identidadePtBR } from './i18n/pt-BR'
export { PaginaLogin } from './ui/PaginaLogin'

/** Handlers da API simulada (MSW); carregados só no `pnpm dev`. */
export const carregarApiSimuladaIdentidade = async (): Promise<typeof import('./mocks/handlers').handlersIdentidade> =>
  (await import('./mocks/handlers')).handlersIdentidade
