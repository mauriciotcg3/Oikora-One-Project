import Alert from '@mui/material/Alert'
import AlertTitle from '@mui/material/AlertTitle'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import MenuItem from '@mui/material/MenuItem'
import Paper from '@mui/material/Paper'
import TextField from '@mui/material/TextField'
import Typography from '@mui/material/Typography'
import { useMutation, useQuery } from '@tanstack/react-query'
import { useNavigate } from '@tanstack/react-router'
import { useEffect, useState, type ReactNode } from 'react'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { aplicarErros422 } from '../../../shared/forms'
import { registrarEventoUi } from '../../../shared/observabilidade'
import { EstadoSucesso, LinkTexto, PaginaDoPainel } from '../../../shared/ui'
import { cadastrarPessoa, listarRecurso, verificarDuplicidade } from '../api/pessoas'
import { useMensagemDeErro } from './comum'

interface DadosCadastro {
  nome: string
  nascimento: string
  igreja: string
  vinculo: 'visitante' | 'frequentador' | 'membro'
  celular: string
}

const CAMPOS: readonly (keyof DadosCadastro)[] = ['nome', 'nascimento', 'igreja', 'vinculo', 'celular']
const VAZIO: DadosCadastro = { nome: '', nascimento: '', igreja: '', vinculo: 'visitante', celular: '' }

