import { ErroHttp, requisitar } from '../../../shared/http'
import type { DesafioDoisFatores } from './autenticacao'

// Contrato da elevação do painel (T-16): POST /painel/elevacao com `senha` e, quando a conta tem 2FA,
// `codigo` ou `codigo_recuperacao`.
// 204 elevado; 403 `dois_fatores_necessario` (canal, destino) ou `dois_fatores_cadastro_necessario`;
// 422 senha ou código; 429 `muitas_tentativas`.

export type ResultadoElevacao =
  | { tipo: 'elevado' }
  | { tipo: 'dois_fatores'; desafio: DesafioDoisFatores }
  | { tipo: 'cadastro_necessario' }
  | { tipo: 'recusado'; motivo: 'senha' | 'codigo' | 'muitas_tentativas' | 'falha' }

export async function elevar(senha: string, codigo?: string): Promise<ResultadoElevacao> {
  try {
    await requisitar<unknown>('POST', '/painel/elevacao', codigo ? { senha, codigo } : { senha })
    return { tipo: 'elevado' }
  } catch (erro) {
    if (!(erro instanceof ErroHttp)) return { tipo: 'recusado', motivo: 'falha' }
    if (erro.status === 429) return { tipo: 'recusado', motivo: 'muitas_tentativas' }
    if (erro.status === 422) return { tipo: 'recusado', motivo: erro.corpo.errors?.codigo ? 'codigo' : 'senha' }
    if (erro.codigo === 'dois_fatores_cadastro_necessario') return { tipo: 'cadastro_necessario' }
    const { canal, destino } = erro.corpo
    if (erro.codigo === 'dois_fatores_necessario' && (canal === 'email' || canal === 'whatsapp' || canal === 'totp')) {
      return { tipo: 'dois_fatores', desafio: { canal, destino: typeof destino === 'string' ? destino : '' } }
    }
    return { tipo: 'recusado', motivo: 'falha' }
  }
}
