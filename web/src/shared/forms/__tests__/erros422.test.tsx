// OIK-SPEC-0001 T-37, caso 5: 422 com `contatos.0.valor` → erro no campo, foco no primeiro erro e
// resumo com os erros de campos que não estão na tela (OIK-ADR-0006 §5).
import TextField from '@mui/material/TextField'
import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw/http'
import { useState, type ReactNode } from 'react'
import { useForm, type Path } from 'react-hook-form'
import { describe, expect, it } from 'vitest'
import { renderizarComProvedores } from '../../../test/renderizar'
import { servidor } from '../../../test/servidor'
import { requisitar } from '../../http'
import { aplicarErros422, ResumoErros, type ErroForaDaTela } from '../index'

interface Pessoa {
  nome: string
  contatos: { valor: string }[]
}

const NA_TELA: Path<Pessoa>[] = ['nome', 'contatos.0.valor']

function FormularioPessoa(): ReactNode {
  const formulario = useForm<Pessoa>({ defaultValues: { nome: 'Maria', contatos: [{ valor: '123' }] } })
  const [fora, setFora] = useState<ErroForaDaTela[]>([])
  const { errors } = formulario.formState

  const enviar = formulario.handleSubmit(async (dados) => {
    try {
      await requisitar('POST', '/painel/pessoas', dados)
    } catch (erro) {
      setFora(aplicarErros422(erro, formulario, NA_TELA) ?? [])
    }
  })

  return (
    <form onSubmit={(evento) => void enviar(evento)} noValidate>
      <ResumoErros erros={fora} titulo="Corrija também:" />
      <TextField label="Nome" {...formulario.register('nome')} error={!!errors.nome} helperText={errors.nome?.message} />
      <TextField
        label="Celular"
        {...formulario.register('contatos.0.valor')}
        error={!!errors.contatos?.[0]?.valor}
        helperText={errors.contatos?.[0]?.valor?.message}
      />
      <button type="submit">Salvar</button>
    </form>
  )
}

describe('erros 422 no formulário', () => {
  it('põe o erro no campo, foca o primeiro e resume os campos fora da tela', async () => {
    servidor.use(
      http.post('*/api/v1/painel/pessoas', () =>
        HttpResponse.json(
          {
            message: 'Dados inválidos.',
            errors: { cpf: ['CPF já cadastrado.'], 'contatos.0.valor': ['Celular inválido.'] },
          },
          { status: 422 },
        ),
      ),
    )
    renderizarComProvedores(<FormularioPessoa />, {})

    await userEvent.click(screen.getByRole('button', { name: 'Salvar' }))

    expect(await screen.findByText('Celular inválido.')).toBeInTheDocument()
    expect(screen.getByLabelText('Celular')).toHaveFocus()
    expect(screen.getByLabelText('Celular')).toHaveAttribute('aria-invalid', 'true')
    expect(screen.getByLabelText('Nome')).toHaveAttribute('aria-invalid', 'false')
    expect(screen.getByRole('alert')).toHaveTextContent('Corrija também:CPF já cadastrado.')
  })

  it('não trata erro que não é 422', () => {
    const chamadas: string[] = []
    const formulario = { setError: () => chamadas.push('erro'), setFocus: () => chamadas.push('foco') }

    expect(aplicarErros422(new Error('rede'), formulario, ['nome'])).toBeNull()
    expect(chamadas).toEqual([])
  })
})
