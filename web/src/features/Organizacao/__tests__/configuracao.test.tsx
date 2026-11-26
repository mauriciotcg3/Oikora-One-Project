import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw/http'
import { describe, expect, it } from 'vitest'
import { renderizarComProvedores } from '../../../test/renderizar'
import { servidor } from '../../../test/servidor'
import { TelaConfiguracao } from '../index'

describe('configuração da organização', () => {
  it('lista igrejas, permite criar e não oferece exclusão', async () => {
    servidor.use(
      http.get('*/api/v1/painel/organizacao', () => HttpResponse.json({ data: { uuid: 'o1', nome: 'Oikora', fuso: 'America/Sao_Paulo', cor_primaria: '#1565C0', modo_matricula: 'automatica', limite_whatsapp_mensal: 100, email_contato: 'admin@oikora.test', logo_url: null } })),
      http.get('*/api/v1/painel/igrejas', () => HttpResponse.json({ data: [{ uuid: 'i1', nome: 'Igreja Central', regional: null }] })),
      http.post('*/api/v1/painel/igrejas', async ({ request }) => HttpResponse.json({ data: { uuid: 'i2', nome: String((await request.json() as { nome: string }).nome), regional: null } }, { status: 201 })),
    )
    const usuario = userEvent.setup()
    renderizarComProvedores(<TelaConfiguracao />, {})

    await usuario.click(await screen.findByRole('tab', { name: 'Igrejas' }))
    expect(await screen.findByDisplayValue('Igreja Central')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /excluir/i })).not.toBeInTheDocument()
    await usuario.type(screen.getByLabelText('Nome da nova igreja'), 'Igreja Norte')
    await usuario.click(screen.getByRole('button', { name: 'Adicionar' }))
  })
})
