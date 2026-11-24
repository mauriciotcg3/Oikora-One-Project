// OIK-SPEC-0001 T-40, casos 1 a 3: convidar com busca, papel e escopo (422 no campo); revogar com
// confirmação; papéis com o catálogo agrupado e mensagens próprias. API simulada pelo MSW (plan.md §7).
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw/http'
import { describe, expect, it } from 'vitest'
import { renderizarComProvedores } from '../../../test/renderizar'
import { servidor } from '../../../test/servidor'
import { autorizacaoPtBR, TelaAcessos, TelaPapeis } from '../index'

const API = '*/api/v1'
const recursos = { autorizacao: autorizacaoPtBR }

const papeis = [
  { uuid: 'p-admin', nome: 'Administrador', sistema: 'administrador', permissoes: ['papeis.gerir', 'acessos.gerir'] },
  { uuid: 'p-sec', nome: 'Secretaria', sistema: 'secretaria', permissoes: ['pessoas.ver'] },
]
const catalogo = ['organizacao.configurar', 'acessos.gerir', 'papeis.gerir', 'pessoas.ver', 'pessoas.editar', 'pessoas.sensiveis.saude.ver', 'pessoas.sensiveis.fe.ver', 'permissoes.solicitacoes.aprovar']

function apiDeAcessos(estado: { acessos: unknown[] }) {
  return [
    http.get(`${API}/painel/acessos`, () => HttpResponse.json({ data: estado.acessos, convites: [] })),
    http.get(`${API}/painel/papeis`, () => HttpResponse.json({ data: papeis })),
    http.get(`${API}/painel/igrejas`, () => HttpResponse.json({ data: [{ uuid: 'ig-x', nome: 'Igreja X' }] })),
    http.get(`${API}/painel/busca`, () => HttpResponse.json({ data: [{ uuid: 'pe-1', nome: 'Clara', igreja: { uuid: 'ig-x', nome: 'Igreja X' } }] })),
  ]
}

const acessoAtivo = { uuid: 'ac-1', estado: 'ativo', pessoa: { uuid: 'pe-9', nome: 'Marta' }, papeis: [{ nome: 'Secretaria', escopo_tipo: 'igreja' }], meta: { acoes: ['revogar'] } }

describe('acessos e convites', () => {
  it('convida escolhendo a pessoa pela busca, o papel e o escopo; o 422 de pessoa sem contato aparece no campo', async () => {
    const pedidos: unknown[] = []
    servidor.use(
      ...apiDeAcessos({ acessos: [] }),
      http.post(`${API}/painel/convites`, async ({ request }) => {
        pedidos.push(await request.json())
        return HttpResponse.json({ message: 'x', errors: { pessoa: ['pessoa_sem_contato'] } }, { status: 422 })
      }),
    )
    const usuario = userEvent.setup()
    renderizarComProvedores(<TelaAcessos />, recursos)

    await usuario.type(screen.getByLabelText(/Buscar pessoa/), 'Clara')
    await usuario.click(screen.getByRole('button', { name: 'Buscar' }))
    await usuario.click(await screen.findByRole('combobox', { name: 'Pessoa' }))
    await usuario.click(await screen.findByRole('option', { name: /Clara/ }))
    await usuario.click(screen.getByRole('combobox', { name: 'Papel' }))
    await usuario.click(await screen.findByRole('option', { name: 'Secretaria' }))
    await usuario.click(screen.getByRole('combobox', { name: 'Igreja' }))
    await usuario.click(await screen.findByRole('option', { name: 'Igreja X' }))
    await usuario.click(screen.getByRole('button', { name: 'Enviar convite' }))

    expect(await screen.findByText(/não tem e-mail nem celular/)).toBeInTheDocument()
    expect(pedidos).toEqual([{ pessoa: 'pe-1', papel: 'p-sec', escopo_tipo: 'igreja', escopo: 'ig-x' }])
  })

  it('revogar pede confirmação e atualiza a lista', async () => {
    const estado = { acessos: [acessoAtivo] }
    let revogados = 0
    servidor.use(
      ...apiDeAcessos(estado),
      http.delete(`${API}/painel/acessos/:id`, () => {
        revogados++
        estado.acessos = [{ ...acessoAtivo, estado: 'revogado', meta: { acoes: [] } }]
        return new HttpResponse(null, { status: 204 })
      }),
    )
    const usuario = userEvent.setup()
    renderizarComProvedores(<TelaAcessos />, recursos)

    await usuario.click(await screen.findByRole('button', { name: 'Revogar o acesso de Marta' }))
    const dialogo = await screen.findByRole('dialog', { name: 'Revogar acesso?' })
    expect(revogados).toBe(0)
    await usuario.click(within(dialogo).getByRole('button', { name: 'Sim, revogar' }))

    await waitFor(() => expect(screen.getByRole('cell', { name: 'Revogado' })).toBeInTheDocument())
    expect(revogados).toBe(1)
    expect(screen.queryByRole('button', { name: 'Revogar o acesso de Marta' })).not.toBeInTheDocument()
  })

  it('o último administrador tem mensagem própria', async () => {
    servidor.use(
      ...apiDeAcessos({ acessos: [acessoAtivo] }),
      http.delete(`${API}/painel/acessos/:id`, () => HttpResponse.json({ codigo: 'ultimo_administrador' }, { status: 422 })),
    )
    const usuario = userEvent.setup()
    renderizarComProvedores(<TelaAcessos />, recursos)

    await usuario.click(await screen.findByRole('button', { name: 'Revogar o acesso de Marta' }))
    await usuario.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Sim, revogar' }))

    expect(await screen.findByText(/ao menos um Administrador ativo/)).toBeInTheDocument()
  })
})

