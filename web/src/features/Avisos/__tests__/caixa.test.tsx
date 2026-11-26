// OIK-SPEC-0001 W9 (T-39, T-44): caixa de avisos e WhatsApp no UI Standard.
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw/http'
import { describe, expect, it } from 'vitest'
import { renderizarNaRota } from '../../../test/renderizar'
import { servidor } from '../../../test/servidor'
import { avisosPtBR, TelaAvisos, TelaWhatsapp } from '../index'

const TEXTOS = { avisos: avisosPtBR }

describe('caixa de avisos', () => {
  it('marca todos como lidos, confirma na tela e mantém tipos obrigatórios bloqueados com nome legível', async () => {
    let marcados = false
    servidor.use(
      http.get('*/api/v1/avisos/', () => HttpResponse.json({ data: marcados ? [] : [{ id: 'a1', titulo: 'Convite', texto: 'Você recebeu um convite.', acao: 'Abrir', destino: '/painel/', lido_em: null, criado_em: '2026-11-25T12:00:00Z' }], meta: { nao_lidos: marcados ? 0 : 1 } })),
      http.post('*/api/v1/avisos/lidos', () => { marcados = true; return HttpResponse.json({ marcados: 1, nao_lidos: 0 }) }),
      http.get('*/api/v1/conta/preferencias-aviso', () => HttpResponse.json({ data: [{ tipo: 'acesso.convite', categoria: 'acesso', obrigatorio: true, permite_escolher_canal: false, canais: [{ canal: 'email', ligado: true }] }] })),
    )
    const usuario = userEvent.setup()
    renderizarNaRota(<TelaAvisos />, TEXTOS)

    expect(await screen.findByRole('heading', { name: '1 aviso novo' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Abrir' })).toHaveAttribute('href', '/')
    expect(await screen.findByRole('switch', { name: 'E-mail para Convite de acesso' })).toBeDisabled()
    expect(screen.queryByText('acesso.convite')).not.toBeInTheDocument()
    await usuario.click(screen.getByRole('button', { name: 'Marcar todos como lidos' }))
    await waitFor(() => expect(screen.getByRole('heading', { name: 'Nenhum aviso novo' })).toBeInTheDocument())
    expect(await screen.findByText('Todos os avisos foram marcados como lidos.')).toBeInTheDocument()
  })
})

describe('WhatsApp', () => {
  it('só desconecta o número depois da confirmação e mostra o resultado', async () => {
    let desligado = false
    servidor.use(http.delete('*/api/v1/painel/avisos/whatsapp', () => { desligado = true; return new HttpResponse(null, { status: 204 }) }))
    const usuario = userEvent.setup()
    renderizarNaRota(<TelaWhatsapp />, TEXTOS)

    await usuario.click(await screen.findByRole('button', { name: 'Desconectar número' }))
    const confirmacao = await screen.findByRole('alertdialog', { name: 'Desconectar o número do WhatsApp?' })
    expect(desligado).toBe(false)
    await usuario.click(within(confirmacao).getByRole('button', { name: 'Desconectar número' }))

    expect(await screen.findByText('Número desconectado')).toBeInTheDocument()
    expect(desligado).toBe(true)
  })
})
