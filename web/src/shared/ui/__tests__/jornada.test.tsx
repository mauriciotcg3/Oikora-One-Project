// OIK-SPEC-0001 T-52 (CA-43): jornada guiada — validação junto ao campo, foco, etapas,
// confirmação de ação irreversível e erro 422 na etapa correta.
import { zodResolver } from '@hookform/resolvers/zod'
import TextField from '@mui/material/TextField'
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw/http'
import { useState, type ReactNode } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { describe, expect, it, vi } from 'vitest'
import { z } from 'zod'
import { requisitar } from '../../http'
import { renderizarComProvedores } from '../../../test/renderizar'
import { servidor } from '../../../test/servidor'
import { ConfirmacaoIrreversivel } from '../ConfirmacaoIrreversivel'
import { criarPersistenciaJornada, JornadaGuiada, type EtapaDaJornada, type PersistenciaJornada } from '../JornadaGuiada'

const esquema = z.object({
  nome: z.string().min(1, 'Informe o nome'),
  email: z.string().min(1, 'Informe o e-mail'),
  cidade: z.string(),
  igreja: z.string(),
})
type Valores = z.infer<typeof esquema>

const TEXTOS = {
  anterior: 'Voltar',
  proximo: 'Avançar',
  concluir: 'Concluir',
  etapaDe: (atual: number, total: number) => `Etapa ${atual} de ${total}`,
  errosFora: 'Corrija também:',
}

function Jornada({ aoConcluir, persistencia }: { aoConcluir: (valores: Valores) => Promise<void>, persistencia?: PersistenciaJornada<Valores> }): ReactNode {
  const formulario = useForm<Valores>({
    resolver: zodResolver(esquema),
    defaultValues: { nome: '', email: '', cidade: '', igreja: '' },
  })
  const campo = (nome: keyof Valores, rotulo: string): ReactNode => (
    <Controller
      name={nome}
      control={formulario.control}
      render={({ field, fieldState }) => (
        <TextField
          label={rotulo}
          name={field.name}
          value={field.value}
          onChange={field.onChange}
          onBlur={field.onBlur}
          inputRef={field.ref}
          error={fieldState.invalid} helperText={fieldState.error?.message} />
      )}
    />
  )
  const etapas: EtapaDaJornada<Valores>[] = [
    { chave: 'dados', titulo: 'Dados', campos: ['nome', 'email'], conteudo: <>{campo('nome', 'Nome')}{campo('email', 'E-mail')}</> },
    { chave: 'local', titulo: 'Local', campos: ['cidade', 'igreja'], conteudo: <>{campo('cidade', 'Cidade')}{campo('igreja', 'Igreja')}</> },
  ]
  return <JornadaGuiada formulario={formulario} etapas={etapas} textos={TEXTOS} aoConcluir={aoConcluir} persistencia={persistencia} />
}

