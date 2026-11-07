import { http, HttpResponse } from 'msw/http'

// API simulada do login para o `pnpm dev`, enquanto a T-10/T-12 não existem (mesmo contrato de api/autenticacao.ts).
// Senha `oikora123` pede o código; código `123456` ou recuperação `ABCD-1234` entram;
// senha `bloqueada` simula muitas tentativas; qualquer outra senha não confere.

const API = '*/api/v1'
let canalAtual: 'email' | 'whatsapp' = 'email'

const mascararEmail = (email: string): string => {
  const [usuario = '', dominio = 'exemplo.com'] = email.split('@')
  return `${usuario.slice(0, 1)}•••@${dominio}`
}
const mascararCelular = (e164: string): string => `(${e164.slice(3, 5)}) •••••-${e164.slice(-4)}`
const destinoDe = (canal: 'email' | 'whatsapp', identificador: string): string =>
  canal === 'email'
    ? identificador.includes('@') ? mascararEmail(identificador) : 'm•••@exemplo.com'
    : identificador.includes('@') ? '(11) •••••-4321' : mascararCelular(identificador)

let ultimoIdentificador = ''

export const handlersIdentidade = [
  http.get('*/sanctum/csrf-cookie', () => new HttpResponse(null, { status: 204 })),

  http.post(`${API}/auth/login`, async ({ request }) => {
    const { identificador, senha } = (await request.json()) as { identificador: string; senha: string }
    if (senha === 'bloqueada') return HttpResponse.json({ codigo: 'muitas_tentativas', request_id: 'simulado' }, { status: 429 })
    if (senha !== 'oikora123') {
      return HttpResponse.json({ message: 'Os dados não conferem.', errors: { identificador: ['auth.failed'] } }, { status: 422 })
    }
    ultimoIdentificador = identificador
    canalAtual = identificador.includes('@') ? 'email' : 'whatsapp'
    return HttpResponse.json(
      { codigo: 'dois_fatores_necessario', canal: canalAtual, destino: destinoDe(canalAtual, identificador), request_id: 'simulado' },
      { status: 403 },
    )
  }),

  http.post(`${API}/auth/2fa/enviar`, async ({ request }) => {
    const { canal } = (await request.json()) as { canal: 'email' | 'whatsapp' }
    canalAtual = canal
    return HttpResponse.json({ canal, destino: destinoDe(canal, ultimoIdentificador) })
  }),

  http.post(`${API}/auth/2fa/confirmar`, async ({ request }) => {
    const corpo = (await request.json()) as { codigo?: string; codigo_recuperacao?: string }
    const valido = corpo.codigo === '123456' || corpo.codigo_recuperacao?.replace(/[^A-Z0-9]/gi, '').toUpperCase() === 'ABCD1234'
    return valido
      ? new HttpResponse(null, { status: 204 })
      : HttpResponse.json({ message: 'Código inválido.', errors: { codigo: ['invalid'] } }, { status: 422 })
  }),

  http.post(`${API}/auth/senha/esqueci`, () => new HttpResponse(null, { status: 204 })),
]
