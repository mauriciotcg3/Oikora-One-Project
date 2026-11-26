// OIK-SPEC-0001 W9 (T-41 a T-44): telas de Pessoas no UI Standard — textos traduzidos, navegação pelo
// roteador, confirmação antes de mesclar e retorno visível de cada ação.
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw/http'
import { beforeAll, describe, expect, it } from 'vitest'
import { renderizarNaRota } from '../../../test/renderizar'
import { servidor } from '../../../test/servidor'
import { pessoasPtBR, TelaBuscaPessoas, TelaDatasPessoas, TelaFichaPessoa, TelaOperacoesPessoas, TelaPessoas } from '../index'

const TEXTOS = { pessoas: pessoasPtBR }
const PAR = (acoes: string[]) => ({
  data: [{
    pessoas: [
      { uuid: 'p1', nome: 'Ana Lima', nascimento: '1990-01-01', vinculo: 'membro', igreja: { nome: 'Central' } },
      { uuid: 'p2', nome: 'Ana Souza', nascimento: '1990-01-01', vinculo: 'membro', igreja: { nome: 'Norte' } },
    ],
    criterios: ['nome_nascimento'],
    meta: { acoes },
  }],
  meta: { pagina: 1, por_pagina: 25, total: 1 },
})

beforeAll(async () => {
  await import('../../../shared/ui/TabelaDeRegistros/GradeDeRegistros')
}, 60_000)

