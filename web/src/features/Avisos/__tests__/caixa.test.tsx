import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw/http'
import { describe, expect, it } from 'vitest'
import { renderizarComProvedores } from '../../../test/renderizar'
import { servidor } from '../../../test/servidor'
import { TelaAvisos } from '../index'

describe('caixa de avisos', () => {
  it('marca todos como lidos e mantém tipos obrigatórios bloqueados', async () => {
    let marcados = false
    servidor.use(
      http.get('*/api/v1/avisos/', () => HttpResponse.json({ data: marcados ? [] : [{ id: 'a1', titulo: 'Convite', texto: 'Você recebeu um convite.', acao: 'Abrir', destino: '/painel/', lido_em: null, criado_em: '2026-11-25T12:00:00Z' }], meta: { nao_lidos: marcados ? 0 : 1 } })),
      http.post('*/api/v1/avisos/lidos', () => { marcados = true; return HttpResponse.json({ marcados: 1, nao_lidos: 0 }) }),
      http.get('*/api/v1/conta/preferencias-aviso', () => HttpResponse.json({ data: [{ tipo: 'acesso.convite', categoria: 'acesso', obrigatorio: true, permite_escolher_canal: false, canais: [{ canal: 'email', ligado: true }] }] })),
    )
    const usuario = userEvent.setup()
    renderizarComProvedores(<TelaAvisos />, {})

    expect(await screen.findByRole('heading', { name: 'Avisos (1 não lidos)' })).toBeInTheDocument()
    expect(screen.getByRole('switch', { name: 'email para acesso.convite' })).toBeDisabled()
    await usuario.click(screen.getByRole('button', { name: 'Marcar todos como lidos' }))
    await waitFor(() => expect(screen.getByRole('heading', { name: 'Avisos (0 não lidos)' })).toBeInTheDocument())
  })
})