describe('papéis', () => {
  it('as permissões vêm do catálogo, agrupadas, com as sensíveis por categoria', async () => {
    servidor.use(
      http.get(`${API}/painel/papeis`, () => HttpResponse.json({ data: papeis })),
      http.get(`${API}/painel/permissoes`, () => HttpResponse.json({ data: catalogo })),
    )
    const usuario = userEvent.setup()
    renderizarComProvedores(<TelaPapeis />, recursos)

    await usuario.click(await screen.findByRole('button', { name: 'Novo papel' }))
    const editor = await screen.findByRole('form', { name: 'Editar papel' })
    const grupos = within(editor).getAllByRole('group').map((g) => within(g).getAllByRole('checkbox').length)
    expect(grupos).toEqual([3, 1, 2, 1, 1])
    expect(within(editor).getByRole('group', { name: /Saúde/ })).toHaveTextContent('Dado sensível')
    expect(within(editor).getByRole('group', { name: /Fé e conversão/ })).toHaveTextContent('Dado sensível')
    expect(within(editor).getByRole('group', { name: /Pessoas/ })).not.toHaveTextContent('Dado sensível')
  })

  it('escalada bloqueada e administrador sem governança mostram mensagens próprias', async () => {
    const respostas = [
      HttpResponse.json({ codigo: 'escalada_bloqueada' }, { status: 403 }),
      HttpResponse.json({ codigo: 'administrador_sem_governanca' }, { status: 422 }),
    ]
    const corpos: unknown[] = []
    servidor.use(
      http.get(`${API}/painel/papeis`, () => HttpResponse.json({ data: papeis })),
      http.get(`${API}/painel/permissoes`, () => HttpResponse.json({ data: catalogo })),
      http.put(`${API}/painel/papeis/:id`, async ({ request }) => {
        corpos.push(await request.json())
        return respostas.shift() ?? new HttpResponse(null, { status: 500 })
      }),
    )
    const usuario = userEvent.setup()
    renderizarComProvedores(<TelaPapeis />, recursos)

    await usuario.click(await screen.findByRole('button', { name: 'Administrador' }))
    const editor = await screen.findByRole('form', { name: 'Editar papel' })
    await usuario.click(within(within(editor).getByRole('group', { name: /Saúde/ })).getByRole('checkbox', { name: 'Ver' }))
    await usuario.click(within(editor).getByRole('button', { name: 'Salvar papel' }))
    expect(await screen.findByText(/não pode conceder uma permissão/)).toBeInTheDocument()

    await usuario.click(within(editor).getByRole('button', { name: 'Salvar papel' }))
    expect(await screen.findByText(/não pode perder a gestão de papéis/)).toBeInTheDocument()
    expect(corpos[0]).toMatchObject({ nome: 'Administrador' })
  })
})
