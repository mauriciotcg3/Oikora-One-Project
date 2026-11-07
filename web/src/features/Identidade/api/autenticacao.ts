import { ErroHttp, renovarCsrf, requisitar } from '../../../shared/http'

// Contrato do login (plan.md §7), já implementado na API. O MSW simula o mesmo contrato no `pnpm dev`:
// - POST /auth/login → 204 entrou; 403 `dois_fatores_necessario` com canal e destino mascarado; 403 `conta_bloqueada`;
//   422 credencial inválida (mensagem nunca diz se a conta existe, OIK-ADR-0003 §4); 429 `muitas_tentativas`.
// - POST /auth/2fa/confirmar → 204; 401 sem desafio aberto; 422 código incorreto; 429 código invalidado por erros.
// - POST /auth/2fa/enviar → 200 com canal e destino; 401 sem desafio aberto; 429 limite de reenvios.
// - POST /auth/senha/esqueci → 204 sempre que o pedido é aceito.

export type CanalDoisFatores = 'email' | 'whatsapp' | 'totp'

export interface DesafioDoisFatores {
  canal: CanalDoisFatores
  destino: string
}

export type ResultadoLogin = { tipo: 'autenticado' } | { tipo: 'dois_fatores'; desafio: DesafioDoisFatores }

export type MotivoFalha = 'credenciais' | 'muitas_tentativas' | 'codigo_incorreto' | 'falha'

export class FalhaAutenticacao extends Error {
  readonly motivo: MotivoFalha

  constructor(motivo: MotivoFalha) {
    super(motivo)
    this.name = 'FalhaAutenticacao'
    this.motivo = motivo
  }
}

export interface Credenciais {
  identificador: string
  senha: string
}

export type ConfirmacaoDoisFatores = { codigo: string } | { codigo_recuperacao: string }

function motivoDe(erro: unknown, motivo422: MotivoFalha): FalhaAutenticacao {
  if (erro instanceof ErroHttp) {
    if (erro.status === 429) return new FalhaAutenticacao('muitas_tentativas')
    if (erro.status === 422) return new FalhaAutenticacao(motivo422)
  }
  return new FalhaAutenticacao('falha')
}

function desafioDe(erro: ErroHttp): DesafioDoisFatores | undefined {
  const { canal, destino } = erro.corpo
  const canalValido = canal === 'email' || canal === 'whatsapp' || canal === 'totp'
  return erro.codigo === 'dois_fatores_necessario' && canalValido
    ? { canal, destino: typeof destino === 'string' ? destino : '' }
    : undefined
}

export async function entrar(credenciais: Credenciais): Promise<ResultadoLogin> {
  await renovarCsrf()
  try {
    await requisitar<unknown>('POST', '/auth/login', credenciais)
    return { tipo: 'autenticado' }
  } catch (erro) {
    const desafio = erro instanceof ErroHttp && erro.status === 403 ? desafioDe(erro) : undefined
    if (desafio) return { tipo: 'dois_fatores', desafio }
    throw motivoDe(erro, 'credenciais')
  }
}

export async function confirmarDoisFatores(confirmacao: ConfirmacaoDoisFatores): Promise<void> {
  try {
    await requisitar<unknown>('POST', '/auth/2fa/confirmar', confirmacao)
  } catch (erro) {
    throw motivoDe(erro, 'codigo_incorreto')
  }
}

export async function enviarCodigo(canal: CanalDoisFatores): Promise<DesafioDoisFatores> {
  try {
    return await requisitar<DesafioDoisFatores>('POST', '/auth/2fa/enviar', { canal })
  } catch (erro) {
    throw motivoDe(erro, 'falha')
  }
}

export async function pedirRecuperacaoSenha(identificador: string): Promise<void> {
  await renovarCsrf()
  try {
    await requisitar<unknown>('POST', '/auth/senha/esqueci', { identificador })
  } catch (erro) {
    throw motivoDe(erro, 'falha')
  }
}
