// Cliente HTTP único da API Laravel (FE-COMM-001, OIK-ADR-0006 §10). Todo pedido sai por
// `enviarComSessao`: CSRF, organização esperada, `X-Cliente`, `traceparent` e o tratamento de
// 419, 401, 403 `elevacao_necessaria` e 409 `organizacao_trocada`. Quem decide o que mostrar
// (diálogo, login, recarga) é a entrada em `app/`, por `configurarSessao`.

import { criarClienteApi, type ClienteApi } from '@oikora/api-contrato'
import { novoTrace } from '../observabilidade'

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
  readonly traceId: string | undefined

  constructor(status: number, corpo: CorpoErroApi, traceId?: string) {
    super(`HTTP ${status}${corpo.codigo ? ` ${corpo.codigo}` : ''}`)
    this.name = 'ErroHttp'
    this.status = status
    this.corpo = corpo
    this.traceId = traceId
  }

  get codigo(): string | undefined {
    return this.corpo.codigo
  }
}

/** Comportamentos de sessão que dependem da entrada (painel ou membro). */
export interface ConfiguracaoSessao {
  /** Identificador público da organização ativa, vindo do `/me`. */
  organizacaoEsperada: () => string | undefined
  /** 401: limpar o cache e ir ao login com a URL de volta. */
  aoNaoAutenticado: () => void
  /** 403 `elevacao_necessaria`: abre o diálogo; `true` quando elevou e o pedido pode ser repetido. */
  aoElevacaoNecessaria: () => Promise<boolean>
  /** 409 `organizacao_trocada`: avisar as outras abas e recarregar. */
  aoOrganizacaoTrocada: () => void
}

const semConfiguracao: ConfiguracaoSessao = {
  organizacaoEsperada: () => undefined,
  aoNaoAutenticado: () => undefined,
  aoElevacaoNecessaria: async () => false,
  aoOrganizacaoTrocada: () => undefined,
}

let sessao: ConfiguracaoSessao = semConfiguracao

/** Liga os comportamentos de sessão da entrada; devolve a função que os desliga. */
export function configurarSessao(configuracao: Partial<ConfiguracaoSessao>): () => void {
  sessao = { ...semConfiguracao, ...configuracao }
  return () => {
    sessao = semConfiguracao
  }
}

function urlAbsoluta(caminho: string): string {
  return new URL(caminho, window.location.origin).toString()
}

function lerCookie(nome: string): string | undefined {
  const par = document.cookie.split('; ').find((item) => item.startsWith(`${nome}=`))
  return par ? decodeURIComponent(par.slice(nome.length + 1)) : undefined
}

async function codigoDoErro(resposta: Response): Promise<string | undefined> {
  try {
    const corpo = (await resposta.clone().json()) as CorpoErroApi
    return typeof corpo.codigo === 'string' ? corpo.codigo : undefined
  } catch {
    return undefined
  }
}

/** Pede ao Sanctum um cookie XSRF novo (OIK-ADR-0003 §9). */
export async function renovarCsrf(): Promise<void> {
  await fetch(urlAbsoluta(ROTA_CSRF), { credentials: 'include', headers: { Accept: 'application/json' } })
}

const traceDoPedido = new WeakMap<Response, string>()

/** `trace_id` que saiu no `traceparent` do pedido desta resposta. */
export function traceDaResposta(resposta: Response): string | undefined {
  return traceDoPedido.get(resposta)
}

/**
 * `fetch` do painel: monta os cabeçalhos a cada tentativa, repete uma vez depois do 419 e depois de
 * elevar, e devolve a resposta final (inclusive de erro) para quem chamou.
 */
export async function enviarComSessao(entrada: Request): Promise<Response> {
  const original = entrada.clone()

  const enviar = async (): Promise<Response> => {
    const pedido = original.clone()
    const trace = novoTrace()
    const cabecalhos = new Headers(pedido.headers)
    cabecalhos.set('Accept', 'application/json')
    cabecalhos.set('X-Requested-With', 'XMLHttpRequest')
    cabecalhos.set('X-Cliente', `painel@${VERSAO_BUILD}`)
    cabecalhos.set('traceparent', trace.traceparent)
    const xsrf = lerCookie('XSRF-TOKEN')
    if (xsrf) cabecalhos.set('X-XSRF-TOKEN', xsrf)
    const organizacao = sessao.organizacaoEsperada()
    if (organizacao) cabecalhos.set('X-Organizacao-Esperada', organizacao)

    const resposta = await fetch(pedido, { headers: cabecalhos, credentials: 'include' })
    traceDoPedido.set(resposta, trace.traceId)
    return resposta
  }

  let resposta = await enviar()

  if (resposta.status === 419) {
    await renovarCsrf()
    resposta = await enviar()
  }

  if (resposta.status === 401) {
    sessao.aoNaoAutenticado()
    return resposta
  }

  if (resposta.status === 403 && (await codigoDoErro(resposta)) === 'elevacao_necessaria') {
    if (await sessao.aoElevacaoNecessaria()) {
      resposta = await enviar()
    }
    return resposta
  }

  if (resposta.status === 409 && (await codigoDoErro(resposta)) === 'organizacao_trocada') {
    sessao.aoOrganizacaoTrocada()
  }

  return resposta
}

async function lerCorpoErro(resposta: Response): Promise<CorpoErroApi> {
  try {
    return (await resposta.json()) as CorpoErroApi
  } catch {
    return {}
  }
}

export async function requisitar<T>(metodo: MetodoHttp, caminho: string, corpo?: unknown): Promise<T> {
  const resposta = await enviarComSessao(
    new Request(urlAbsoluta(BASE_API + caminho), {
      method: metodo,
      headers: corpo === undefined ? undefined : { 'Content-Type': 'application/json' },
      body: corpo === undefined ? undefined : JSON.stringify(corpo),
    }),
  )
  if (!resposta.ok) throw new ErroHttp(resposta.status, await lerCorpoErro(resposta), traceDaResposta(resposta))
  if (resposta.status === 204) return undefined as T
  return (await resposta.json()) as T
}

/** Cliente tipado do contrato (`packages/api-contrato`), com o mesmo tratamento de sessão. */
export function criarClienteTipado(): ClienteApi {
  return criarClienteApi({ baseUrl: urlAbsoluta(BASE_API), fetch: enviarComSessao })
}
