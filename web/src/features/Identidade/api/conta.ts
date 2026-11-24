import { ErroHttp, renovarCsrf, requisitar } from '../../../shared/http'

// Conta, convite e organização ativa (plan.md §7; T-12, T-16, T-22):
// - GET /me → conta, organização ativa, organizações, `dois_fatores` { canal, obrigatorio }.
// - PUT /me/organizacao-ativa { organizacao } → 204; 403 `organizacao_sem_acesso`.
// - POST /conta/confirmar-senha { senha } → 204; 422 senha.
// - POST /conta/2fa { canal } → { canal, codigos_recuperacao } (10 códigos, mostrados uma vez).
// - GET /convites/{token} → dados mínimos; 4xx `convite_invalido`.
// - POST /convites/{token}/aceitar → sem sessão { senha, senha_confirmation }; 409 `entre_para_aceitar`.

export interface OrganizacaoDaConta {
  id: string
  nome: string
}

export interface Me {
  organizacao_ativa: OrganizacaoDaConta | null
  organizacoes: OrganizacaoDaConta[]
  dois_fatores: { canal: string | null; obrigatorio: boolean }
}

export async function lerMe(): Promise<Me | null> {
  try {
    const resposta = await requisitar<{ data: Me }>('GET', '/me')
    return resposta.data
  } catch (erro) {
    if (erro instanceof ErroHttp && erro.status === 401) return null
    throw erro
  }
}

export async function escolherOrganizacao(organizacao: string): Promise<void> {
  await requisitar<unknown>('PUT', '/me/organizacao-ativa', { organizacao })
}

export type CanalCadastro = 'email' | 'whatsapp'

/** `false` quando a senha não confere (422). */
export async function confirmarSenha(senha: string): Promise<boolean> {
  try {
    await requisitar<unknown>('POST', '/conta/confirmar-senha', { senha })
    return true
  } catch (erro) {
    if (erro instanceof ErroHttp && erro.status === 422) return false
    throw erro
  }
}

export async function cadastrarDoisFatores(canal: CanalCadastro): Promise<string[]> {
  const resposta = await requisitar<{ canal: string; codigos_recuperacao: string[] }>('POST', '/conta/2fa', { canal })
  return resposta.codigos_recuperacao
}

export interface DadosConvite {
  organizacao: { nome: string }
  pessoa: { nome: string | null }
  papel: { nome: string | null }
  expira_em: string
}

export async function lerConvite(token: string): Promise<DadosConvite | null> {
  try {
    return await requisitar<DadosConvite>('GET', `/convites/${encodeURIComponent(token)}`)
  } catch (erro) {
    if (erro instanceof ErroHttp && erro.status >= 400 && erro.status < 500) return null
    throw erro
  }
}

export type ResultadoAceite = 'aceito' | 'entre_para_aceitar' | 'invalido'

export async function aceitarConvite(token: string, senha?: string): Promise<ResultadoAceite> {
  await renovarCsrf()
  try {
    await requisitar<unknown>(
      'POST',
      `/convites/${encodeURIComponent(token)}/aceitar`,
      senha === undefined ? {} : { senha, senha_confirmation: senha },
    )
    return 'aceito'
  } catch (erro) {
    if (erro instanceof ErroHttp && erro.codigo === 'entre_para_aceitar') return 'entre_para_aceitar'
    if (erro instanceof ErroHttp && erro.codigo === 'convite_invalido') return 'invalido'
    throw erro
  }
}