describe('pessoas', { timeout: 15_000 }, () => {
  it('lista pessoas na tabela paginada e abre a ficha pelo roteador', async () => {
    servidor.use(
      http.get('*/api/v1/painel/igrejas', () => HttpResponse.json({ data: [{ uuid: 'i1', nome: 'Central' }] })),
      http.get('*/api/v1/painel/pessoas', () => HttpResponse.json({ data: [{ uuid: 'p1', nome: 'Ana Lima', igreja: { uuid: 'i1', nome: 'Central' }, vinculo: 'membro', situacao: 'ativa' }], meta: { pagina: 1, por_pagina: 25, total: 1 } })),
    )
    const usuario = userEvent.setup()
    const { roteador } = renderizarNaRota(<TelaPessoas />, TEXTOS, '/pessoas')

    expect(await screen.findByText('Ana Lima', {}, { timeout: 5000 })).toBeInTheDocument()
    expect(screen.getByText('Membro')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Cadastrar pessoa' })).toHaveAttribute('href', '/pessoas/nova')
    await usuario.click(screen.getByRole('button', { name: 'Abrir ficha de Ana Lima' }))
    await waitFor(() => expect(roteador.state.location.pathname).toBe('/pessoas/p1'))
  })

  it('mostra só as seções que a API devolveu, com rótulos legíveis, sim/não e a completude recebida', async () => {
    servidor.use(http.get('*/api/v1/painel/pessoas/p1', () => HttpResponse.json({
      data: { uuid: 'p1', vinculo: 'membro', situacao: 'ativa', completude: { percentual: 75, faltando: ['cpf'] }, campos: { nome: 'Ana Lima', estado_civil: null, aceitou_jesus: true } },
      meta: { acoes: [], categorias: ['fe'] },
    })))
    const usuario = userEvent.setup()
    renderizarNaRota(<TelaFichaPessoa uuid="p1" />, TEXTOS)

    expect(await screen.findByRole('heading', { name: 'Ana Lima' })).toBeInTheDocument()
    expect(screen.getByRole('progressbar', { name: 'Completude da ficha' })).toHaveAttribute('aria-valuetext', 'Completude da ficha: 75%')
    expect(screen.getByText('Falta preencher: CPF')).toBeInTheDocument()
    expect(screen.getByText('Estado civil')).toBeInTheDocument()
    expect(screen.queryByText('estado_civil')).not.toBeInTheDocument()
    expect(screen.queryByRole('tab', { name: /saúde/i })).not.toBeInTheDocument()
    await usuario.click(screen.getByRole('tab', { name: 'Dados eclesiásticos' }))
    expect(await screen.findByText('Aceitou Jesus')).toBeInTheDocument()
    expect(screen.getByText('Sim')).toBeInTheDocument()
  })

  it('marca pessoas diferentes só quando a API libera e confirma o resultado na tela', async () => {
    let corpo: unknown
    servidor.use(
      http.get('*/api/v1/painel/duplicados', () => HttpResponse.json(PAR(['nao_duplicadas']))),
      http.post('*/api/v1/painel/duplicados/nao-duplicados', async ({ request }) => { corpo = await request.json(); return new HttpResponse(null, { status: 204 }) }),
    )
    const usuario = userEvent.setup()
    renderizarNaRota(<TelaOperacoesPessoas />, TEXTOS)

    await usuario.click(await screen.findByRole('button', { name: 'São pessoas diferentes' }))
    expect(corpo).toEqual({ pessoa: 'p1', outra: 'p2' })
    expect(await screen.findByText('Ana Lima e Ana Souza foram marcadas como pessoas diferentes.')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Mesclar com os valores escolhidos' })).not.toBeInTheDocument()
  })

  it('altera o vínculo somente para o próximo passo, exige um motivo da lista e confirma a mudança', async () => {
    let corpo: unknown
    servidor.use(
      http.get('*/api/v1/painel/pessoas/p1', () => HttpResponse.json({ data: { uuid: 'p1', vinculo: 'visitante', situacao: 'ativa', completude: { percentual: 50 }, campos: { nome: 'Ana Lima' } }, meta: { acoes: ['editar'], categorias: [] } })),
      http.get('*/api/v1/painel/listas/motivo_vinculo', () => HttpResponse.json({ data: [{ uuid: 'm1', nome: 'Decisão pessoal', ordem: 1, ativo: true }] })),
      http.post('*/api/v1/painel/pessoas/p1/vinculo', async ({ request }) => { corpo = await request.json(); return HttpResponse.json({ data: { uuid: 'p1' }, meta: { acoes: ['editar'], categorias: [] } }) }),
    )
    const usuario = userEvent.setup()
    renderizarNaRota(<TelaFichaPessoa uuid="p1" />, TEXTOS)

    await usuario.click(await screen.findByRole('button', { name: 'Avançar para Frequentador' }))
    const dialogo = await screen.findByRole('dialog', { name: 'Alterar vínculo para Frequentador' })
    expect(within(dialogo).getByRole('button', { name: 'Confirmar' })).toBeDisabled()
    await usuario.click(within(dialogo).getByRole('combobox', { name: 'Motivo' }))
    await usuario.click(await screen.findByRole('option', { name: 'Decisão pessoal' }))
    await usuario.click(within(dialogo).getByRole('button', { name: 'Confirmar' }))
    expect(corpo).toEqual({ vinculo: 'frequentador', motivo: 'm1' })
    expect(await screen.findByText('Vínculo alterado para Frequentador.')).toBeInTheDocument()
  })

  it('pede confirmação antes de mesclar, envia os valores escolhidos e mantém o "Desfazer" depois de recarregar os pares', async () => {
    let corpo: unknown
    let pares = PAR(['mesclar', 'nao_duplicadas'])
    servidor.use(
      http.get('*/api/v1/painel/duplicados', () => HttpResponse.json(pares)),
      http.get('*/api/v1/painel/pessoas/p1', () => HttpResponse.json({ data: { uuid: 'p1', campos: { nome: 'Ana Lima', profissao: 'Professora' } }, meta: { acoes: ['mesclar'], categorias: [] } })),
      http.get('*/api/v1/painel/pessoas/p2', () => HttpResponse.json({ data: { uuid: 'p2', campos: { nome: 'Ana Souza', profissao: 'Engenheira' } }, meta: { acoes: ['mesclar'], categorias: [] } })),
      http.post('*/api/v1/painel/mesclagens', async ({ request }) => {
        corpo = await request.json()
        pares = { data: [], meta: { pagina: 1, por_pagina: 25, total: 0 } }
        return HttpResponse.json({ operacao: 'op1' }, { status: 201 })
      }),
    )
    const usuario = userEvent.setup()
    renderizarNaRota(<TelaOperacoesPessoas />, TEXTOS)

    await usuario.click(await screen.findByLabelText('Ana Souza: Engenheira'))
    await usuario.click(screen.getByRole('button', { name: 'Mesclar com os valores escolhidos' }))
    const confirmacao = await screen.findByRole('alertdialog', { name: 'Mesclar as duas fichas?' })
    expect(corpo).toBeUndefined()
    await usuario.click(within(confirmacao).getByRole('button', { name: 'Mesclar' }))

    await waitFor(() => expect(corpo).toEqual({ mantida: 'p1', absorvida: 'p2', valores: { profissao: 'absorvida' } }))
    expect(await screen.findByText('Nenhum possível duplicado')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Desfazer mesclagem' })).toBeInTheDocument()
  })

  it('pede transferência escolhendo a igreja na lista, sem digitar identificador, e confirma o envio', async () => {
    let corpo: unknown
    servidor.use(
      http.get('*/api/v1/painel/busca', () => HttpResponse.json({ data: [{ uuid: 'p9', nome: 'Rute Alves', igreja: { uuid: 'i2', nome: 'Norte' }, vinculo: 'membro', meta: { acoes: [] } }] })),
      http.get('*/api/v1/painel/igrejas', () => HttpResponse.json({ data: [{ uuid: 'i1', nome: 'Central' }, { uuid: 'i2', nome: 'Norte' }] })),
      http.post('*/api/v1/painel/transferencias', async ({ request }) => { corpo = await request.json(); return HttpResponse.json({ data: { uuid: 't1' } }, { status: 201 }) }),
    )
    const usuario = userEvent.setup()
    renderizarNaRota(<TelaBuscaPessoas />, TEXTOS)

    await usuario.type(await screen.findByRole('searchbox', { name: 'Nome, CPF, e-mail ou telefone' }), 'Rute')
    await usuario.click(await screen.findByRole('button', { name: 'Pedir transferência' }))
    const dialogo = await screen.findByRole('dialog', { name: 'Pedir transferência de Rute Alves' })
    await usuario.click(within(dialogo).getByRole('combobox', { name: 'Igreja de destino' }))
    expect(screen.queryByRole('option', { name: 'Norte' })).not.toBeInTheDocument()
    await usuario.click(await screen.findByRole('option', { name: 'Central' }))
    await usuario.click(within(dialogo).getByRole('button', { name: 'Enviar pedido' }))

    expect(corpo).toEqual({ pessoa: 'p9', igreja_destino: 'i1' })
    expect(await screen.findByText('Pedido de transferência enviado')).toBeInTheDocument()
  })

  it('lista as datas da mesma pessoa uma vez por tipo, com nome do tipo e data legível', async () => {
    servidor.use(http.get('*/api/v1/painel/aniversarios', () => HttpResponse.json({
      data: [
        { uuid: 'p1', nome: 'Ana Lima', tipo: 'nascimento', data: '1990-11-26' },
        { uuid: 'p1', nome: 'Ana Lima', tipo: 'batismo', data: '2010-11-28' },
      ],
      meta: { criterio: 'mes' },
    })))
    renderizarNaRota(<TelaDatasPessoas />, TEXTOS, '/acompanhamentos?aba=datas')

    expect(await screen.findByText('Aniversário de batismo')).toBeInTheDocument()
    expect(screen.getByText('Aniversário')).toBeInTheDocument()
    expect(screen.getAllByRole('link', { name: 'Ana Lima' })).toHaveLength(2)
    expect(screen.getByText('28/11/2010')).toBeInTheDocument()
    expect(screen.queryByText(/p1/)).not.toBeInTheDocument()
  })
})
