import { zodResolver } from '@hookform/resolvers/zod'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import { useEffect, type ReactNode } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { validarIdentificador } from '../lib/identificador'
import { CampoIdentificador } from './CampoIdentificador'
import type { RespostaAcao } from './FormularioCodigo'
import { AvisoErro, Cabecalho, Tela, Voltar } from './partes'

const esquema = z.object({
  identificador: z.string().superRefine((valor, contexto) => {
    const chave = validarIdentificador(valor)
    if (chave) contexto.addIssue({ code: 'custom', message: `validacao.${chave}` })
  }),
})

interface Props {
  aoEnviar: (identificador: string) => Promise<RespostaAcao>
  enviando: boolean
  aoVoltar: () => void
}

export function FormularioEsqueci({ aoEnviar, enviando, aoVoltar }: Props): ReactNode {
  const { t } = useTranslation('identidade')
  const { control, handleSubmit, setFocus, clearErrors, setError, formState } = useForm<z.infer<typeof esquema>>({
    resolver: zodResolver(esquema),
    defaultValues: { identificador: '' },
    reValidateMode: 'onSubmit',
  })

  useEffect(() => {
    setFocus('identificador')
  }, [setFocus])

  const enviar = handleSubmit(async ({ identificador }) => {
    const mensagem = await aoEnviar(identificador)
    if (mensagem) setError('root', { message: mensagem })
  })

  const erroCampo = formState.errors.identificador?.message
  const erroServidor = formState.errors.root?.message

  return (
    <Tela rotuloId="titulo-esqueci">
      <Voltar rotulo={t('voltarLogin')} aoClicar={aoVoltar} />
      <Cabecalho id="titulo-esqueci" titulo={t('esqueci.titulo')}>
        {t('esqueci.subtitulo')}
      </Cabecalho>
      {erroServidor ? <AvisoErro>{erroServidor}</AvisoErro> : null}
      <Box component="form" noValidate onSubmit={(evento) => void enviar(evento)} sx={{ display: 'grid', gap: 2 }}>
        <Controller
          name="identificador"
          control={control}
          render={({ field }) => (
            <CampoIdentificador
              id="identificador-esqueci"
              valor={field.value}
              aoMudar={(valor) => {
                clearErrors()
                field.onChange(valor)
              }}
              aoSair={field.onBlur}
              erro={erroCampo ? t(erroCampo) : undefined}
              campoRef={field.ref}
            />
          )}
        />
        <Button type="submit" variant="contained" fullWidth loading={enviando} loadingPosition="start">
          {enviando ? t('esqueci.enviando') : t('esqueci.enviar')}
        </Button>
      </Box>
    </Tela>
  )
}
