// OIK-SPEC-0001 T-37, caso 3 na tela: 403 `elevacao_necessaria` abre o diálogo sobre a tela atual e
// o pedido é repetido depois de elevar, sem perder o que estava no formulário.
import { QueryClient } from '@tanstack/react-query'
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw/http'
import { useState, type ReactNode } from 'react'
import { describe, expect, it } from 'vitest'
import { identidadePtBR } from '../../../features/Identidade'
import { requisitar } from '../../../shared/http'
import { renderizarComProvedores } from '../../../test/renderizar'
import { servidor } from '../../../test/servidor'
import { SessaoPainel } from '../SessaoPainel'

function FormularioPapel(): ReactNode {
  const [nome, setNome] = useState('')
  const [salvo, setSalvo] = useState(false)
  const salvar = async (): Promise<void> => {
    await requisitar('POST', '/painel/papeis', { nome })
    setSalvo(true)
  }
  return (
    <>
      <label>
        Nome do papel
        <input value={nome} onChange={(evento) => setNome(evento.target.value)} />
      </label>
      <button onClick={() => void salvar()}>Salvar papel</button>
      {salvo && <p>Papel salvo</p>}
    </>
  )
}

describe('sessão do painel', () => {
  it('eleva pelo diálogo e repete o pedido sem perder o formulário', async () => {
    const corpos: unknown[] = []
    const elevacoes: unknown[] = []
    servidor.use(
      http.post('*/api/v1/painel/papeis', async ({ request }) => {
        corpos.push(await request.json())
        return elevacoes.length === 0
          ? HttpResponse.json({ codigo: 'elevacao_necessaria' }, { status: 403 })
          : HttpResponse.json({ data: { nome: 'Tesouraria' } }, { status: 201 })
      }),
      http.post('*/api/v1/painel/elevacao', async ({ request }) => {
        elevacoes.push(await request.json())
        return new HttpResponse(null, { status: 204 })
      }),
    )
    const usuario = userEvent.setup()
    renderizarComProvedores(
      <SessaoPainel clienteConsulta={new QueryClient()}>
        <FormularioPapel />
      </SessaoPainel>,
      { identidade: identidadePtBR },
    )

    await usuario.type(screen.getByLabelText('Nome do papel'), 'Tesouraria')
    await usuario.click(screen.getByRole('button', { name: 'Salvar papel' }))

    const dialogo = await screen.findByRole('dialog', { name: 'Confirme que é você' })
    expect(screen.getByLabelText('Nome do papel', { selector: 'input' })).toHaveValue('Tesouraria')
    await usuario.type(screen.getByLabelText(/Senha/), 'senha-correta-123')
    await usuario.click(screen.getByRole('button', { name: 'Continuar' }))

    expect(await screen.findByText('Papel salvo')).toBeInTheDocument()
    await waitFor(() => expect(dialogo).not.toBeInTheDocument())
    expect(elevacoes).toEqual([{ senha: 'senha-correta-123' }])
    expect(corpos).toEqual([{ nome: 'Tesouraria' }, { nome: 'Tesouraria' }])
  })
})
