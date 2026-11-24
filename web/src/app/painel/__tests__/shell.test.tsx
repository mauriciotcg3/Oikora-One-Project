// OIK-SPEC-0001 T-51 (CA-42): shell do painel — pular ao conteúdo, menu por capacidades,
// navegação estreita com Esc e item ativo anunciado.
import { createMemoryHistory } from '@tanstack/react-router'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw/http'
import { afterEach, describe, expect, it } from 'vitest'
import { servidor } from '../../../test/servidor'
import { criarClienteConsulta } from '../clienteConsulta'
import { i18nPainel } from '../i18n'
import { Raiz } from '../Raiz'
import { criarRoteador } from '../roteador'

const matchMediaOriginal = window.matchMedia

/** jsdom não mede tela: `larga` decide se a consulta `min-width` casa. */
function definirTela(larga: boolean): void {
  window.matchMedia = ((consulta: string) => ({
    matches: larga && consulta.includes('min-width'),
    media: consulta,
    onchange: null,
    addEventListener: () => undefined,
    removeEventListener: () => undefined,
    addListener: () => undefined,
    removeListener: () => undefined,
    dispatchEvent: () => false,
  })) as typeof window.matchMedia
}

function responderMe(permissoes: string[]): void {
  servidor.use(
    http.get('*/api/v1/me', () =>
      HttpResponse.json({
        data: {
          conta: { id: 'c1' },
          organizacao_ativa: { id: 'o1', nome: 'Igreja Central' },
          organizacoes: [],
          autorizacao: { capacidades: permissoes.map((permissao) => ({ permissao, escopo_tipo: 'organizacao', escopo_ids: [] })) },
          elevacao: { ativa: false, expira_em: null },
          dois_fatores: { canal: null, obrigatorio: false },
        },
      }),
    ),
  )
}

function abrir(caminho: string): void {
  const roteador = criarRoteador(createMemoryHistory({ initialEntries: [caminho] }))
  render(<Raiz roteador={roteador} i18n={i18nPainel} clienteConsulta={criarClienteConsulta()} />)
}

afterEach(() => {
  window.matchMedia = matchMediaOriginal
})

describe('Shell do painel', () => {
  it('o primeiro Tab leva a "Ir para o conteúdo" e o Enter move o foco para o main', async () => {
    definirTela(true)
    responderMe([])
    const usuario = userEvent.setup()
    abrir('/painel/')
    await screen.findByRole('heading', { name: 'Painel da igreja' }, { timeout: 5000 })

    await usuario.tab()
    expect(screen.getByRole('link', { name: 'Ir para o conteúdo' })).toHaveFocus()
    await usuario.keyboard('{Enter}')

    expect(screen.getByRole('main')).toHaveFocus()
  })

  it('monta o menu pelas capacidades do /me e nega o acesso direto à Auditoria sem auditoria.ver', async () => {
    definirTela(true)
    responderMe(['pessoas.ver'])
    abrir('/painel/auditoria')

    expect(await screen.findByText('Você não tem acesso a esta área')).toBeInTheDocument()
    const menu = screen.getByRole('navigation', { name: 'Menu do painel' })
    expect(menu).toHaveTextContent('Início')
    expect(menu).not.toHaveTextContent('Auditoria')
  })

  it('mostra o item Auditoria com auditoria.ver', async () => {
    definirTela(true)
    responderMe(['auditoria.ver'])
    abrir('/painel/auditoria')

    expect(await screen.findByRole('link', { name: 'Auditoria' })).toBeInTheDocument()
    expect(screen.queryByText('Você não tem acesso a esta área')).not.toBeInTheDocument()
  })

  it('em tela estreita o menu abre por botão com rótulo e fecha com Esc devolvendo o foco', async () => {
    definirTela(false)
    responderMe(['auditoria.ver'])
    const usuario = userEvent.setup()
    abrir('/painel/')
    await screen.findByRole('heading', { name: 'Painel da igreja' }, { timeout: 5000 })
    expect(screen.queryByRole('navigation', { name: 'Menu do painel' })).not.toBeInTheDocument()

    const botao = screen.getByRole('button', { name: 'Abrir menu' })
    await usuario.click(botao)
    expect(await screen.findByRole('navigation', { name: 'Menu do painel' })).toBeInTheDocument()

    await usuario.keyboard('{Escape}')
    await waitFor(() => expect(screen.queryByRole('navigation', { name: 'Menu do painel' })).not.toBeInTheDocument())
    expect(botao).toHaveFocus()
  })

  it('anuncia o item ativo com aria-current=page', async () => {
    definirTela(true)
    responderMe(['auditoria.ver'])
    abrir('/painel/auditoria')

    expect(await screen.findByRole('link', { name: 'Auditoria' })).toHaveAttribute('aria-current', 'page')
    expect(screen.getByRole('link', { name: 'Início' })).not.toHaveAttribute('aria-current')
  })
})
