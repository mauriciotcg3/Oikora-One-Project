// Public API da feature Identidade (FE-MOD-003).
export { identidadePtBR } from './i18n/pt-BR'
export { useMe } from './model/useConta'
export { CadastroDoisFatores } from './ui/CadastroDoisFatores'
export { DialogoElevacao } from './ui/DialogoElevacao'
export { PaginaConvite } from './ui/PaginaConvite'
export { PaginaLogin } from './ui/PaginaLogin'
export { SeletorOrganizacao } from './ui/SeletorOrganizacao'

/** Handlers da API simulada (MSW); carregados só no `pnpm dev`. */
export const carregarApiSimuladaIdentidade = async (): Promise<typeof import('./mocks/handlers').handlersIdentidade> =>
  (await import('./mocks/handlers')).handlersIdentidade
