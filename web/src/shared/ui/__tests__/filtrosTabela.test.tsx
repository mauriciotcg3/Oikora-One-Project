// OIK-SPEC-0001 T-53 (CA-44, CA-47): filtros na URL, tabela com falha por texto e ícone,
// detalhe em camadas aberto por teclado.
import { createMemoryHistory, createRootRoute, createRoute, createRouter, RouterProvider } from '@tanstack/react-router'
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useQuery, keepPreviousData } from '@tanstack/react-query'
import { http, HttpResponse } from 'msw/http'
import { useState, type ReactNode } from 'react'
import { beforeAll, describe, expect, it } from 'vitest'
import { requisitar } from '../../http'
import { servidor } from '../../../test/servidor'
import { renderizarComProvedores } from '../../../test/renderizar'
import { DetalheEmCamadas } from '../DetalheEmCamadas'
import { FiltrosNaUrl, lerPaginacao, useFiltrosDaUrl, usePaginacaoDaUrl, type CampoDeFiltro } from '../FiltrosNaUrl'
import { TabelaDeRegistros, type TextosDaTabela } from '../TabelaDeRegistros'

interface Registro {
  id: string
  evento: string
  situacao: 'ok' | 'falha'
  falha: boolean
  traceId: string
}

const REGISTROS: Registro[] = [
  { id: '1', evento: 'pessoa.criada', situacao: 'ok', falha: false, traceId: 'trace-ok-1' },
  { id: '2', evento: 'pessoa.excluida', situacao: 'falha', falha: true, traceId: 'trace-falha-2' },
]

const TEXTOS_TABELA: TextosDaTabela = {
  linhasPorPagina: 'Linhas por página',
  exibindo: (de, ate, total) => `${de}–${ate} de ${total}`,
  paginaAnterior: 'Página anterior',
  proximaPagina: 'Próxima página',
  semLinhas: 'Nenhum registro',
}

const CAMPOS: CampoDeFiltro[] = [
  { chave: 'busca', rotulo: 'Buscar' },
  {
    chave: 'situacao',
    rotulo: 'Situação',
    opcoes: [
      { valor: 'ok', rotulo: 'Sucesso' },
      { valor: 'falha', rotulo: 'Falha' },
    ],
  },
]

function Pagina(): ReactNode {
  const filtros = useFiltrosDaUrl(CAMPOS)
  const [aberto, setAberto] = useState<Registro | null>(null)
  const visiveis = REGISTROS.filter(
    (registro) =>
      (!filtros.situacao || registro.situacao === filtros.situacao) &&
      (!filtros.busca || registro.evento.includes(filtros.busca)),
  )
  return (
    <>
      <FiltrosNaUrl campos={CAMPOS} rotuloAplicar="Aplicar filtros" rotuloLimpar="Limpar filtros" rotuloGrupo="Filtros" rotuloTodos="Todas" />
      <TabelaDeRegistros
        rotulo="Registros"
        colunas={[{ chave: 'evento', rotulo: 'Evento', valor: (linha) => linha.evento }]}
        linhas={visiveis}
        textoFalha="Falha"
        textoCarregando="Carregando a tabela"
        rotuloAbrir={(linha) => `Ver ${linha.evento}`}
        aoAbrir={setAberto}
        paginacao={{ pagina: 1, porPagina: 25, total: visiveis.length, aoMudar: () => undefined }}
        textos={TEXTOS_TABELA}
      />
      <DetalheEmCamadas
        aberto={aberto !== null}
        titulo="Detalhe do registro"
        resumo={[{ rotulo: 'Evento', valor: aberto?.evento ?? '' }]}
        tecnico={{ rotulo: 'Detalhes técnicos', rotuloCopiar: 'Copiar', itens: [{ rotulo: 'trace_id', valor: aberto?.traceId ?? '' }] }}
        rotuloFechar="Fechar detalhe"
        aoFechar={() => setAberto(null)}
      />
    </>
  )
}

function abrir(caminho: string) {
  const raiz = createRootRoute()
  const lista = createRoute({ getParentRoute: () => raiz, path: '/lista', component: Pagina })
  const roteador = createRouter({ routeTree: raiz.addChildren([lista]), history: createMemoryHistory({ initialEntries: [caminho] }) })
  renderizarComProvedores(<RouterProvider router={roteador} />, {})
  return roteador
}

// O DataGrid é um chunk `lazy`; carregá-lo antes evita que o primeiro teste pague o import com a suíte em paralelo.
beforeAll(async () => {
  await import('../TabelaDeRegistros/GradeDeRegistros')
}, 60_000)

