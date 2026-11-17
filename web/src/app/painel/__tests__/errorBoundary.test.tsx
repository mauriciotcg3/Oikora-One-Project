// OIK-SPEC-0001 T-37, caso 6: o Error Boundary mostra e registra o `trace_id` (OIK-ADR-0006 §14).
import { screen } from '@testing-library/react'
import type { ReactNode } from 'react'
import { describe, expect, it, vi } from 'vitest'
import { ErroHttp } from '../../../shared/http'
import { renderizarComProvedores } from '../../../test/renderizar'
import { FronteiraDeErro } from '../FronteiraDeErro'
import { painelPtBR } from '../i18n/pt-BR'
import { destinoDepoisDoLogin } from '../roteador'
import { urlDoLogin } from '../SessaoPainel'

const TRACE = '4bf92f3577b34da6a3ce929d0e0e4736'

function Quebra({ erro }: { erro: unknown }): ReactNode {
  throw erro
}

describe('Error Boundary do painel', () => {
  it('mostra e registra o trace_id do pedido que falhou', () => {
    const registro = vi.spyOn(console, 'error').mockImplementation(() => undefined)

    renderizarComProvedores(
      <FronteiraDeErro>
        <Quebra erro={new ErroHttp(500, { codigo: 'erro_interno' }, TRACE)} />
      </FronteiraDeErro>,
      { painel: painelPtBR },
    )

    expect(screen.getByRole('heading', { name: 'Algo deu errado' })).toBeInTheDocument()
    expect(screen.getByText(`Código para o suporte: ${TRACE}`)).toBeInTheDocument()
    expect(registro).toHaveBeenCalledWith('painel.erro', expect.objectContaining({ trace_id: TRACE, erro: 'ErroHttp' }))
    registro.mockRestore()
  })

  it('usa o trace atual quando o erro não veio de um pedido', () => {
    const registro = vi.spyOn(console, 'error').mockImplementation(() => undefined)

    renderizarComProvedores(
      <FronteiraDeErro>
        <Quebra erro={new TypeError('x')} />
      </FronteiraDeErro>,
      { painel: painelPtBR },
    )

    expect(screen.getByText(/^Código para o suporte: [0-9a-f]{32}$/)).toBeInTheDocument()
    registro.mockRestore()
  })
})

describe('volta ao login', () => {
  it('leva a URL atual e só volta para dentro do painel', () => {
    expect(urlDoLogin({ pathname: '/painel/pessoas', search: '?q=ana' } as Location)).toBe('/painel/login?voltar=%2Fpainel%2Fpessoas%3Fq%3Dana')
    expect(urlDoLogin({ pathname: '/painel/login', search: '' } as Location)).toBe('/painel/login')
    expect(destinoDepoisDoLogin('?voltar=%2Fpainel%2Fpessoas')).toBe('/painel/pessoas')
    expect(destinoDepoisDoLogin('?voltar=https%3A%2F%2Fmal.example')).toBeNull()
    expect(destinoDepoisDoLogin('?voltar=%2F%2Fmal.example')).toBeNull()
  })
})
