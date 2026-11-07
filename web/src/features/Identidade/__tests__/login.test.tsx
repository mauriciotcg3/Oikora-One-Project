// OIK-SPEC-0001 T-38, caso 1 (recorte do login): credencial inválida → mensagem genérica;
// com 2FA → pede o código. Inclui o que a tela aprovada no protótipo faz em volta disso.
// Contrato da API simulado (MSW) até a T-10/T-12 existirem.
import { screen, waitFor } from '@testing-library/react'
import userEvent, { type UserEvent } from '@testing-library/user-event'
import { http, HttpResponse } from 'msw/http'
import { describe, expect, it, vi, type Mock } from 'vitest'
import { renderizarComProvedores } from '../../../test/renderizar'
import { servidor } from '../../../test/servidor'
import { identidadePtBR, PaginaLogin } from '../index'

const API = '*/api/v1'
const csrf = http.get('*/sanctum/csrf-cookie', () => new HttpResponse(null, { status: 204 }))

const desafioPorEmail = http.post(`${API}/auth/login`, () =>
  HttpResponse.json(
    { codigo: 'dois_fatores_necessario', canal: 'email', destino: 'm•••@exemplo.com', request_id: 'req-2' },
    { status: 403 },
  ),
)

interface Cenario {
  usuario: UserEvent
  aoEntrar: Mock<() => void>
}

function renderizarLogin(): Cenario {
  const aoEntrar = vi.fn<() => void>()
  renderizarComProvedores(<PaginaLogin aoEntrar={aoEntrar} />, { identidade: identidadePtBR })
  return { usuario: userEvent.setup(), aoEntrar }
}

async function entrar(usuario: UserEvent, identificador: string, senha: string): Promise<void> {
  await usuario.type(screen.getByLabelText('E-mail ou celular'), identificador)
  await usuario.type(screen.getByLabelText('Senha'), senha)
  await usuario.click(screen.getByRole('button', { name: 'Entrar' }))
}