describe('Filtros na URL', { timeout: 15_000 }, () => {
  it('aplicar atualiza a URL, abrir a URL restaura os filtros e limpar zera URL e lista', async () => {
    const usuario = userEvent.setup()
    const roteador = abrir('/lista')
    await screen.findByRole('grid', { name: 'Registros' }, { timeout: 5000 })

    await usuario.type(screen.getByLabelText('Buscar'), 'excluida')
    await usuario.click(screen.getByRole('button', { name: 'Aplicar filtros' }))

    await waitFor(() => expect(roteador.state.location.search).toEqual({ busca: 'excluida' }))
    expect(screen.queryByText('pessoa.criada')).not.toBeInTheDocument()

    await usuario.click(screen.getByRole('button', { name: 'Limpar filtros' }))
    await waitFor(() => expect(roteador.state.location.search).toEqual({}))
    expect(screen.getByText('pessoa.criada')).toBeInTheDocument()
  })

  it('abrir uma URL com filtro restaura o formulário e a lista', async () => {
    abrir('/lista?busca=criada')

    expect(await screen.findByLabelText('Buscar')).toHaveValue('criada')
    expect(screen.queryByText('pessoa.excluida')).not.toBeInTheDocument()
  })

  it('valores inválidos na URL são ignorados com segurança', async () => {
    abrir('/lista?situacao=<script>&busca=' + 'x'.repeat(500))

    expect(await screen.findByText('pessoa.criada', {}, { timeout: 5000 })).toBeInTheDocument()
    expect(screen.getByText('pessoa.excluida')).toBeInTheDocument()
    expect(screen.getByLabelText('Buscar')).toHaveValue('')
  })
})

describe('Tabela e detalhe em camadas', { timeout: 15_000 }, () => {
  it('a linha de falha traz o texto "Falha" e o leitor de tela a identifica; a outra não', async () => {
    abrir('/lista')
    await screen.findByRole('grid', { name: 'Registros' }, { timeout: 5000 })

    expect(screen.getByRole('row', { name: /Falha.*pessoa\.excluida/ })).toBeInTheDocument()
    expect(within(screen.getByRole('row', { name: /pessoa\.criada/ })).queryByText('Falha')).not.toBeInTheDocument()
  })

  it('o detalhe abre por teclado, mostra o resumo primeiro e a camada técnica só sob demanda', async () => {
    const usuario = userEvent.setup()
    abrir('/lista')
    const botao = await screen.findByRole('button', { name: 'Ver pessoa.excluida' }, { timeout: 5000 })

    botao.focus()
    await usuario.keyboard('{Enter}')

    const detalhe = await screen.findByRole('dialog', { name: 'Detalhe do registro' })
    expect(within(detalhe).getByText('pessoa.excluida')).toBeInTheDocument()
    expect(within(detalhe).queryByText('trace-falha-2')).not.toBeInTheDocument()

    await usuario.click(within(detalhe).getByRole('button', { name: 'Detalhes técnicos' }))
    expect(within(detalhe).getByText('trace-falha-2')).toBeInTheDocument()

    await usuario.keyboard('{Escape}')
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    expect(botao).toHaveFocus()
  })
})

// Paginação no servidor (CA-44 + OIK-ADR-0006 §6): a tela pede só a página atual à API.
const TOTAL = 60

function PaginaDoServidor(): ReactNode {
  const filtros = useFiltrosDaUrl(CAMPOS)
  const { pagina, porPagina, mudar } = usePaginacaoDaUrl()
  const { data, isFetching } = useQuery({
    queryKey: ['registros', filtros, pagina, porPagina],
    queryFn: () =>
      requisitar<{ data: Registro[]; meta: { total: number } }>(
        'GET',
        `/registros?${new URLSearchParams({ ...(filtros.busca ? { busca: filtros.busca } : {}), pagina: String(pagina), por_pagina: String(porPagina) })}`,
      ),
    placeholderData: keepPreviousData,
  })
  return (
    <>
      <FiltrosNaUrl campos={CAMPOS} rotuloAplicar="Aplicar filtros" rotuloLimpar="Limpar filtros" rotuloGrupo="Filtros" rotuloTodos="Todas" />
      <TabelaDeRegistros
        rotulo="Registros"
        colunas={[{ chave: 'evento', rotulo: 'Evento', valor: (linha) => linha.evento }]}
        linhas={data?.data ?? []}
        textoFalha="Falha"
        textoCarregando="Carregando a tabela"
        rotuloAbrir={(linha) => `Ver ${linha.evento}`}
        aoAbrir={() => undefined}
        paginacao={{ pagina, porPagina, total: data?.meta.total ?? 0, aoMudar: mudar, carregando: isFetching }}
        textos={TEXTOS_TABELA}
      />
    </>
  )
}

