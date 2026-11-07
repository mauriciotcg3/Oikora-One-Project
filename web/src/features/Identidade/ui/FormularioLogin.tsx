import { zodResolver } from '@hookform/resolvers/zod'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import FormControl from '@mui/material/FormControl'
import FormHelperText from '@mui/material/FormHelperText'
import FormLabel from '@mui/material/FormLabel'
import IconButton from '@mui/material/IconButton'
import InputAdornment from '@mui/material/InputAdornment'
import Link from '@mui/material/Link'
import OutlinedInput from '@mui/material/OutlinedInput'
import Typography from '@mui/material/Typography'
import { useEffect, useState, type ReactNode } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import type { MotivoFalha } from '../api/autenticacao'
import { validarIdentificador } from '../lib/identificador'
import { CampoIdentificador } from './CampoIdentificador'
import { IconeCadeado, IconeOlho, IconeOlhoFechado } from './icones'
import { AvisoErro, Cabecalho, Tela } from './partes'

// Validação só de experiência (FE-SEC-001); as mensagens são chaves do namespace `identidade`.
const esquema = z.object({
  identificador: z.string().superRefine((valor, contexto) => {
    const chave = validarIdentificador(valor)
    if (chave) contexto.addIssue({ code: 'custom', message: `validacao.${chave}` })
  }),
  senha: z.string().min(1, 'validacao.senhaVazia'),
})

export type DadosLogin = z.infer<typeof esquema>

const MENSAGEM_FALHA: Record<MotivoFalha, string> = {
  credenciais: 'erros.credenciais',
  muitas_tentativas: 'erros.muitasTentativas',
  codigo_incorreto: 'erros.falha',
  falha: 'erros.falha',
}

interface Props {
  /** Resolve com o motivo quando o login falha. */
  aoEnviar: (dados: DadosLogin) => Promise<MotivoFalha | null>
  enviando: boolean
  falha: MotivoFalha | null
  aoEsquecer: () => void
  focarAoMontar: boolean
}

export function FormularioLogin({ aoEnviar, enviando, falha, aoEsquecer, focarAoMontar }: Props): ReactNode {
  const { t } = useTranslation('identidade')
  const [senhaVisivel, setSenhaVisivel] = useState(false)
  const [capsLock, setCapsLock] = useState(false)
  const { control, handleSubmit, setValue, setFocus, clearErrors, formState } = useForm<DadosLogin>({
    resolver: zodResolver(esquema),
    defaultValues: { identificador: '', senha: '' },
    reValidateMode: 'onSubmit',
  })

  useEffect(() => {
    if (focarAoMontar) setFocus('identificador')
  }, [focarAoMontar, setFocus])

  const enviar = handleSubmit(async (dados) => {
    const motivo = await aoEnviar(dados)
    if (motivo === 'credenciais') {
      setValue('senha', '')
      setFocus('senha')
    }
  })

  const erroIdentificador = formState.errors.identificador?.message
  const erroSenha = formState.errors.senha?.message

  return (
    <Tela rotuloId="titulo-login">
      <Cabecalho id="titulo-login" titulo={t('login.titulo')}>
        {t('login.subtitulo')}
      </Cabecalho>
      {falha ? <AvisoErro>{t(MENSAGEM_FALHA[falha])}</AvisoErro> : null}
      <Box component="form" noValidate onSubmit={(evento) => void enviar(evento)} sx={{ display: 'grid', gap: 2 }}>
        <Controller
          name="identificador"
          control={control}
          render={({ field }) => (
            <CampoIdentificador
              id="identificador"
              valor={field.value}
              aoMudar={(valor) => {
                clearErrors('identificador')
                field.onChange(valor)
              }}
              aoSair={field.onBlur}
              erro={erroIdentificador ? t(erroIdentificador) : undefined}
              campoRef={field.ref}
            />
          )}
        />
        <Controller
          name="senha"
          control={control}
          render={({ field }) => {
            const dica = erroSenha ? t(erroSenha) : capsLock ? t('login.capsLock') : undefined
            return (
              <FormControl fullWidth error={Boolean(erroSenha)} sx={{ gap: 1 }}>
                <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', gap: 1.5 }}>
                  <FormLabel htmlFor="senha">{t('login.senha')}</FormLabel>
                  <Link component="button" type="button" onClick={aoEsquecer} sx={{ py: 0.5 }}>
                    {t('login.esqueci')}
                  </Link>
                </Box>
                <OutlinedInput
                  id="senha"
                  name="senha"
                  type={senhaVisivel ? 'text' : 'password'}
                  value={field.value}
                  inputRef={field.ref}
                  onChange={(evento) => {
                    clearErrors('senha')
                    field.onChange(evento.target.value)
                  }}
                  onBlur={field.onBlur}
                  onKeyUp={(evento) => setCapsLock(evento.getModifierState('CapsLock'))}
                  inputProps={{ autoComplete: 'current-password', 'aria-describedby': dica ? 'senha-dica' : undefined }}
                  startAdornment={
                    <InputAdornment position="start" sx={{ color: 'text.secondary' }}>
                      <IconeCadeado />
                    </InputAdornment>
                  }
                  endAdornment={
                    <InputAdornment position="end">
                      <IconButton
                        edge="end"
                        aria-label={senhaVisivel ? t('login.ocultarSenha') : t('login.mostrarSenha')}
                        aria-pressed={senhaVisivel}
                        onClick={() => {
                          setSenhaVisivel((visivel) => !visivel)
                          setFocus('senha')
                        }}
                      >
                        {senhaVisivel ? <IconeOlhoFechado /> : <IconeOlho />}
                      </IconButton>
                    </InputAdornment>
                  }
                />
                {dica ? (
                  <FormHelperText id="senha-dica" aria-live="polite" sx={{ m: 0, fontWeight: erroSenha ? 500 : 400 }}>
                    {dica}
                  </FormHelperText>
                ) : null}
              </FormControl>
            )
          }}
        />
        <Button type="submit" variant="contained" fullWidth loading={enviando} loadingPosition="start">
          {enviando ? t('login.entrando') : t('login.entrar')}
        </Button>
      </Box>
      <Typography color="text.secondary" sx={{ fontSize: '0.9375rem' }}>
        {t('login.ajuda')}
      </Typography>
    </Tela>
  )
}
