import { ErroHttp, requisitar } from '../../../shared/http'

// Contrato de acessos, convites e papéis (plan.md §7; T-15, T-22):
// - GET /painel/acessos → { data: Acesso[], convites: Convite[] }
// - POST /painel/convites { pessoa, papel, escopo_tipo, escopo? } → 201; 422 por campo (`pessoa_sem_contato`);
//   403 `escalada_bloqueada`; 422 `pessoa_ja_tem_acesso` | `convite_pendente`.
// - DELETE /painel/acessos/{id} → 204; 422 `ultimo_administrador`.
// - GET /painel/busca?q= → pessoas da denominação (projeção restrita).
// - GET /painel/igrejas, GET /painel/papeis, GET /painel/permissoes; POST|PUT /painel/papeis.

export interface PessoaResumo {
  uuid: string | null
  nome: string | null
}

export interface Acesso {
  uuid: string
  estado: 'ativo' | 'revogado'
  pessoa: PessoaResumo
  papeis: { nome: string; escopo_tipo: string }[]
  meta: { acoes: string[] }
}

export interface ConvitePendente {
  uuid: string
  pessoa: PessoaResumo
  expira_em: string
}

export interface Papel {
  uuid: string
  nome: string
  sistema: string | null
  permissoes: string[]
}

export interface Igreja {
  uuid: string
  nome: string
}

export interface PessoaAchada {
  uuid: string
  nome: string
  igreja: { uuid: string; nome: string } | null
}

export type EscopoTipo = 'organizacao' | 'igreja'

export interface PedidoConvite {
  pessoa: string
  papel: string
  escopo_tipo: EscopoTipo
  escopo?: string
}

/** Recusa com código estável, ou erros por campo (422). */
export type Recusa = { codigo: string; campos?: Record<string, string> }

export async function listarAcessos(): Promise<{ acessos: Acesso[]; convites: ConvitePendente[] }> {
  const resposta = await requisitar<{ data: Acesso[]; convites: ConvitePendente[] }>('GET', '/painel/acessos')
  return { acessos: resposta.data, convites: resposta.convites }
}

export async function buscarPessoas(termo: string): Promise<PessoaAchada[]> {
  return (await requisitar<{ data: PessoaAchada[] }>('GET', `/painel/busca?q=${encodeURIComponent(termo)}`)).data
}

export async function listarIgrejas(): Promise<Igreja[]> {
  return (await requisitar<{ data: Igreja[] }>('GET', '/painel/igrejas')).data
}

export async function listarPapeis(): Promise<Papel[]> {
  return (await requisitar<{ data: Papel[] }>('GET', '/painel/papeis')).data
}

export async function listarPermissoes(): Promise<string[]> {
  return (await requisitar<{ data: Array<{ chave: string } | string> }>('GET', '/painel/permissoes')).data
    .map((item) => typeof item === 'string' ? item : item.chave)
}

function recusaDe(erro: unknown): Recusa {
  if (!(erro instanceof ErroHttp)) return { codigo: 'falha' }
  if (erro.status === 422 && erro.corpo.errors) {
    const campos = Object.fromEntries(Object.entries(erro.corpo.errors).map(([campo, msgs]) => [campo, msgs[0] ?? 'invalido']))
    return { codigo: erro.codigo ?? 'campos_invalidos', campos }
  }
  return { codigo: erro.codigo ?? 'falha' }
}

/** `null` quando deu certo. */
export async function convidar(pedido: PedidoConvite): Promise<Recusa | null> {
  try {
    await requisitar<unknown>('POST', '/painel/convites', pedido)
    return null
  } catch (erro) {
    return recusaDe(erro)
  }
}

export async function revogarAcesso(uuid: string): Promise<Recusa | null> {
  try {
    await requisitar<unknown>('DELETE', `/painel/acessos/${uuid}`)
    return null
  } catch (erro) {
    return recusaDe(erro)
  }
}

export async function salvarPapel(papel: { uuid?: string; nome: string; permissoes: string[] }): Promise<Recusa | null> {
  try {
    const corpo = { nome: papel.nome, permissoes: papel.permissoes }
    if (papel.uuid) await requisitar<unknown>('PUT', `/painel/papeis/${papel.uuid}`, corpo)
    else await requisitar<unknown>('POST', '/painel/papeis', corpo)
    return null
  } catch (erro) {
    return recusaDe(erro)
  }
}
