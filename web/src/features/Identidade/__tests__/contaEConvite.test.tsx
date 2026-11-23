// OIK-SPEC-0001 T-38, casos 2 a 5: cadastro do 2FA com os 10 códigos, aceite de convite, diálogo de
// elevação e seletor de organização. API simulada pelo MSW com o contrato da API (plan.md §7).
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw/http'
import { describe, expect, it, vi } from 'vitest'
import { renderizarComProvedores } from '../../../test/renderizar'
import { servidor } from '../../../test/servidor'
import { CadastroDoisFatores, DialogoElevacao, identidadePtBR, PaginaConvite, SeletorOrganizacao } from '../index'

const API = '*/api/v1'
const recursos = { identidade: identidadePtBR }
const csrf = http.get('*/sanctum/csrf-cookie', () => new HttpResponse(null, { status: 204 }))
const semSessao = http.get(`${API}/me`, () => HttpResponse.json({ message: 'Unauthenticated.' }, { status: 401 }))
const codigos = Array.from({ length: 10 }, (_, i) => `COD${i}-ABCD`)

const meCom = (organizacoes: { id: string; nome: string }[], ativa = organizacoes[0]) =>
  HttpResponse.json({ data: { organizacao_ativa: ativa, organizacoes, dois_fatores: { canal: 'email', obrigatorio: true } } })

describe('cadastro do 2FA', () => {
  it('o administrador sem 2FA confirma a senha, escolhe o canal e vê os 10 códigos uma vez', async () => {
    const pedidos: unknown[] = []
    servidor.use(
      http.post(`${API}/conta/confirmar-senha`, async ({ request }) => {
        const { senha } = (await request.json()) as { senha: string }
        return senha === 'certa-123'
          ? new HttpResponse(null, { status: 204 })
          : HttpResponse.json({ message: 'x', errors: { senha: ['Senha incorreta.'] } }, { status: 422 })
      }),
      http.post(`${API}/conta/2fa`, async ({ request }) => {
        pedidos.push(await request.json())
        return HttpResponse.json({ canal: 'whatsapp', codigos_recuperacao: codigos })
      }),
    )
    const aoConcluir = vi.fn()
    const usuario = userEvent.setup()
    renderizarComProvedores(<CadastroDoisFatores aoConcluir={aoConcluir} />, recursos)

    await usuario.type(screen.getByLabelText(/Sua senha/), 'errada')
    await usuario.click(screen.getByRole('button', { name: 'Continuar' }))
    expect(await screen.findByText('Senha incorreta.')).toBeInTheDocument()

    await usuario.clear(screen.getByLabelText(/Sua senha/))
    await usuario.type(screen.getByLabelText(/Sua senha/), 'certa-123')
    await usuario.click(screen.getByRole('button', { name: 'Continuar' }))
    await usuario.click(await screen.findByLabelText('WhatsApp'))
    await usuario.click(screen.getByRole('button', { name: 'Ativar' }))

    const lista = await screen.findByRole('list', { name: 'Códigos de recuperação' })
    expect(within(lista).getAllByRole('listitem')).toHaveLength(10)
    expect(pedidos).toEqual([{ canal: 'whatsapp' }])

    const ir = screen.getByRole('button', { name: 'Ir para o painel' })
    expect(ir).toBeDisabled()
    await usuario.click(screen.getByLabelText('Guardei os códigos em lugar seguro'))
    await usuario.click(ir)
    expect(aoConcluir).toHaveBeenCalledOnce()
  })
})

