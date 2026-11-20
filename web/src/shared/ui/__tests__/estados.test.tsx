// OIK-SPEC-0001 T-50 (CA-41): componentes de estado do Oikora UI Standard — papéis ARIA,
// ação de repetir por teclado, detalhes técnicos sob demanda e nenhum texto fixo.
import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { renderizarComProvedores } from '../../../test/renderizar'
import {
  AjudaContextual,
  DetalhesTecnicos,
  EstadoAviso,
  EstadoCarregando,
  EstadoErro,
  EstadoIndisponivel,
  EstadoSucesso,
  EstadoVazio,
} from '..'

const detalhes = {
  rotulo: 'Detalhes técnicos',
  rotuloCopiar: 'Copiar para o suporte',
  itens: [
    { rotulo: 'Código do pedido', valor: 'req_123' },
    { rotulo: 'Correlação', valor: 'trace_456' },
  ],
}

describe('Estados', () => {
  it('carregando é anunciado como ocupado, com texto', () => {
    renderizarComProvedores(<EstadoCarregando texto="Carregando as pessoas" />, {})

    const regiao = screen.getByRole('status')
    expect(regiao).toHaveAttribute('aria-busy', 'true')
    expect(regiao).toHaveTextContent('Carregando as pessoas')
  })

  it('vazio explica o próximo passo e oferece a ação', async () => {
    const usuario = userEvent.setup()
    const aoClicar = vi.fn()
    renderizarComProvedores(
      <EstadoVazio titulo="Nenhum registro" texto="Ajuste o período para ver mais." acao={{ rotulo: 'Limpar filtros', aoClicar }} />,
      {},
    )

    expect(screen.getByRole('heading', { name: 'Nenhum registro' })).toBeInTheDocument()
    expect(screen.getByText('Ajuste o período para ver mais.')).toBeVisible()
    await usuario.click(screen.getByRole('button', { name: 'Limpar filtros' }))
    expect(aoClicar).toHaveBeenCalledOnce()
  })

  it('erro usa role=alert e repete a ação por teclado', async () => {
    const usuario = userEvent.setup()
    const aoTentarDeNovo = vi.fn()
    renderizarComProvedores(
      <EstadoErro titulo="Não foi possível carregar" texto="Tente de novo em instantes." rotuloTentar="Tentar de novo" aoTentarDeNovo={aoTentarDeNovo} />,
      {},
    )

    const alerta = screen.getByRole('alert')
    expect(within(alerta).getByText('Não foi possível carregar')).toBeVisible()
    await usuario.tab()
    expect(screen.getByRole('button', { name: 'Tentar de novo' })).toHaveFocus()
    await usuario.keyboard('{Enter}')
    expect(aoTentarDeNovo).toHaveBeenCalledOnce()
  })

  it('request_id e trace_id ficam ocultos até abrir os detalhes técnicos, por teclado', async () => {
    const usuario = userEvent.setup()
    renderizarComProvedores(<EstadoErro titulo="Falhou" texto="Algo deu errado." detalhes={detalhes} />, {})

    expect(screen.queryByText('req_123')).not.toBeInTheDocument()
    const botao = screen.getByRole('button', { name: 'Detalhes técnicos' })
    expect(botao).toHaveAttribute('aria-expanded', 'false')

    botao.focus()
    await usuario.keyboard('{Enter}')

    expect(botao).toHaveAttribute('aria-expanded', 'true')
    expect(screen.getByText('req_123')).toBeVisible()
    expect(screen.getByText('trace_456')).toBeVisible()
  })

  it('copia os identificadores para o suporte e registra a abertura sem valores', async () => {
    const usuario = userEvent.setup()
    const registro = vi.spyOn(console, 'info').mockImplementation(() => undefined)
    const escrever = vi.spyOn(navigator.clipboard, 'writeText').mockResolvedValue()
    renderizarComProvedores(<DetalhesTecnicos {...detalhes} />, {})

    await usuario.click(screen.getByRole('button', { name: 'Detalhes técnicos' }))
    await usuario.click(screen.getByRole('button', { name: 'Copiar para o suporte' }))

    expect(escrever).toHaveBeenCalledWith('Código do pedido: req_123\nCorrelação: trace_456')
    const chamadas = JSON.stringify(registro.mock.calls)
    expect(chamadas).toContain('ui.detalhes_tecnicos_abertos')
    expect(chamadas).not.toContain('req_123')
  })

  it('indisponível e aviso se distinguem por texto, não só por cor, e sucesso é anunciado com educação', () => {
    renderizarComProvedores(
      <>
        <EstadoIndisponivel titulo="Serviço indisponível" texto="Voltamos em breve." />
        <EstadoAviso titulo="Atenção" texto="Revise antes de continuar." />
        <EstadoSucesso titulo="Salvo" texto="Tudo certo." />
      </>,
      {},
    )

    expect(screen.getByRole('alert')).toHaveTextContent('Serviço indisponível')
    const avisos = screen.getAllByRole('status')
    expect(avisos.map((a) => a.textContent)).toEqual([expect.stringContaining('Atenção'), expect.stringContaining('Salvo')])
  })

  it('ajuda contextual abre e fecha por teclado', async () => {
    const usuario = userEvent.setup()
    renderizarComProvedores(<AjudaContextual rotulo="Ajuda sobre filtros">Use o período para reduzir a lista.</AjudaContextual>, {})

    const botao = screen.getByRole('button', { name: 'Ajuda sobre filtros' })
    expect(screen.queryByText('Use o período para reduzir a lista.')).not.toBeInTheDocument()
    botao.focus()
    await usuario.keyboard('{Enter}')
    expect(screen.getByText('Use o período para reduzir a lista.')).toBeVisible()
    await usuario.keyboard('{Enter}')
    expect(screen.queryByText('Use o período para reduzir a lista.')).not.toBeInTheDocument()
  })
})
