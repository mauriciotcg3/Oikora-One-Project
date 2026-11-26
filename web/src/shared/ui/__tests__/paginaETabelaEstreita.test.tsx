// UI Standard: moldura das telas do painel e a tabela de registros em tela estreita (reflow, WCAG 1.4.10).
import Button from '@mui/material/Button'
import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { renderizarComProvedores } from '../../../test/renderizar'
import { PaginaDoPainel, TabelaDeRegistros } from '../index'

const matchMediaOriginal = window.matchMedia

/** jsdom não mede tela: simula até 899 px CSS (celular ou zoom de 200% a 400%). */
function telaEstreita(): void {
  window.matchMedia = ((consulta: string) => ({
    matches: consulta.includes('max-width'),
    media: consulta,
    onchange: null,
    addEventListener: () => undefined,
    removeEventListener: () => undefined,
    addListener: () => undefined,
    removeListener: () => undefined,
    dispatchEvent: () => false,
  })) as typeof window.matchMedia
}

afterEach(() => {
  window.matchMedia = matchMediaOriginal
})

interface Linha {
  id: string
  nome: string
  igreja: string
}

const TEXTOS = {
  linhasPorPagina: 'Linhas por página',
  exibindo: (de: number, ate: number, total: number) => `${de}–${ate} de ${total}`,
  paginaAnterior: 'Página anterior',
  proximaPagina: 'Próxima página',
  semLinhas: 'Nada aqui',
}

describe('PaginaDoPainel', () => {
  it('dá à tela um título de nível 1 que nomeia a seção, com descrição e ações', () => {
    renderizarComProvedores(
      <PaginaDoPainel titulo="Pessoas" descricao="Cadastros da organização." acoes={<Button>Cadastrar pessoa</Button>}>
        <p>conteúdo</p>
      </PaginaDoPainel>,
      {},
    )

    expect(screen.getByRole('heading', { level: 1, name: 'Pessoas' })).toBeInTheDocument()
    expect(screen.getByRole('region', { name: 'Pessoas' })).toHaveTextContent('Cadastros da organização.')
    expect(screen.getByRole('button', { name: 'Cadastrar pessoa' })).toBeVisible()
  })
})

describe('TabelaDeRegistros em tela estreita', () => {
  it('mostra um cartão por linha com o rótulo de cada coluna, sem carregar o DataGrid', async () => {
    telaEstreita()
    const aoAbrir = vi.fn()
    const aoMudar = vi.fn()
    const usuario = userEvent.setup()
    renderizarComProvedores(
      <TabelaDeRegistros<Linha>
        rotulo="Pessoas"
        colunas={[
          { chave: 'nome', rotulo: 'Nome', valor: (linha) => linha.nome },
          { chave: 'igreja', rotulo: 'Igreja', valor: (linha) => linha.igreja },
        ]}
        linhas={[{ id: 'p1', nome: 'Ana Lima', igreja: 'Central' }]}
        textoFalha="Falha"
        rotuloAbrir={(linha) => `Abrir ficha de ${linha.nome}`}
        textoAbrir="Abrir ficha"
        aoAbrir={aoAbrir}
        paginacao={{ pagina: 1, porPagina: 25, total: 30, aoMudar }}
        textos={TEXTOS}
        textoCarregando="Carregando"
      />,
      {},
    )

    const regiao = screen.getByRole('region', { name: 'Pessoas' })
    expect(within(regiao).queryByRole('grid')).not.toBeInTheDocument()
    const cartao = within(regiao).getByRole('listitem')
    expect(within(cartao).getByText('Igreja')).toBeInTheDocument()
    expect(within(cartao).getByText('Central')).toBeInTheDocument()
    await usuario.click(within(cartao).getByRole('button', { name: 'Abrir ficha de Ana Lima' }))
    expect(aoAbrir).toHaveBeenCalledWith(expect.objectContaining({ id: 'p1' }))
    expect(screen.getByText('1–25 de 30')).toBeInTheDocument()
    await usuario.click(screen.getByRole('button', { name: 'Próxima página' }))
    expect(aoMudar).toHaveBeenCalledWith(2, 25)
  })

  it('marca linhas sem perder as de outras páginas e respeita as que não podem ser marcadas', async () => {
    telaEstreita()
    const aoMudar = vi.fn()
    const usuario = userEvent.setup()
    renderizarComProvedores(
      <TabelaDeRegistros<Linha>
        rotulo="Pessoas"
        colunas={[{ chave: 'nome', rotulo: 'Nome', valor: (linha) => linha.nome }]}
        linhas={[
          { id: 'p1', nome: 'Ana Lima', igreja: 'Central' },
          { id: 'p2', nome: 'Bruno Reis', igreja: 'Central' },
        ]}
        textoFalha="Falha"
        rotuloAbrir={(linha) => `Abrir ${linha.nome}`}
        aoAbrir={() => undefined}
        paginacao={{ pagina: 2, porPagina: 25, total: 27, aoMudar: () => undefined }}
        textos={TEXTOS}
        selecao={{
          selecionadas: new Set(['de-outra-pagina']),
          aoMudar,
          podeSelecionar: (linha) => linha.id !== 'p2',
          rotuloLinha: (linha) => `Selecionar ${linha.nome}`,
          rotuloPagina: 'Selecionar a página',
          rotuloColuna: 'Selecionar',
        }}
        textoCarregando="Carregando"
      />,
      {},
    )

    expect(screen.getByRole('checkbox', { name: 'Selecionar Bruno Reis' })).toBeDisabled()
    await usuario.click(screen.getByRole('checkbox', { name: 'Selecionar a página' }))
    expect(aoMudar).toHaveBeenLastCalledWith(new Set(['de-outra-pagina', 'p1']))
  })
})
