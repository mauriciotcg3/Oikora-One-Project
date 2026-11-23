// OIK-SPEC-0001 T-40, caso 4: auditoria filtra por período e evento e não mostra valores de campo.
import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw/http'
import { describe, expect, it } from 'vitest'
import { renderizarComProvedores } from '../../../test/renderizar'
import { servidor } from '../../../test/servidor'
import { auditoriaPtBR, TelaAuditoria } from '../index'

const API = '*/api/v1'

const linha = {
  ocorrido_em: '2026-11-20T13:00:00-03:00',
  evento: 'pessoa.atualizada',
  ator: { acesso: 'ac-1', plataforma: false },
  alvo: { tipo: 'pessoa', id: 'pe-1' },
  igreja: { uuid: 'ig-x', nome: 'Igreja X' },
  campos: ['profissao', 'nascimento'],
  // A API nunca manda valores; mesmo um metadado técnico não aparece na tela.
  metadados: { segredo: 'valor-que-nao-aparece' },
  canal: 'painel',
  request_id: 'req-1',
}

describe('consulta da auditoria', () => {
  it('filtra por período e evento e mostra só os nomes dos campos', async () => {
    const consultas: string[] = []
    servidor.use(http.get(`${API}/painel/auditoria`, ({ request }) => {
      consultas.push(new URL(request.url).search)
      return HttpResponse.json({ data: [linha], meta: { pagina: 1, por_pagina: 25, total: 1 } })
    }))
    const usuario = userEvent.setup()
    renderizarComProvedores(<TelaAuditoria />, { auditoria: auditoriaPtBR })

    expect(await screen.findByRole('cell', { name: 'Ficha atualizada' })).toBeInTheDocument()
    await usuario.type(screen.getByLabelText('De'), '2026-11-01')
    await usuario.type(screen.getByLabelText('Até'), '2026-11-30')
    await usuario.type(screen.getByLabelText('Evento'), 'pessoa.atualizada')
    await usuario.click(screen.getByRole('button', { name: 'Filtrar' }))

    expect(await screen.findByText('1 registro')).toBeInTheDocument()
    const ultima = new URLSearchParams(consultas.at(-1))
    expect(ultima.get('de')).toBe('2026-11-01')
    expect(ultima.get('ate')).toBe('2026-11-30')
    expect(ultima.get('evento')).toBe('pessoa.atualizada')
    expect(screen.getByRole('cell', { name: 'profissao, nascimento' })).toBeInTheDocument()
    expect(screen.queryByText(/valor-que-nao-aparece/)).not.toBeInTheDocument()
  })
})
