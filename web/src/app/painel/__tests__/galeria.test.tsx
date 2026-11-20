// Galeria de desenvolvimento: renderiza os componentes reais sem chamar a API.
import { createMemoryHistory } from '@tanstack/react-router'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import { criarClienteConsulta } from '../clienteConsulta'
import { i18nPainel } from '../i18n'
import { Raiz } from '../Raiz'
import { criarRoteador } from '../roteador'

describe('Galeria da interface (dev)', () => {
  it('abre sem login nem API e a tabela pede só a primeira página', async () => {
    const roteador = criarRoteador(createMemoryHistory({ initialEntries: ['/painel/galeria'] }))
    render(<Raiz roteador={roteador} i18n={i18nPainel} clienteConsulta={criarClienteConsulta()} />)

    expect(await screen.findByRole('heading', { name: 'Galeria da interface' }, { timeout: 10_000 })).toBeInTheDocument()
    expect(await screen.findByText('pessoa.criada #1', {}, { timeout: 10_000 })).toBeInTheDocument()
    expect(screen.queryByText('pessoa.atualizada #26')).not.toBeInTheDocument()

    await userEvent.setup().click(screen.getByRole('button', { name: 'Próxima página' }))
    expect(await screen.findByText('pessoa.atualizada #26', {}, { timeout: 10_000 })).toBeInTheDocument()
  })
})
