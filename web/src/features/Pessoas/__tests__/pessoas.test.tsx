import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw/http'
import { describe, expect, it, vi } from 'vitest'
import { renderizarComProvedores } from '../../../test/renderizar'
import { servidor } from '../../../test/servidor'
import { TelaFichaPessoa, TelaOperacoesPessoas, TelaPessoas } from '../index'

describe('pessoas', () => {
  it('lista pessoas e oferece a abertura da ficha', async () => {
    servidor.use(http.get('*/api/v1/painel/pessoas', () => HttpResponse.json({ data: [{ uuid: 'p1', nome: 'Ana Lima', igreja: { uuid: 'i1', nome: 'Central' }, vinculo: 'membro', situacao: 'ativa' }], meta: { pagina: 1, por_pagina: 25, total: 1 } })))
    renderizarComProvedores(<TelaPessoas />, {})
    expect(await screen.findByRole('cell', { name: 'Ana Lima' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Abrir ficha' })).toHaveAttribute('href', '/painel/pessoas/p1')
  })

  it('não inventa seção sensível que a API omitiu e usa a completude recebida', async () => {
    servidor.use(http.get('*/api/v1/painel/pessoas/p1', () => HttpResponse.json({ data: { uuid: 'p1', nome: 'Ana Lima', vinculo: 'membro', situacao: 'ativa', completude: { percentual: 75 }, campos: { 'pessoais.nome': 'Ana Lima', 'contatos.email': 'ana@example.test' } }, meta: { acoes: [], categorias: ['contatos'] } })))
    renderizarComProvedores(<TelaFichaPessoa uuid="p1" />, {})
    expect(await screen.findByText('Completude da ficha: 75%')).toBeInTheDocument()
    expect(screen.queryByRole('tab', { name: /saúde/i })).not.toBeInTheDocument()
  })

  it('executa somente as ações de duplicidade liberadas pela API', async () => {
    let corpo: unknown
    servidor.use(
      http.get('*/api/v1/painel/duplicados', () => HttpResponse.json({ data: [{ pessoas: [{ uuid: 'p1', nome: 'Ana', nascimento: '1990-01-01', vinculo: 'membro', igreja: { nome: 'Central' } }, { uuid: 'p2', nome: 'Ana', nascimento: '1990-01-01', vinculo: 'membro', igreja: { nome: 'Norte' } }], criterios: ['nome_nascimento'], meta: { acoes: ['nao_duplicadas'] } }], meta: { pagina: 1, por_pagina: 25, total: 1 } })),
      http.post('*/api/v1/painel/duplicados/nao-duplicados', async ({ request }) => { corpo = await request.json(); return new HttpResponse(null, { status: 204 }) }),
    )
    const usuario = userEvent.setup()
    renderizarComProvedores(<TelaOperacoesPessoas />, {})
    await usuario.click(await screen.findByRole('button', { name: 'São pessoas diferentes' }))
    expect(corpo).toEqual({ pessoa: 'p1', outra: 'p2' })
    expect(screen.queryByRole('button', { name: 'Mesclar com os valores escolhidos' })).not.toBeInTheDocument()
  })

  it('altera o vínculo somente para o próximo passo e exige um motivo da lista', async () => {
    let corpo: unknown
    servidor.use(
      http.get('*/api/v1/painel/pessoas/p1', () => HttpResponse.json({ data: { uuid: 'p1', vinculo: 'visitante', situacao: 'ativa', completude: { percentual: 50 }, campos: { nome: 'Ana Lima' } }, meta: { acoes: ['editar'], categorias: [] } })),
      http.get('*/api/v1/painel/listas/motivo_vinculo', () => HttpResponse.json({ data: [{ uuid: 'm1', nome: 'Decisão pessoal', ordem: 1, ativo: true }] })),
      http.post('*/api/v1/painel/pessoas/p1/vinculo', async ({ request }) => { corpo = await request.json(); return HttpResponse.json({ data: { uuid: 'p1' }, meta: { acoes: ['editar'], categorias: [] } }) }),
    )
    const usuario = userEvent.setup()
    renderizarComProvedores(<TelaFichaPessoa uuid="p1" />, {})
    await usuario.click(await screen.findByRole('button', { name: 'Avançar para Frequentador' }))
    await usuario.click(await screen.findByRole('combobox', { name: 'Motivo' }))
    await usuario.click(await screen.findByRole('option', { name: 'Decisão pessoal' }))
    await usuario.click(screen.getByRole('button', { name: 'Confirmar' }))
    expect(corpo).toEqual({ vinculo: 'frequentador', motivo: 'm1' })
  })

  it('envia a escolha dos campos divergentes e oferece desfazer a mesclagem', async () => {
    let corpo: unknown
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    servidor.use(
      http.get('*/api/v1/painel/duplicados', () => HttpResponse.json({ data: [{ pessoas: [{ uuid: 'p1', nome: 'Ana Lima', nascimento: '1990-01-01', vinculo: 'membro', igreja: { nome: 'Central' } }, { uuid: 'p2', nome: 'Ana Souza', nascimento: '1990-01-01', vinculo: 'membro', igreja: { nome: 'Norte' } }], criterios: ['nome_nascimento'], meta: { acoes: ['mesclar', 'nao_duplicadas'] } }], meta: { pagina: 1, por_pagina: 25, total: 1 } })),
      http.get('*/api/v1/painel/pessoas/p1', () => HttpResponse.json({ data: { uuid: 'p1', campos: { nome: 'Ana Lima', profissao: 'Professora' } }, meta: { acoes: ['mesclar'], categorias: [] } })),
      http.get('*/api/v1/painel/pessoas/p2', () => HttpResponse.json({ data: { uuid: 'p2', campos: { nome: 'Ana Souza', profissao: 'Engenheira' } }, meta: { acoes: ['mesclar'], categorias: [] } })),
      http.post('*/api/v1/painel/mesclagens', async ({ request }) => { corpo = await request.json(); return HttpResponse.json({ operacao: 'op1' }, { status: 201 }) }),
    )
    const usuario = userEvent.setup()
    renderizarComProvedores(<TelaOperacoesPessoas />, {})
    await usuario.click(await screen.findByLabelText('Ana Souza: Engenheira'))
    await usuario.click(screen.getByRole('button', { name: 'Mesclar com os valores escolhidos' }))
    expect(corpo).toEqual({ mantida: 'p1', absorvida: 'p2', valores: { profissao: 'absorvida' } })
    expect(await screen.findByRole('button', { name: 'Desfazer' })).toBeInTheDocument()
  })
})
