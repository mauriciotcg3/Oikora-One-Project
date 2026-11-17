// OIK-SPEC-0001 T-37, casos 1 a 4: cabeçalhos de todo pedido, 419, 401, 403 `elevacao_necessaria` e
// 409 `organizacao_trocada` (OIK-ADR-0006 §10, §14).
import { http, HttpResponse } from 'msw/http'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { servidor } from '../../../test/servidor'
import { configurarSessao, criarClienteTipado, ErroHttp, requisitar } from '../index'

const API = '*/api/v1'
let desligar: () => void = () => undefined

beforeEach(() => {
  document.cookie = 'XSRF-TOKEN=token%3Dcsrf; path=/'
})

afterEach(() => {
  desligar()
  document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/'
})

describe('cliente HTTP do painel', () => {
  it('envia credenciais, CSRF, organização esperada, cliente e traceparent em todo pedido', async () => {
    const pedidos: Request[] = []
    servidor.use(
      http.get(`${API}/me`, ({ request }) => {
        pedidos.push(request)
        return HttpResponse.json({ ok: true })
      }),
    )
    desligar = configurarSessao({ organizacaoEsperada: () => 'org-uuid-1' })
    const espiao = vi.spyOn(globalThis, 'fetch')

    await requisitar('GET', '/me')
    await criarClienteTipado().GET('/me')

    expect(espiao.mock.calls.map(([, opcoes]) => opcoes?.credentials)).toEqual(['include', 'include'])
    espiao.mockRestore()
    expect(pedidos).toHaveLength(2)
    for (const pedido of pedidos) {
      expect(pedido.headers.get('X-XSRF-TOKEN')).toBe('token=csrf')
      expect(pedido.headers.get('X-Organizacao-Esperada')).toBe('org-uuid-1')
      expect(pedido.headers.get('X-Cliente')).toMatch(/^painel@/)
      expect(pedido.headers.get('traceparent')).toMatch(/^00-[0-9a-f]{32}-[0-9a-f]{16}-01$/)
    }
    expect(pedidos[0]?.headers.get('traceparent')).not.toBe(pedidos[1]?.headers.get('traceparent'))
  })

  it('renova o CSRF no 419 e repete o pedido uma vez', async () => {
    let tentativas = 0
    let renovacoes = 0
    servidor.use(
      http.get('*/sanctum/csrf-cookie', () => {
        renovacoes++
        return new HttpResponse(null, { status: 204 })
      }),
      http.post(`${API}/pessoas`, () => {
        tentativas++
        return tentativas === 1 ? new HttpResponse(null, { status: 419 }) : HttpResponse.json({ data: { uuid: 'p1' } }, { status: 201 })
      }),
    )

    await expect(requisitar('POST', '/pessoas', { nome: 'Maria' })).resolves.toEqual({ data: { uuid: 'p1' } })
    expect([renovacoes, tentativas]).toEqual([1, 2])
  })

  it('no 401 avisa a entrada para limpar o cache e ir ao login', async () => {
    const aoNaoAutenticado = vi.fn()
    servidor.use(http.get(`${API}/me`, () => HttpResponse.json({ message: 'Unauthenticated.' }, { status: 401 })))
    desligar = configurarSessao({ aoNaoAutenticado })

    await expect(requisitar('GET', '/me')).rejects.toBeInstanceOf(ErroHttp)
    expect(aoNaoAutenticado).toHaveBeenCalledOnce()
  })

  it('no 403 elevacao_necessaria espera o diálogo e repete o mesmo pedido, com o mesmo corpo', async () => {
    const corpos: unknown[] = []
    const aoElevacaoNecessaria = vi.fn(async () => true)
    servidor.use(
      http.put(`${API}/painel/papeis/p1`, async ({ request }) => {
        corpos.push(await request.json())
        return corpos.length === 1
          ? HttpResponse.json({ codigo: 'elevacao_necessaria' }, { status: 403 })
          : HttpResponse.json({ data: { nome: 'Tesouraria' } })
      }),
    )
    desligar = configurarSessao({ aoElevacaoNecessaria })

    await expect(requisitar('PUT', '/painel/papeis/p1', { nome: 'Tesouraria' })).resolves.toEqual({ data: { nome: 'Tesouraria' } })
    expect(aoElevacaoNecessaria).toHaveBeenCalledOnce()
    expect(corpos).toEqual([{ nome: 'Tesouraria' }, { nome: 'Tesouraria' }])
  })

  it('não repete quando a pessoa cancela a elevação', async () => {
    let tentativas = 0
    servidor.use(
      http.get(`${API}/painel/papeis`, () => {
        tentativas++
        return HttpResponse.json({ codigo: 'elevacao_necessaria' }, { status: 403 })
      }),
    )
    desligar = configurarSessao({ aoElevacaoNecessaria: async () => false })

    const erro = await requisitar('GET', '/painel/papeis').catch((e: unknown) => e)
    expect(erro).toBeInstanceOf(ErroHttp)
    expect((erro as ErroHttp).codigo).toBe('elevacao_necessaria')
    expect(tentativas).toBe(1)
  })

  it('no 409 organizacao_trocada avisa a entrada para recarregar e publicar às outras abas', async () => {
    const aoOrganizacaoTrocada = vi.fn()
    servidor.use(http.get(`${API}/painel/papeis`, () => HttpResponse.json({ codigo: 'organizacao_trocada' }, { status: 409 })))
    desligar = configurarSessao({ aoOrganizacaoTrocada })

    await expect(requisitar('GET', '/painel/papeis')).rejects.toBeInstanceOf(ErroHttp)
    expect(aoOrganizacaoTrocada).toHaveBeenCalledOnce()
  })

  it('leva o trace_id do pedido no erro', async () => {
    let traceparent = ''
    servidor.use(
      http.get(`${API}/painel/papeis`, ({ request }) => {
        traceparent = request.headers.get('traceparent') ?? ''
        return HttpResponse.json({ codigo: 'authz.denied' }, { status: 403 })
      }),
    )

    const erro = (await requisitar('GET', '/painel/papeis').catch((e: unknown) => e)) as ErroHttp
    expect(traceparent.split('-')[1]).toBe(erro.traceId)
  })
})
