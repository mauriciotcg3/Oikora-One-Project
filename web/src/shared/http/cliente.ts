// Cliente HTTP único da API Laravel (FE-COMM-001, OIK-ADR-0006 §10).
// Fatia do login: credentials, CSRF, X-Cliente e repetição no 419. Os tratamentos de
// 401, 403 `elevacao_necessaria`, 409 `organizacao_trocada` e o `traceparent` entram com a T-37.

const BASE_API = '/api/v1'
const ROTA_CSRF = '/sanctum/csrf-cookie'
const VERSAO_BUILD: string = import.meta.env.VITE_VERSAO_BUILD ?? 'dev'

export type MetodoHttp = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'

/** Corpo de erro da API: 422 no formato do Laravel; demais com `codigo` estável (OIK-ADR-0006 §11). */
export interface CorpoErroApi {
  message?: string
  errors?: Record<string, string[]>
  codigo?: string
  request_id?: string
  [campo: string]: unknown
}

export class ErroHttp extends Error {
  readonly status: number
  readonly corpo: CorpoErroApi

  constructor(status: number, corpo: CorpoErroApi) {
    super(`HTTP ${status}${corpo.codigo ? ` ${corpo.codigo}` : ''}`)
    this.name = 'ErroHttp'
    this.status = status
    this.corpo = corpo
  }

  get codigo(): string | undefined {
    return this.corpo.codigo
  }
}

function urlAbsoluta(caminho: string): string {
  return new URL(caminho, window.location.origin).toString()
}

function lerCookie(nome: string): string | undefined {
  const par = document.cookie.split('; ').find((item) => item.startsWith(`${nome}=`))
  return par ? decodeURIComponent(par.slice(nome.length + 1)) : undefined
}

async function lerCorpoErro(resposta: Response): Promise<CorpoErroApi> {
  try {
    return (await resposta.json()) as CorpoErroApi
  } catch {
    return {}
  }
}

/** Pede ao Sanctum um cookie XSRF novo (OIK-ADR-0003 §9). */
export async function renovarCsrf(): Promise<void> {
  await fetch(urlAbsoluta(ROTA_CSRF), { credentials: 'include', headers: { Accept: 'application/json' } })
}

export async function requisitar<T>(metodo: MetodoHttp, caminho: string, corpo?: unknown): Promise<T> {
  const enviar = (): Promise<Response> => {
    const cabecalhos: Record<string, string> = {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-Cliente': `painel@${VERSAO_BUILD}`,
    }
    const xsrf = lerCookie('XSRF-TOKEN')
    if (xsrf) cabecalhos['X-XSRF-TOKEN'] = xsrf
    if (corpo !== undefined) cabecalhos['Content-Type'] = 'application/json'
    return fetch(urlAbsoluta(BASE_API + caminho), {
      method: metodo,
      credentials: 'include',
      headers: cabecalhos,
      body: corpo === undefined ? undefined : JSON.stringify(corpo),
    })
  }

  let resposta = await enviar()
  if (resposta.status === 419) {
    await renovarCsrf()
    resposta = await enviar()
  }
  if (!resposta.ok) throw new ErroHttp(resposta.status, await lerCorpoErro(resposta))
  if (resposta.status === 204) return undefined as T
  return (await resposta.json()) as T
}
