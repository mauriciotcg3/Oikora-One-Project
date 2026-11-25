import { ErroHttp, requisitar } from '../../../shared/http'

export type EstadoSolicitacao = 'pendente' | 'aprovada' | 'recusada' | 'cancelada' | 'revogada' | 'expirada'
export type EscopoSolicitado = 'organizacao' | 'regional' | 'igreja'

export interface PermissaoSolicitada {
  chave: string
  categoria_sensivel: string | null
  escopo_tipo: EscopoSolicitado
  escopo_id: string | null
}

export interface SolicitacaoPermissao {
  uuid: string
  estado: EstadoSolicitacao
  versao: number
  solicitante: { acesso: string }
  duracao_horas: number
  justificativa: string
  motivo_decisao: string | null
  criada_em: string
  decidida_em: string | null
  permissoes: PermissaoSolicitada[]
  concessao: null | { uuid: string; inicia_em: string; expira_em: string; revogada_em: string | null }
}

export interface PedidoSolicitacao {
  permissoes: PermissaoSolicitada[]
  duracao_horas: number
  justificativa: string
}

export interface RecusaSolicitacao {
  status: number
  codigo: string
  campos?: Record<string, string>
}

export async function listarSolicitacoes(visao: 'proprias' | 'aprovaveis'): Promise<SolicitacaoPermissao[]> {
  return (await requisitar<{ data: SolicitacaoPermissao[] }>('GET', `/painel/solicitacoes-permissao?visao=${visao}`)).data
}

export async function catalogoSolicitavel(): Promise<string[]> {
  return (await requisitar<{ data: string[] }>('GET', '/painel/permissoes')).data
}

function recusa(erro: unknown): RecusaSolicitacao {
  if (!(erro instanceof ErroHttp)) return { status: 0, codigo: 'falha' }
  const campos = erro.status === 422 && erro.corpo.errors
    ? Object.fromEntries(Object.entries(erro.corpo.errors).map(([campo, mensagens]) => [campo, mensagens[0] ?? 'invalido']))
    : undefined
  return { status: erro.status, codigo: erro.codigo ?? 'falha', campos }
}

async function mutacao<T>(executar: () => Promise<T>): Promise<{ data?: T; erro?: RecusaSolicitacao }> {
  try { return { data: await executar() } } catch (erro) { return { erro: recusa(erro) } }
}

export const criarSolicitacao = (pedido: PedidoSolicitacao) => mutacao(() =>
  requisitar<{ data: SolicitacaoPermissao }>('POST', '/painel/solicitacoes-permissao', pedido))

export const cancelarSolicitacao = (uuid: string) => mutacao(() =>
  requisitar<{ data: SolicitacaoPermissao }>('POST', `/painel/solicitacoes-permissao/${uuid}/cancelar`))

export const aprovarSolicitacao = (uuid: string, versao: number) => mutacao(() =>
  requisitar<{ data: SolicitacaoPermissao }>('POST', `/painel/solicitacoes-permissao/${uuid}/aprovar`, { versao }))

export const recusarSolicitacao = (uuid: string, versao: number, justificativa: string) => mutacao(() =>
  requisitar<{ data: SolicitacaoPermissao }>('POST', `/painel/solicitacoes-permissao/${uuid}/recusar`, { versao, justificativa }))

export const revogarConcessao = (uuid: string, motivo: string) => mutacao(() =>
  requisitar<unknown>('POST', `/painel/concessoes-permissao/${uuid}/revogar`, { motivo }))
