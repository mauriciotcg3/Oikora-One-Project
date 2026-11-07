// OIK-SPEC-0001 T-01, caso 4 (parte web): a raiz da entrada `painel` renderiza sem erro.
import { createMemoryHistory } from '@tanstack/react-router'
import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { criarClienteConsulta } from '../clienteConsulta'
import { i18nPainel } from '../i18n'
import { Raiz } from '../Raiz'
import { criarRoteador } from '../roteador'

describe('Raiz do painel', () => {
  it('renderiza a tela de login em /painel/login', async () => {
    const roteador = criarRoteador(createMemoryHistory({ initialEntries: ['/painel/login'] }))

    render(<Raiz roteador={roteador} i18n={i18nPainel} clienteConsulta={criarClienteConsulta()} />)

    expect(await screen.findByRole('heading', { name: 'Entrar' })).toBeInTheDocument()
  })
})
