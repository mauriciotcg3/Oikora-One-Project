// OIK-SPEC-0001 W9 (T-39): configuração da organização no UI Standard.
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw/http'
import { describe, expect, it } from 'vitest'
import { renderizarNaRota } from '../../../test/renderizar'
import { servidor } from '../../../test/servidor'
import { organizacaoPtBR, TelaConfiguracao } from '../index'

const TEXTOS = { organizacao: organizacaoPtBR }
const ORGANIZACAO = { uuid: 'o1', nome: 'Oikora', fuso: 'America/Sao_Paulo', cor_primaria: '#1565C0', modo_matricula: 'automatica', limite_whatsapp_mensal: 100, email_contato: 'admin@oikora.test', logo_url: null }

describe('configuração da organização', () => {
  it('lista igrejas, cria com retorno na tela e não oferece exclusão', async () => {
    let criada: unknown
    servidor.use(
      http.get('*/api/v1/painel/organizacao', () => HttpResponse.json({ data: ORGANIZACAO })),
      http.get('*/api/v1/painel/igrejas', () => HttpResponse.json({ data: [{ uuid: 'i1', nome: 'Igreja Central', regional: null }] })),
      http.post('*/api/v1/painel/igrejas', async ({ request }) => {
        criada = await request.json()
        return HttpResponse.json({ data: { uuid: 'i2', nome: (criada as { nome: string }).nome, regional: null } }, { status: 201 })
      }),
    )
    const usuario = userEvent.setup()
    const { roteador } = renderizarNaRota(<TelaConfiguracao />, TEXTOS, '/configuracao')

    await usuario.click(await screen.findByRole('tab', { name: 'Igrejas' }))
    await waitFor(() => expect(roteador.state.location.search).toEqual({ aba: 'igrejas' }))
    expect(await screen.findByDisplayValue('Igreja Central')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /excluir/i })).not.toBeInTheDocument()
    await usuario.type(screen.getByLabelText('Nome da nova igreja'), 'Igreja Norte')
    await usuario.click(screen.getByRole('button', { name: 'Adicionar' }))

    await waitFor(() => expect(criada).toEqual({ nome: 'Igreja Norte' }))
    expect(await screen.findByText('Igreja Norte foi adicionada.')).toBeInTheDocument()
  })

  it('escolhe campos obrigatórios pelo nome legível e salva a lista do vínculo', async () => {
    let corpo: unknown
    servidor.use(
      http.get('*/api/v1/painel/organizacao', () => HttpResponse.json({ data: ORGANIZACAO })),
      http.get('*/api/v1/painel/configuracao/obrigatorios', () => HttpResponse.json({ data: { visitante: ['nome'], frequentador: [], membro: [] } })),
      http.put('*/api/v1/painel/configuracao/obrigatorios', async ({ request }) => { corpo = await request.json(); return HttpResponse.json({ data: {} }) }),
    )
    const usuario = userEvent.setup()
    renderizarNaRota(<TelaConfiguracao />, TEXTOS, '/configuracao?aba=obrigatorios')

    expect(await screen.findByRole('checkbox', { name: 'Nome' })).toBeChecked()
    await usuario.click(screen.getByRole('checkbox', { name: 'Celular' }))
    await usuario.click(screen.getByRole('button', { name: 'Salvar campos obrigatórios' }))

    await waitFor(() => expect(corpo).toEqual({ vinculo: 'visitante', campos: ['nome', 'celular'] }))
    expect(await screen.findByText('Campos obrigatórios de Visitante salvos.')).toBeInTheDocument()
  })

  it('sobe um valor trocando de lugar com o vizinho, sem deixar posições empatadas', async () => {
    const edicoes: Array<{ item: string; corpo: unknown }> = []
    servidor.use(
      http.get('*/api/v1/painel/organizacao', () => HttpResponse.json({ data: ORGANIZACAO })),
      http.get('*/api/v1/painel/listas/como_chegou', () => HttpResponse.json({ data: [
        { uuid: 'a', nome: 'Amigo', ordem: 1, ativo: true },
        { uuid: 'b', nome: 'Internet', ordem: 2, ativo: true },
      ] })),
      http.put('*/api/v1/painel/listas/como_chegou/:item', async ({ request, params }) => {
        edicoes.push({ item: String(params.item), corpo: await request.json() })
        return HttpResponse.json({ data: {} })
      }),
    )
    const usuario = userEvent.setup()
    renderizarNaRota(<TelaConfiguracao />, TEXTOS, '/configuracao?aba=listas')

    await usuario.click(await screen.findByRole('button', { name: 'Subir “Internet”' }))
    await waitFor(() => expect(edicoes).toEqual([{ item: 'b', corpo: { ordem: 1 } }, { item: 'a', corpo: { ordem: 2 } }]))
  })
})
