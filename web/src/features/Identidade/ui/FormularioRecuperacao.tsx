import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import FormControl from '@mui/material/FormControl'
import FormHelperText from '@mui/material/FormHelperText'
import FormLabel from '@mui/material/FormLabel'
import InputAdornment from '@mui/material/InputAdornment'
import OutlinedInput from '@mui/material/OutlinedInput'
import { useEffect, useRef, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import type { RespostaAcao } from './FormularioCodigo'
import { IconeChave } from './icones'
import { Cabecalho, Tela, Voltar } from './partes'

// Um dos 10 códigos de recuperação de uso único (OIK-ADR-0003 §7), no formato ABCD-1234.

interface Props {
  aoConfirmar: (codigoRecuperacao: string) => Promise<RespostaAcao>
  confirmando: boolean
  aoVoltar: () => void
}

export function FormularioRecuperacao({ aoConfirmar, confirmando, aoVoltar }: Props): ReactNode {
  const { t } = useTranslation('identidade')
  const [codigo, setCodigo] = useState('')
  const [erro, setErro] = useState<string | null>(null)
  const campo = useRef<HTMLInputElement>(null)

  useEffect(() => {
    campo.current?.focus()
  }, [])

  const enviar = async (): Promise<void> => {
    const valor = codigo.trim()
    if (valor.replace(/[^a-z0-9]/gi, '').length < 8) {
      setErro(t('validacao.recuperacaoIncompleta'))
      campo.current?.focus()
      return
    }
    const mensagem = await aoConfirmar(valor)
    if (mensagem) {
      setErro(mensagem)
      campo.current?.focus()
    }
  }

  return (
    <Tela rotuloId="titulo-recuperacao">
      <Voltar rotulo={t('voltar')} aoClicar={aoVoltar} />
      <Cabecalho id="titulo-recuperacao" titulo={t('recuperacao.titulo')}>
        {t('recuperacao.subtitulo')}
      </Cabecalho>
      <Box
        component="form"
        noValidate
        onSubmit={(evento) => {
          evento.preventDefault()
          void enviar()
        }}
        sx={{ display: 'grid', gap: 2 }}
      >
        <FormControl fullWidth error={Boolean(erro)} sx={{ gap: 1 }}>
          <FormLabel htmlFor="codigo-recuperacao">{t('recuperacao.campo')}</FormLabel>
          <OutlinedInput
            id="codigo-recuperacao"
            name="codigo_recuperacao"
            value={codigo}
            inputRef={campo}
            onChange={(evento) => {
              setErro(null)
              setCodigo(evento.target.value.toUpperCase())
            }}
            placeholder={t('recuperacao.exemplo')}
            startAdornment={
              <InputAdornment position="start" sx={{ color: 'text.secondary' }}>
                <IconeChave />
              </InputAdornment>
            }
            inputProps={{
              autoComplete: 'off',
              spellCheck: false,
              autoCapitalize: 'characters',
              'aria-describedby': erro ? 'codigo-recuperacao-dica' : undefined,
            }}
          />
          {erro ? (
            <FormHelperText id="codigo-recuperacao-dica" aria-live="polite" sx={{ m: 0, fontWeight: 500 }}>
              {erro}
            </FormHelperText>
          ) : null}
        </FormControl>
        <Button type="submit" variant="contained" fullWidth loading={confirmando} loadingPosition="start">
          {t('recuperacao.confirmar')}
        </Button>
      </Box>
    </Tela>
  )
}