describe('Jornada guiada', () => {
  it('avançar com campo inválido mostra o erro junto ao campo, foca nele e não troca de etapa', async () => {
    const usuario = userEvent.setup()
    renderizarComProvedores(<Jornada aoConcluir={vi.fn()} />, {})

    await usuario.click(screen.getByRole('button', { name: 'Avançar' }))

    expect(await screen.findByText('Informe o nome')).toBeInTheDocument()
    await waitFor(() => expect(screen.getByLabelText('Nome')).toHaveFocus())
    expect(screen.getByText('Etapa 1 de 2')).toBeInTheDocument()
  })

  it('voltar preserva o que foi digitado e a etapa atual é anunciada', async () => {
    const usuario = userEvent.setup()
    renderizarComProvedores(<Jornada aoConcluir={vi.fn()} />, {})

    await usuario.type(screen.getByLabelText('Nome'), 'Maria')
    await usuario.type(screen.getByLabelText('E-mail'), 'maria@exemplo.com')
    await usuario.click(screen.getByRole('button', { name: 'Avançar' }))
    expect(await screen.findByText('Etapa 2 de 2')).toBeInTheDocument()

    await usuario.click(screen.getByRole('button', { name: 'Voltar' }))

    expect(screen.getByText('Etapa 1 de 2')).toBeInTheDocument()
    expect(screen.getByLabelText('Nome')).toHaveValue('Maria')
  })

  it('erro 422 do servidor aparece no campo da etapa correspondente', async () => {
    servidor.use(
      http.post('*/api/v1/pessoas', () =>
        HttpResponse.json({ message: 'inválido', errors: { email: ['E-mail já cadastrado'] } }, { status: 422 }),
      ),
    )
    const usuario = userEvent.setup()
    renderizarComProvedores(<Jornada aoConcluir={async (valores) => void (await requisitar('POST', '/pessoas', valores))} />, {})

    await usuario.type(screen.getByLabelText('Nome'), 'Maria')
    await usuario.type(screen.getByLabelText('E-mail'), 'maria@exemplo.com')
    await usuario.click(screen.getByRole('button', { name: 'Avançar' }))
    await usuario.click(await screen.findByRole('button', { name: 'Concluir' }))

    expect(await screen.findByText('E-mail já cadastrado')).toBeInTheDocument()
    expect(screen.getByText('Etapa 1 de 2')).toBeInTheDocument()
    await waitFor(() => expect(screen.getByLabelText('E-mail')).toHaveFocus())
  })

  it('retoma etapa e campos depois de recarregar a página', async () => {
    let estado: Record<string, unknown> | null = null
    servidor.use(
      http.get('*/api/v1/painel/rascunhos/conectar_whatsapp', () => HttpResponse.json({ data: estado })),
      http.put('*/api/v1/painel/rascunhos/conectar_whatsapp', async ({ request }) => {
        const corpo = await request.json() as { etapa: string, dados: Valores }
        estado = { tipo: 'conectar_whatsapp', etapa: corpo.etapa, dados: corpo.dados, versao: 1, atualizado_em: '', expira_em: '' }
        return HttpResponse.json({ data: estado })
      }),
    )
    const usuario = userEvent.setup()
    const persistencia = criarPersistenciaJornada<Valores>('conectar_whatsapp')
    const primeira = renderizarComProvedores(<Jornada aoConcluir={vi.fn()} persistencia={persistencia} />, {})
    await usuario.type(screen.getByLabelText('Nome'), 'Maria')
    await usuario.type(screen.getByLabelText('E-mail'), 'maria@exemplo.com')
    await usuario.click(screen.getByRole('button', { name: 'Avançar' }))
    expect(await screen.findByText('Etapa 2 de 2')).toBeInTheDocument()
    primeira.unmount()

    renderizarComProvedores(<Jornada aoConcluir={vi.fn()} persistencia={criarPersistenciaJornada('conectar_whatsapp')} />, {})
    expect(await screen.findByText('Etapa 2 de 2')).toBeInTheDocument()
    await usuario.click(screen.getByRole('button', { name: 'Voltar' }))
    expect(screen.getByLabelText('Nome')).toHaveValue('Maria')
  })
})

function Exclusao({ aoExecutar }: { aoExecutar: () => void }): ReactNode {
  const [aberta, setAberta] = useState(false)
  return (
    <>
      <button onClick={() => setAberta(true)}>Excluir cadastro</button>
      <ConfirmacaoIrreversivel
        aberta={aberta}
        titulo="Excluir o cadastro?"
        texto="Não dá para desfazer."
        rotuloConfirmar="Excluir"
        rotuloCancelar="Manter"
        aoCancelar={() => setAberta(false)}
        aoConfirmar={() => {
          setAberta(false)
          aoExecutar()
        }}
      />
    </>
  )
}

describe('Confirmação de ação irreversível', () => {
  it('só executa depois de confirmar', async () => {
    const executar = vi.fn()
    const usuario = userEvent.setup()
    renderizarComProvedores(<Exclusao aoExecutar={executar} />, {})

    await usuario.click(screen.getByRole('button', { name: 'Excluir cadastro' }))
    expect(executar).not.toHaveBeenCalled()
    await usuario.click(await screen.findByRole('button', { name: 'Excluir' }))

    expect(executar).toHaveBeenCalledTimes(1)
  })

  it('Esc cancela sem executar e devolve o foco', async () => {
    const executar = vi.fn()
    const usuario = userEvent.setup()
    renderizarComProvedores(<Exclusao aoExecutar={executar} />, {})
    const abrir = screen.getByRole('button', { name: 'Excluir cadastro' })

    await usuario.click(abrir)
    expect(await screen.findByRole('alertdialog', { name: 'Excluir o cadastro?' })).toBeInTheDocument()
    await usuario.keyboard('{Escape}')

    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument())
    expect(executar).not.toHaveBeenCalled()
    expect(abrir).toHaveFocus()
  })
})