function abrirComServidor(caminho: string) {
  const pedidos: URLSearchParams[] = []
  servidor.use(
    http.get('*/api/v1/registros', ({ request }) => {
      const consulta = new URL(request.url).searchParams
      pedidos.push(consulta)
      const porPagina = Number(consulta.get('por_pagina'))
      const pagina = Number(consulta.get('pagina'))
      const todos = Array.from({ length: TOTAL }, (_, i) => ({ id: String(i + 1), evento: `evento-${i + 1}`, situacao: 'ok', falha: false, traceId: 't' }))
      const filtrados = todos.filter((registro) => !consulta.get('busca') || registro.evento.includes(consulta.get('busca') ?? ''))
      return HttpResponse.json({
        data: filtrados.slice((pagina - 1) * porPagina, pagina * porPagina),
        meta: { pagina, por_pagina: porPagina, total: filtrados.length },
      })
    }),
  )
  const raiz = createRootRoute()
  const lista = createRoute({ getParentRoute: () => raiz, path: '/lista', component: PaginaDoServidor })
  const roteador = createRouter({ routeTree: raiz.addChildren([lista]), history: createMemoryHistory({ initialEntries: [caminho] }) })
  renderizarComProvedores(<RouterProvider router={roteador} />, {})
  return { roteador, pedidos }
}

describe('Paginação no servidor', { timeout: 15_000 }, () => {
  it('pede só a primeira página (25 de 60) e mostra o total sem baixar o resto', async () => {
    const { pedidos } = abrirComServidor('/lista')

    expect(await screen.findByText('evento-1', {}, { timeout: 5000 })).toBeInTheDocument()
    expect(screen.getByText('evento-25')).toBeInTheDocument()
    expect(screen.queryByText('evento-26')).not.toBeInTheDocument()
    expect(screen.getByText('1–25 de 60')).toBeInTheDocument()
    expect(pedidos.map((p) => `${p.get('pagina')}/${p.get('por_pagina')}`)).toEqual(['1/25'])
  })

  it('trocar de página faz um pedido novo da página e põe pagina na URL; filtrar volta à página 1', async () => {
    const usuario = userEvent.setup()
    const { roteador, pedidos } = abrirComServidor('/lista')
    await screen.findByText('evento-1', {}, { timeout: 5000 })

    await usuario.click(screen.getByRole('button', { name: 'Próxima página' }))

    expect(await screen.findByText('evento-26')).toBeInTheDocument()
    expect(screen.queryByText('evento-1')).not.toBeInTheDocument()
    expect(roteador.state.location.search).toEqual({ pagina: 2 })
    expect(pedidos.at(-1)?.get('pagina')).toBe('2')

    await usuario.type(screen.getByLabelText('Buscar'), 'evento-5')
    await usuario.click(screen.getByRole('button', { name: 'Aplicar filtros' }))

    await waitFor(() => expect(roteador.state.location.search).toEqual({ busca: 'evento-5' }))
    await waitFor(() => expect(pedidos.at(-1)?.get('busca')).toBe('evento-5'))
    expect(pedidos.at(-1)?.get('pagina')).toBe('1')
  })

  it('abrir a URL com pagina e por_pagina pede exatamente aquela página', async () => {
    const { pedidos } = abrirComServidor('/lista?pagina=2&por_pagina=50')

    expect(await screen.findByText('evento-51', {}, { timeout: 5000 })).toBeInTheDocument()
    expect(screen.queryByText('evento-50')).not.toBeInTheDocument()
    expect(pedidos.map((p) => `${p.get('pagina')}/${p.get('por_pagina')}`)).toEqual(['2/50'])
  })
})

describe('lerPaginacao', () => {
  it.each([
    [{}, { pagina: 1, porPagina: 25 }],
    [{ pagina: 3, por_pagina: 50 }, { pagina: 3, porPagina: 50 }],
    [{ pagina: 'abc', por_pagina: 7 }, { pagina: 1, porPagina: 25 }],
    [{ pagina: -2, por_pagina: 10_000 }, { pagina: 1, porPagina: 25 }],
    [null, { pagina: 1, porPagina: 25 }],
  ])('valor inválido na URL volta ao padrão (%j)', (busca, esperado) => {
    expect(lerPaginacao(busca)).toEqual(esperado)
  })
})
