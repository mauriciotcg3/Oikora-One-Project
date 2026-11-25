import { http, HttpResponse } from 'msw/http'
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import { servidor } from '../../../test/servidor'
import { renderizarComProvedores } from '../../../test/renderizar'
import { autorizacaoPtBR, TelaSolicitacoes } from '../index'

const API = '*/api/v1'
const recursos = { autorizacao: autorizacaoPtBR }
const permissao = { chave: 'pessoas.sensiveis.fe.ver', categoria_sensivel: 'fe', escopo_tipo: 'organizacao', escopo_id: null }
const base = {
  uuid: '11111111-1111-4111-8111-111111111111', estado: 'pendente', versao: 1,
  solicitante: { acesso: '22222222-2222-4222-8222-222222222222' }, duracao_horas: 4,
  justificativa: 'Preciso conferir os registros de conversão.', motivo_decisao: null,
  criada_em: '2026-11-25T12:00:00Z', decidida_em: null, permissoes: [permissao], concessao: null,
}

describe('solicitações temporárias', () => {
  it('cria pedido com categoria sensível explícita e duração limitada a 72 horas', async () => {
    let corpo: unknown
    servidor.use(
      http.get(`${API}/painel/permissoes`, () => HttpResponse.json({ data: [permissao.chave] })),
      http.get(`${API}/painel/solicitacoes-permissao`, () => HttpResponse.json({ data: [] })),
      http.post(`${API}/painel/solicitacoes-permissao`, async ({ request }) => {
        corpo = await request.json()
        return HttpResponse.json({ data: base }, { status: 201 })
      }),
    )
    const usuario = userEvent.setup()
    renderizarComProvedores(<TelaSolicitacoes />, recursos)

    await usuario.selectOptions(await screen.findByLabelText('Permissão'), permissao.chave)
    await usuario.clear(screen.getByLabelText('Duração em horas'))
    await usuario.type(screen.getByLabelText('Duração em horas'), '4')
    await usuario.type(screen.getByLabelText('Justificativa'), base.justificativa)
    await usuario.click(screen.getByRole('button', { name: 'Solicitar acesso temporário' }))

    expect(await screen.findByText('Solicitação enviada.')).toBeInTheDocument()
    expect(corpo).toEqual({ permissoes: [permissao], duracao_horas: 4, justificativa: base.justificativa })
  })

  it('recarrega a solicitação em conflito sem repetir automaticamente a decisão', async () => {
    let listagens = 0
    let aprovacoes = 0
    servidor.use(
      http.get(`${API}/painel/permissoes`, () => HttpResponse.json({ data: [permissao.chave] })),
      http.get(`${API}/painel/solicitacoes-permissao`, ({ request }) => {
        listagens++
        return HttpResponse.json({ data: [{ ...base, versao: listagens > 2 ? 2 : 1 }] })
      }),
      http.post(`${API}/painel/solicitacoes-permissao/:id/aprovar`, () => {
        aprovacoes++
        return HttpResponse.json({ codigo: 'solicitacao_permissao_alterada' }, { status: 409 })
      }),
    )
    const usuario = userEvent.setup()
    renderizarComProvedores(<TelaSolicitacoes podeAprovar />, recursos)

    const pendentes = await screen.findByRole('region', { name: 'Aguardando decisão' })
    await usuario.click(within(pendentes).getByRole('button', { name: 'Aprovar' }))

    expect(await screen.findByText(/alterada por outra pessoa/i)).toBeInTheDocument()
    await waitFor(() => expect(listagens).toBeGreaterThanOrEqual(3))
    expect(aprovacoes).toBe(1)
  })
})
