import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Checkbox from '@mui/material/Checkbox'
import FormControlLabel from '@mui/material/FormControlLabel'
import Radio from '@mui/material/Radio'
import RadioGroup from '@mui/material/RadioGroup'
import Stack from '@mui/material/Stack'
import TextField from '@mui/material/TextField'
import Typography from '@mui/material/Typography'
import { useState, type FormEvent, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { cadastrarDoisFatores, confirmarSenha, type CanalCadastro } from '../api/conta'

type Etapa = 'senha' | 'canal' | 'codigos'

interface Props {
  /** Chamado depois de a pessoa confirmar que guardou os códigos. */
  aoConcluir: () => void
}

/**
 * Cadastro do 2FA (RN-16; OIK-ADR-0003 §6): o Administrador sem 2FA chega aqui depois do login.
 * Confirma a senha, escolhe o canal e vê os 10 códigos de recuperação uma única vez.
 */
export function CadastroDoisFatores({ aoConcluir }: Props): ReactNode {
  const { t } = useTranslation('identidade')
  const [etapa, setEtapa] = useState<Etapa>('senha')
  const [senha, setSenha] = useState('')
  const [canal, setCanal] = useState<CanalCadastro>('email')
  const [codigos, setCodigos] = useState<string[]>([])
  const [guardou, setGuardou] = useState(false)
  const [erro, setErro] = useState<string | null>(null)
  const [enviando, setEnviando] = useState(false)

  const enviarSenha = async (evento: FormEvent): Promise<void> => {
    evento.preventDefault()
    setEnviando(true)
    setErro(null)
    try {
      if (await confirmarSenha(senha)) setEtapa('canal')
      else setErro(t('cadastro2fa.senhaIncorreta'))
    } catch {
      setErro(t('erros.falha'))
    } finally {
      setEnviando(false)
    }
  }

  const enviarCanal = async (evento: FormEvent): Promise<void> => {
    evento.preventDefault()
    setEnviando(true)
    setErro(null)
    try {
      setCodigos(await cadastrarDoisFatores(canal))
      setSenha('')
      setEtapa('codigos')
    } catch {
      setErro(t('cadastro2fa.canalRecusado'))
    } finally {
      setEnviando(false)
    }
  }

  return (
    <Box component="main" sx={{ maxWidth: 480, mx: 'auto', p: 3 }}>
      <Stack spacing={2}>
        <Typography variant="h1" sx={{ fontSize: '1.75rem' }}>{t('cadastro2fa.titulo')}</Typography>
        {erro && <Alert severity="error">{erro}</Alert>}

        {etapa === 'senha' && (
          <Stack component="form" spacing={2} onSubmit={(e) => void enviarSenha(e)} noValidate>
            <Typography color="text.secondary">{t('cadastro2fa.explicacao')}</Typography>
            <TextField
              label={t('cadastro2fa.senha')}
              type="password"
              autoComplete="current-password"
              value={senha}
              onChange={(e) => setSenha(e.target.value)}
              required
            />
            <Button type="submit" variant="contained" disabled={enviando || senha === ''}>{t('cadastro2fa.continuar')}</Button>
          </Stack>
        )}

        {etapa === 'canal' && (
          <Stack component="form" spacing={2} onSubmit={(e) => void enviarCanal(e)}>
            <Typography id="cadastro2fa-canal" color="text.secondary">{t('cadastro2fa.escolhaCanal')}</Typography>
            <RadioGroup aria-labelledby="cadastro2fa-canal" value={canal} onChange={(e) => setCanal(e.target.value as CanalCadastro)}>
              <FormControlLabel value="email" control={<Radio />} label={t('cadastro2fa.canalEmail')} />
              <FormControlLabel value="whatsapp" control={<Radio />} label={t('cadastro2fa.canalWhatsapp')} />
            </RadioGroup>
            <Button type="submit" variant="contained" disabled={enviando}>{t('cadastro2fa.ativar')}</Button>
          </Stack>
        )}

        {etapa === 'codigos' && (
          <Stack spacing={2}>
            <Alert severity="warning">{t('cadastro2fa.codigosAviso')}</Alert>
            <Box component="ol" aria-label={t('cadastro2fa.codigosRotulo')} sx={{ fontFamily: 'monospace', columns: 2, m: 0 }}>
              {codigos.map((codigo) => <li key={codigo}>{codigo}</li>)}
            </Box>
            <FormControlLabel
              control={<Checkbox checked={guardou} onChange={(e) => setGuardou(e.target.checked)} />}
              label={t('cadastro2fa.guardei')}
            />
            <Button variant="contained" disabled={!guardou} onClick={aoConcluir}>{t('cadastro2fa.irParaPainel')}</Button>
          </Stack>
        )}
      </Stack>
    </Box>
  )
}
