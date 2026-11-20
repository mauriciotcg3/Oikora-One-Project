// OIK-SPEC-0001 T-53 (CA-44, CA-47): filtros na URL, tabela com falha por texto e ícone,
// detalhe em camadas aberto por teclado.
import { createMemoryHistory, createRootRoute, createRoute, createRouter, RouterProvider } from '@tanstack/react-router'
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useState, type ReactNode } from 'react'
import { describe, expect, it } from 'vitest'
import { renderizarComProvedores } from '../../../test/renderizar'
import { DetalheEmCamadas } from '../DetalheEmCamadas'
import { FiltrosNaUrl, useFiltrosDaUrl, type CampoDeFiltro } from '../FiltrosNaUrl'
import { TabelaDeRegistros } from '../TabelaDeRegistros'

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
        rotuloAbrir={(linha) => `Ver ${linha.evento}`}
        aoAbrir={setAberto}
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

describe('Filtros na URL', () => {
  it('aplicar atualiza a URL, abrir a URL restaura os filtros e limpar zera URL e lista', async () => {
    const usuario = userEvent.setup()
    const roteador = abrir('/lista')
    await screen.findByRole('table', { name: 'Registros' })

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

    expect(await screen.findByText('pessoa.criada')).toBeInTheDocument()
    expect(screen.getByText('pessoa.excluida')).toBeInTheDocument()
    expect(screen.getByLabelText('Buscar')).toHaveValue('')
  })
})

describe('Tabela e detalhe em camadas', () => {
  it('a linha de falha traz o texto "Falha" e o leitor de tela a identifica; a outra não', async () => {
    abrir('/lista')
    await screen.findByRole('table', { name: 'Registros' })

    expect(screen.getByRole('row', { name: /Falha.*pessoa\.excluida/ })).toBeInTheDocument()
    expect(within(screen.getByRole('row', { name: /pessoa\.criada/ })).queryByText('Falha')).not.toBeInTheDocument()
  })

  it('o detalhe abre por teclado, mostra o resumo primeiro e a camada técnica só sob demanda', async () => {
    const usuario = userEvent.setup()
    abrir('/lista')
    const botao = await screen.findByRole('button', { name: 'Ver pessoa.excluida' })

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
