// OIK-SPEC-0001 T-55 (CA-45): consulta, trilha e detalhe progressivo da auditoria.
import { createMemoryHistory, createRootRoute, createRoute, createRouter, RouterProvider } from '@tanstack/react-router'
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw/http'
import { beforeAll, describe, expect, it } from 'vitest'
import { renderizarComProvedores } from '../../../test/renderizar'
import { servidor } from '../../../test/servidor'
import { auditoriaPtBR, TelaAuditoria } from '../index'

const API = '*/api/v1'

const linha = {
  ocorrido_em: '2026-11-20T13:00:00-03:00',
  evento: 'pessoa.atualizada',
  ator: { acesso: 'ac-1', nome: 'Ana Souza', plataforma: false },
  alvo: { tipo: 'pessoa', id: 'pe-1' },
  igreja: { uuid: 'ig-x', nome: 'Igreja X' },
  campos: ['profissao', 'nascimento'],
  metadados: { origem: 'painel' },
  canal: 'painel',
  request_id: 'req-1',
  resultado: 'falha',
  codigo_falha: 'dados_invalidos',
  trace_id: 'trace-1',
}

function abrir(caminho = '/auditoria') {
  const raiz = createRootRoute()
  const auditoria = createRoute({ getParentRoute: () => raiz, path: '/auditoria', component: TelaAuditoria })
  const roteador = createRouter({ routeTree: raiz.addChildren([auditoria]), history: createMemoryHistory({ initialEntries: [caminho] }) })
  renderizarComProvedores(<RouterProvider router={roteador} />, { auditoria: auditoriaPtBR })
  return roteador
}

beforeAll(async () => {
  await import('../../../shared/ui/TabelaDeRegistros/GradeDeRegistros')
}, 60_000)

describe('consulta da auditoria', { timeout: 15_000 }, () => {
  it('leva período, evento, ator, alvo, igreja, busca e resultado para a URL e para a página pedida', async () => {
    const consultas: URLSearchParams[] = []
    servidor.use(http.get(`${API}/painel/auditoria`, ({ request }) => {
      consultas.push(new URL(request.url).searchParams)
      return HttpResponse.json({ data: [linha], meta: { pagina: 1, por_pagina: 25, total: 1 } })
    }))
    const usuario = userEvent.setup()
    const roteador = abrir()

    expect(await screen.findByText('Ficha atualizada', {}, { timeout: 5000 })).toBeInTheDocument()
    await usuario.type(screen.getByLabelText('De'), '2026-11-01')
    await usuario.type(screen.getByLabelText('Até'), '2026-11-30')
    await usuario.type(screen.getByLabelText('Evento'), 'pessoa.atualizada')
    await usuario.type(screen.getByLabelText('Ator'), 'ac-1')
    await usuario.type(screen.getByLabelText('Tipo do alvo'), 'pessoa')
    await usuario.type(screen.getByLabelText('Alvo'), 'pe-1')
    await usuario.type(screen.getByLabelText('Igreja'), 'ig-x')
    await usuario.type(screen.getByLabelText('Buscar por evento ou alvo'), 'pessoa')
    await usuario.click(screen.getByLabelText('Resultado'))
    await usuario.click(screen.getByRole('option', { name: 'Falha' }))
    await usuario.click(screen.getByRole('button', { name: 'Aplicar filtros' }))

    await waitFor(() => expect(roteador.state.location.search).toMatchObject({
      de: '2026-11-01', ate: '2026-11-30', evento: 'pessoa.atualizada', ator: 'ac-1',
      alvo_tipo: 'pessoa', alvo: 'pe-1', igreja: 'ig-x', busca: 'pessoa', resultado: 'falha',
    }))
    await waitFor(() => expect(consultas.at(-1)?.get('alvo')).toBe('pe-1'))
    expect(consultas.at(-1)?.get('busca')).toBe('pessoa')
    expect(consultas.at(-1)?.get('pagina')).toBe('1')
    expect(consultas.at(-1)?.get('por_pagina')).toBe('25')

    await usuario.click(screen.getByRole('button', { name: 'Limpar filtros' }))
    await waitFor(() => expect(roteador.state.location.search).toEqual({}))
  })

  it('mostra a trilha em ordem e revela campos, metadados e correlação somente sob demanda', async () => {
    servidor.use(http.get(`${API}/painel/auditoria`, () => HttpResponse.json({
      data: [linha, { ...linha, ocorrido_em: '2026-11-19T13:00:00-03:00', evento: 'pessoa.criada', resultado: 'sucesso', codigo_falha: null }],
      meta: { pagina: 1, por_pagina: 25, total: 2 },
    })))
    const usuario = userEvent.setup()
    abrir('/auditoria?alvo_tipo=pessoa&alvo=pe-1')

    expect(await screen.findByRole('heading', { name: 'Trilha de pessoa pe-1' }, { timeout: 5000 })).toBeInTheDocument()
    await screen.findByRole('button', { name: 'Ver detalhes de Ficha atualizada' })
    const linhas = screen.getAllByRole('row').slice(1)
    expect(linhas[0]).toHaveTextContent('Ficha atualizada')
    expect(linhas[1]).toHaveTextContent('Pessoa cadastrada')

    const botao = screen.getByRole('button', { name: 'Ver detalhes de Ficha atualizada' })
    botao.focus()
    await usuario.keyboard('{Enter}')
    const detalhe = await screen.findByRole('dialog', { name: 'Ficha atualizada' })
    expect(within(detalhe).getByText('Ana Souza')).toBeInTheDocument()
    expect(within(detalhe).queryByText('trace-1')).not.toBeInTheDocument()
    expect(within(detalhe).queryByText('profissao, nascimento')).not.toBeInTheDocument()

    await usuario.click(within(detalhe).getByRole('button', { name: 'Detalhes técnicos' }))
    expect(within(detalhe).getByText('trace-1')).toBeInTheDocument()
    expect(within(detalhe).getByText('req-1')).toBeInTheDocument()
    expect(within(detalhe).getByText('profissao, nascimento')).toBeInTheDocument()
    expect(within(detalhe).getByText('{"origem":"painel"}')).toBeInTheDocument()
    expect(within(detalhe).queryByText(/valor_anterior|valor_posterior/)).not.toBeInTheDocument()
  })

  it('usa EstadoErro e mantém request_id atrás dos detalhes técnicos', async () => {
    servidor.use(http.get(`${API}/painel/auditoria`, () => HttpResponse.json({ codigo: 'indisponivel', request_id: 'req-erro' }, { status: 500 })))
    const usuario = userEvent.setup()
    abrir()

    const erro = await screen.findByRole('alert')
    expect(erro).toHaveTextContent('Não foi possível carregar a auditoria')
    expect(within(erro).queryByText('req-erro')).not.toBeInTheDocument()
    await usuario.click(within(erro).getByRole('button', { name: 'Detalhes técnicos' }))
    expect(within(erro).getByText('req-erro')).toBeInTheDocument()
  })
})