/** Cadastro rápido (T-41): validação junto ao campo, aviso de possível duplicidade e retorno claro. */
export function TelaCadastroPessoa(): ReactNode {
  const { t } = useTranslation('pessoas')
  const navegar = useNavigate()
  const mensagemDeErro = useMensagemDeErro()
  const [cadastrada, setCadastrada] = useState<string | null>(null)
  const igrejas = useQuery({ queryKey: ['pessoas', 'igrejas'], queryFn: () => listarRecurso('/painel/igrejas'), staleTime: 60_000 })
  const opcoesIgreja = igrejas.data?.data ?? []
  const formulario = useForm<DadosCadastro>({ defaultValues: VAZIO, mode: 'onTouched' })
  const { control, handleSubmit, reset, setFocus, setError, getValues } = formulario
  const [nome, nascimento] = useWatch({ control, name: ['nome', 'nascimento'] })
  const duplicidade = useQuery({
    queryKey: ['pessoas', 'duplicidade', nome.trim(), nascimento],
    queryFn: () => verificarDuplicidade(nome.trim(), nascimento),
    enabled: nome.trim().length >= 3 && nascimento !== '',
  })

  const cadastro = useMutation({
    mutationFn: ({ dados }: { dados: DadosCadastro; continuar: boolean }) => cadastrarPessoa({ ...dados }),
    onSuccess: (resposta, { dados, continuar }) => {
      registrarEventoUi('ui.pessoa_cadastrada', { continuar })
      if (continuar) {
        setCadastrada(dados.nome)
        reset({ ...VAZIO, igreja: dados.igreja, vinculo: dados.vinculo })
        setFocus('nome')
      } else {
        void navegar({ to: '/pessoas/$uuid', params: { uuid: resposta.data.uuid } })
      }
    },
    onError: (erro) => {
      aplicarErros422(erro, { setError, setFocus }, CAMPOS)
    },
  })

  const enviar = (continuar: boolean) => (): void => {
    setCadastrada(null)
    void handleSubmit((dados) => cadastro.mutate({ dados, continuar }))()
  }
  const salvandoOutra = cadastro.isPending && cadastro.variables.continuar
  const obrigatorio = { required: t('cadastro.obrigatorio') }
  const igrejaUnica = opcoesIgreja.length === 1 ? opcoesIgreja[0]?.uuid : undefined
  // Com uma igreja só, ela já vem escolhida.
  useEffect(() => {
    if (igrejaUnica && getValues('igreja') === '') formulario.setValue('igreja', igrejaUnica)
  }, [igrejaUnica, getValues, formulario])

  return (
    <PaginaDoPainel
      titulo={t('cadastro.titulo')}
      descricao={t('cadastro.descricao')}
      largura="estreita"
      voltar={<LinkTexto to="/pessoas">{t('comum.voltarPessoas')}</LinkTexto>}
    >
      {cadastrada && <EstadoSucesso titulo={t('cadastro.sucessoOutra', { nome: cadastrada })} />}
      <Paper
        component="form"
        variant="outlined"
        noValidate
        onSubmit={(evento) => {
          evento.preventDefault()
          enviar(false)()
        }}
        sx={{ p: { xs: 2, sm: 3 }, borderRadius: 4, display: 'grid', gap: 2.5 }}
      >
        <Controller
          name="nome"
          control={control}
          rules={{ ...obrigatorio, minLength: { value: 3, message: t('cadastro.obrigatorio') } }}
          render={({ field, fieldState }) => (
            <TextField {...field} inputRef={field.ref} required label={t('cadastro.nome')} autoComplete="off" error={Boolean(fieldState.error)} helperText={fieldState.error?.message ? mensagemCampo(fieldState.error.message, t) : undefined} />
          )}
        />
        <Box sx={{ display: 'grid', gap: 2.5, gridTemplateColumns: { xs: '1fr', sm: 'repeat(2, minmax(0, 1fr))' } }}>
          <Controller
            name="nascimento"
            control={control}
            render={({ field, fieldState }) => (
              <TextField {...field} inputRef={field.ref} type="date" label={t('cadastro.nascimento')} slotProps={{ inputLabel: { shrink: true } }} error={Boolean(fieldState.error)} helperText={fieldState.error?.message ? mensagemCampo(fieldState.error.message, t) : undefined} />
            )}
          />
          <Controller
            name="vinculo"
            control={control}
            render={({ field }) => (
              <TextField {...field} inputRef={field.ref} select label={t('cadastro.vinculo')}>
                {(['visitante', 'frequentador', 'membro'] as const).map((vinculo) => (
                  <MenuItem key={vinculo} value={vinculo}>
                    {t(`vinculos.${vinculo}`)}
                  </MenuItem>
                ))}
              </TextField>
            )}
          />
        </Box>
        <Controller
          name="igreja"
          control={control}
          rules={obrigatorio}
          render={({ field, fieldState }) => (
            <TextField
              {...field}
              inputRef={field.ref}
              select
              required
              label={t('cadastro.igreja')}
              disabled={igrejas.isPending}
              error={Boolean(fieldState.error) || (igrejas.isSuccess && opcoesIgreja.length === 0)}
              helperText={fieldState.error?.message ? mensagemCampo(fieldState.error.message, t) : igrejas.isSuccess && opcoesIgreja.length === 0 ? t('cadastro.semIgrejas') : undefined}
            >
              {opcoesIgreja.map((igreja) => (
                <MenuItem key={igreja.uuid} value={igreja.uuid}>
                  {String(igreja.nome ?? '')}
                </MenuItem>
              ))}
            </TextField>
          )}
        />
        <Controller
          name="celular"
          control={control}
          rules={obrigatorio}
          render={({ field, fieldState }) => (
            <TextField
              {...field}
              inputRef={field.ref}
              required
              type="tel"
              autoComplete="off"
              inputMode="tel"
              label={t('cadastro.celular')}
              error={Boolean(fieldState.error)}
              helperText={fieldState.error?.message ? mensagemCampo(fieldState.error.message, t) : t('cadastro.celularAjuda')}
            />
          )}
        />

        {(duplicidade.data?.data.length ?? 0) > 0 && (
          <Alert severity="warning">
            <AlertTitle>{t('cadastro.duplicidadeTitulo')}</AlertTitle>
            <Typography variant="body2" sx={{ mb: 1 }}>
              {t('cadastro.duplicidadeTexto')}
            </Typography>
            <Box component="ul" sx={{ m: 0, pl: 2.5, display: 'grid', gap: 0.5 }}>
              {duplicidade.data?.data.map((pessoa) => (
                <li key={pessoa.uuid}>
                  <LinkTexto to="/pessoas/$uuid" params={{ uuid: pessoa.uuid }}>
                    {pessoa.nome}
                  </LinkTexto>
                  {` · ${pessoa.igreja?.nome ?? t('comum.semIgreja')}`}
                  {pessoa.vinculo ? ` · ${t(`vinculos.${pessoa.vinculo}` as never, { defaultValue: pessoa.vinculo })}` : ''}
                  {pessoa.na_lixeira ? ` · ${t('cadastro.naLixeira')}` : ''}
                </li>
              ))}
            </Box>
          </Alert>
        )}

        {cadastro.isError && <Alert severity="error">{mensagemDeErro(cadastro.error)}</Alert>}

        <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1, pt: 0.5 }}>
          <Button type="submit" variant="contained" disabled={cadastro.isPending}>
            {cadastro.isPending && !salvandoOutra ? t('cadastro.salvando') : t('cadastro.salvar')}
          </Button>
          <Button variant="outlined" disabled={cadastro.isPending} onClick={enviar(true)}>
            {salvandoOutra ? t('cadastro.salvando') : t('cadastro.salvarOutra')}
          </Button>
        </Box>
      </Paper>
    </PaginaDoPainel>
  )
}

/** Mensagem do 422 vem como código da API; código conhecido vira texto, o resto pede para conferir o campo. */
function mensagemCampo(mensagem: string, t: (chave: string, opcoes?: { defaultValue: string }) => string): string {
  return t(`erros.codigos.${mensagem.replaceAll('.', '_')}`, { defaultValue: mensagem.includes(' ') ? mensagem : t('erros.validacao') })
}