describe('Login do painel', () => {
  it('credencial inválida mostra a mensagem genérica, sem repetir o texto do servidor', async () => {
    servidor.use(
      csrf,
      http.post(`${API}/auth/login`, () =>
        HttpResponse.json(
          { message: 'These credentials do not match our records.', errors: { identificador: ['auth.failed'] } },
          { status: 422 },
        ),
      ),
    )
    const { usuario, aoEntrar } = renderizarLogin()

    await entrar(usuario, 'maria@exemplo.com', 'senha-errada')

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Os dados não conferem. Confira o e-mail ou celular e a senha e tente de novo.',
    )
    expect(screen.queryByText(/These credentials/)).not.toBeInTheDocument()
    expect(screen.getByLabelText('Senha')).toHaveValue('')
    expect(screen.getByRole('heading', { name: 'Entrar' })).toBeInTheDocument()
    expect(aoEntrar).not.toHaveBeenCalled()
  })

  it('com 2FA pede o código de 6 dígitos enviado ao contato', async () => {
    servidor.use(csrf, desafioPorEmail)
    const { usuario } = renderizarLogin()

    await entrar(usuario, 'maria@exemplo.com', 'oikora123')

    expect(await screen.findByRole('heading', { name: 'Confirme que é você' })).toBeInTheDocument()
    expect(screen.getByText('m•••@exemplo.com')).toBeInTheDocument()
    for (let digito = 1; digito <= 6; digito += 1) {
      expect(screen.getByLabelText(`Dígito ${digito}`)).toBeInTheDocument()
    }
    expect(screen.getByLabelText('Dígito 1')).toHaveFocus()
  })

  it('o código certo conclui o login e abre o painel', async () => {
    const corpos: unknown[] = []
    servidor.use(
      csrf,
      desafioPorEmail,
      http.post(`${API}/auth/2fa/confirmar`, async ({ request }) => {
        corpos.push(await request.json())
        return new HttpResponse(null, { status: 204 })
      }),
    )
    const { usuario, aoEntrar } = renderizarLogin()
    await entrar(usuario, 'maria@exemplo.com', 'oikora123')
    await screen.findByRole('heading', { name: 'Confirme que é você' })

    await usuario.keyboard('123456')

    expect(await screen.findByRole('heading', { name: 'Tudo certo' })).toBeInTheDocument()
    expect(corpos).toEqual([{ codigo: '123456' }])
    await waitFor(() => expect(aoEntrar).toHaveBeenCalledTimes(1), { timeout: 2000 })
  })

  it('o código errado mostra o erro e limpa os dígitos', async () => {
    servidor.use(
      csrf,
      desafioPorEmail,
      http.post(`${API}/auth/2fa/confirmar`, () =>
        HttpResponse.json({ message: 'invalid', errors: { codigo: ['invalid'] } }, { status: 422 }),
      ),
    )
    const { usuario, aoEntrar } = renderizarLogin()
    await entrar(usuario, 'maria@exemplo.com', 'oikora123')
    await screen.findByRole('heading', { name: 'Confirme que é você' })

    await usuario.keyboard('654321')

    expect(await screen.findByText('Código incorreto. Confira a mensagem e digite de novo.')).toBeInTheDocument()
    expect(screen.getByLabelText('Dígito 1')).toHaveValue('')
    expect(screen.getByLabelText('Dígito 1')).toHaveFocus()
    expect(aoEntrar).not.toHaveBeenCalled()
  })

  it('o código de recuperação também conclui o login', async () => {
    const corpos: unknown[] = []
    servidor.use(
      csrf,
      desafioPorEmail,
      http.post(`${API}/auth/2fa/confirmar`, async ({ request }) => {
        corpos.push(await request.json())
        return new HttpResponse(null, { status: 204 })
      }),
    )
    const { usuario } = renderizarLogin()
    await entrar(usuario, 'maria@exemplo.com', 'oikora123')
    await screen.findByRole('heading', { name: 'Confirme que é você' })

    await usuario.click(screen.getByRole('button', { name: 'Usar um código de recuperação' }))
    await usuario.type(screen.getByLabelText('Código'), 'abcd-1234')
    await usuario.click(screen.getByRole('button', { name: 'Confirmar' }))

    expect(await screen.findByRole('heading', { name: 'Tudo certo' })).toBeInTheDocument()
    expect(corpos).toEqual([{ codigo_recuperacao: 'ABCD-1234' }])
  })

  it('muitas tentativas mostram o aviso para esperar', async () => {
    servidor.use(
      csrf,
      http.post(`${API}/auth/login`, () =>
        HttpResponse.json({ codigo: 'muitas_tentativas', request_id: 'req-3' }, { status: 429 }),
      ),
    )
    const { usuario } = renderizarLogin()

    await entrar(usuario, 'maria@exemplo.com', 'qualquer')

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Foram muitas tentativas seguidas. Por segurança, espere alguns minutos e tente de novo.',
    )
  })

  it('campos vazios mostram o que falta e não chamam a API', async () => {
    let pedidos = 0
    servidor.use(
      csrf,
      http.post(`${API}/auth/login`, () => {
        pedidos += 1
        return new HttpResponse(null, { status: 204 })
      }),
    )
    const { usuario } = renderizarLogin()

    await usuario.click(screen.getByRole('button', { name: 'Entrar' }))

    expect(await screen.findByText('Digite seu e-mail ou celular.')).toBeInTheDocument()
    expect(screen.getByText('Digite sua senha.')).toBeInTheDocument()
    expect(screen.getByLabelText('E-mail ou celular')).toHaveFocus()
    expect(pedidos).toBe(0)
  })

  it('celular recebe a máscara e vai para a API em E.164', async () => {
    const corpos: unknown[] = []
    servidor.use(
      csrf,
      http.post(`${API}/auth/login`, async ({ request }) => {
        corpos.push(await request.json())
        return HttpResponse.json(
          { codigo: 'dois_fatores_necessario', canal: 'whatsapp', destino: '(11) •••••-4321' },
          { status: 403 },
        )
      }),
    )
    const { usuario } = renderizarLogin()

    await usuario.type(screen.getByLabelText('E-mail ou celular'), '11987654321')
    expect(screen.getByLabelText('E-mail ou celular')).toHaveValue('(11) 98765-4321')
    await usuario.type(screen.getByLabelText('Senha'), 'oikora123')
    await usuario.click(screen.getByRole('button', { name: 'Entrar' }))

    expect(await screen.findByText('(11) •••••-4321')).toBeInTheDocument()
    expect(corpos).toEqual([{ identificador: '+5511987654321', senha: 'oikora123' }])
  })

  it('esqueci a senha confirma o envio sem revelar se a conta existe', async () => {
    const corpos: unknown[] = []
    servidor.use(
      csrf,
      http.post(`${API}/auth/senha/esqueci`, async ({ request }) => {
        corpos.push(await request.json())
        return new HttpResponse(null, { status: 204 })
      }),
    )
    const { usuario } = renderizarLogin()

    await usuario.click(screen.getByRole('button', { name: 'Esqueci minha senha' }))
    expect(screen.getByRole('heading', { name: 'Vamos recuperar seu acesso' })).toBeInTheDocument()
    await usuario.type(screen.getByLabelText('E-mail ou celular'), 'Maria@Exemplo.com')
    await usuario.click(screen.getByRole('button', { name: 'Enviar instruções' }))

    expect(await screen.findByRole('heading', { name: 'Confira sua caixa de entrada' })).toBeInTheDocument()
    expect(corpos).toEqual([{ identificador: 'maria@exemplo.com' }])
  })
})