describe('aceite de convite', () => {
  it('token inválido mostra "convite inválido ou expirado"', async () => {
    servidor.use(semSessao, http.get(`${API}/convites/:token`, () => HttpResponse.json({ codigo: 'convite_invalido' }, { status: 410 })))
    renderizarComProvedores(<PaginaConvite token={'x'.repeat(48)} linkLogin="/painel/login" />, recursos)

    expect(await screen.findByText(/Convite inválido ou expirado/)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Aceitar convite' })).not.toBeInTheDocument()
  })

  it('sem sessão pede a senha nova e aceita', async () => {
    let corpo: unknown
    servidor.use(
      csrf,
      semSessao,
      http.get(`${API}/convites/:token`, () => HttpResponse.json({
        organizacao: { nome: 'Igreja Central' }, pessoa: { nome: 'Joana' }, papel: { nome: 'Secretaria' }, expira_em: '2026-11-30T10:00:00Z',
      })),
      http.post(`${API}/convites/:token/aceitar`, async ({ request }) => {
        corpo = await request.json()
        return HttpResponse.json({ conta_nova: true }, { status: 201 })
      }),
    )
    const usuario = userEvent.setup()
    renderizarComProvedores(<PaginaConvite token={'a'.repeat(48)} linkLogin="/painel/login" />, recursos)

    expect(await screen.findByText('Convite para o painel de Igreja Central')).toBeInTheDocument()
    await usuario.type(screen.getByLabelText(/Nova senha/), 'curta')
    await usuario.type(screen.getByLabelText(/Repita a senha/), 'curta')
    await usuario.click(screen.getByRole('button', { name: 'Aceitar convite' }))
    expect(await screen.findByText(/pelo menos 8 caracteres/)).toBeInTheDocument()

    await usuario.clear(screen.getByLabelText(/Nova senha/))
    await usuario.clear(screen.getByLabelText(/Repita a senha/))
    await usuario.type(screen.getByLabelText(/Nova senha/), 'senha-forte-1')
    await usuario.type(screen.getByLabelText(/Repita a senha/), 'senha-forte-1')
    await usuario.click(screen.getByRole('button', { name: 'Aceitar convite' }))

    expect(await screen.findByText('Convite aceito.')).toBeInTheDocument()
    expect(corpo).toEqual({ senha: 'senha-forte-1', senha_confirmation: 'senha-forte-1' })
  })

  it('quem já tem conta com o contato é levado ao login', async () => {
    servidor.use(
      csrf,
      semSessao,
      http.get(`${API}/convites/:token`, () => HttpResponse.json({
        organizacao: { nome: 'Igreja Central' }, pessoa: { nome: 'Joana' }, papel: { nome: 'Secretaria' }, expira_em: '2026-11-30T10:00:00Z',
      })),
      http.post(`${API}/convites/:token/aceitar`, () => HttpResponse.json({ codigo: 'entre_para_aceitar' }, { status: 409 })),
    )
    const usuario = userEvent.setup()
    renderizarComProvedores(<PaginaConvite token={'b'.repeat(48)} linkLogin="/painel/login?voltar=/painel/convites/x" />, recursos)

    await usuario.type(await screen.findByLabelText(/Nova senha/), 'senha-forte-1')
    await usuario.type(screen.getByLabelText(/Repita a senha/), 'senha-forte-1')
    await usuario.click(screen.getByRole('button', { name: 'Aceitar convite' }))

    expect(await screen.findByText(/Entre nela para aceitar/)).toBeInTheDocument()
    expect(screen.getAllByRole('link', { name: 'Entrar' })[0]).toHaveAttribute('href', '/painel/login?voltar=/painel/convites/x')
  })
})

describe('diálogo de elevação', () => {
  it('aceita senha e código e fecha', async () => {
    let pedidos = 0
    servidor.use(http.post(`${API}/painel/elevacao`, async ({ request }) => {
      pedidos++
      const corpo = (await request.json()) as { senha: string; codigo?: string }
      if (corpo.codigo === undefined) {
        return HttpResponse.json({ codigo: 'dois_fatores_necessario', canal: 'email', destino: 'a•••@exemplo.com' }, { status: 403 })
      }
      return new HttpResponse(null, { status: 204 })
    }))
    const aoConcluir = vi.fn()
    const usuario = userEvent.setup()
    renderizarComProvedores(<DialogoElevacao aberto aoConcluir={aoConcluir} />, recursos)

    await usuario.type(screen.getByLabelText(/Senha/), 'minha-senha')
    await usuario.click(screen.getByRole('button', { name: 'Continuar' }))
    await usuario.type(await screen.findByLabelText(/Código/), '123456')
    await usuario.click(screen.getByRole('button', { name: 'Continuar' }))

    await waitFor(() => expect(aoConcluir).toHaveBeenCalledWith(true))
    expect(pedidos).toBe(2)
  })
})

describe('seletor de organização', () => {
  it('com dois acessos lista as organizações e troca a ativa', async () => {
    const organizacoes = [{ id: 'org-a', nome: 'Igreja A' }, { id: 'org-b', nome: 'Igreja B' }]
    let ativa = organizacoes[0]
    let escolhida: unknown
    servidor.use(
      http.get(`${API}/me`, () => meCom(organizacoes, ativa)),
      http.put(`${API}/me/organizacao-ativa`, async ({ request }) => {
        escolhida = await request.json()
        ativa = organizacoes[1]
        return new HttpResponse(null, { status: 204 })
      }),
    )
    const usuario = userEvent.setup()
    renderizarComProvedores(<SeletorOrganizacao />, recursos)

    const seletor = await screen.findByRole('combobox', { name: 'Organização' })
    expect(seletor).toHaveTextContent('Igreja A')
    await usuario.click(seletor)
    expect(screen.getAllByRole('option').map((o) => o.textContent)).toEqual(['Igreja A', 'Igreja B'])
    await usuario.click(screen.getByRole('option', { name: 'Igreja B' }))

    await waitFor(() => expect(screen.getByRole('combobox', { name: 'Organização' })).toHaveTextContent('Igreja B'))
    expect(escolhida).toEqual({ organizacao: 'org-b' })
  })

  it('com um acesso só, não aparece', async () => {
    servidor.use(http.get(`${API}/me`, () => meCom([{ id: 'org-a', nome: 'Igreja A' }])))
    const { container } = renderizarComProvedores(<SeletorOrganizacao />, recursos)

    await waitFor(() => expect(container).toBeEmptyDOMElement())
  })
})
