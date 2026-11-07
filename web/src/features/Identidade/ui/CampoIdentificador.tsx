import FormControl from '@mui/material/FormControl'
import FormHelperText from '@mui/material/FormHelperText'
import FormLabel from '@mui/material/FormLabel'
import InputAdornment from '@mui/material/InputAdornment'
import OutlinedInput from '@mui/material/OutlinedInput'
import type { ReactNode, Ref } from 'react'
import { useTranslation } from 'react-i18next'
import { formatarIdentificador, pareceCelular } from '../lib/identificador'
import { IconeCelular, IconeEmail } from './icones'

interface Props {
  id: string
  valor: string
  aoMudar: (valor: string) => void
  aoSair: () => void
  erro?: string
  campoRef: Ref<HTMLInputElement>
}

/** E-mail ou celular num campo só, com máscara do celular brasileiro. */
export function CampoIdentificador({ id, valor, aoMudar, aoSair, erro, campoRef }: Props): ReactNode {
  const { t } = useTranslation('identidade')
  const celular = pareceCelular(valor)
  const dica = erro ?? (celular ? t('login.dicaCelular') : undefined)
  const dicaId = `${id}-dica`

  return (
    <FormControl fullWidth error={Boolean(erro)} sx={{ gap: 1 }}>
      <FormLabel htmlFor={id}>{t('login.identificador')}</FormLabel>
      <OutlinedInput
        id={id}
        name="identificador"
        value={valor}
        inputRef={campoRef}
        onChange={(evento) => aoMudar(formatarIdentificador(evento.target.value))}
        onBlur={aoSair}
        placeholder={t('login.identificadorExemplo')}
        startAdornment={
          <InputAdornment position="start" sx={{ color: 'text.secondary' }}>
            {celular ? <IconeCelular /> : <IconeEmail />}
          </InputAdornment>
        }
        inputProps={{
          inputMode: celular ? 'tel' : 'email',
          autoComplete: 'username',
          spellCheck: false,
          autoCapitalize: 'off',
          'aria-describedby': dica ? dicaId : undefined,
        }}
      />
      {dica ? (
        <FormHelperText id={dicaId} aria-live="polite" sx={{ m: 0, fontWeight: erro ? 500 : 400 }}>
          {dica}
        </FormHelperText>
      ) : null}
    </FormControl>
  )
}
