import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import CircularProgress from '@mui/material/CircularProgress'
import Link from '@mui/material/Link'
import Stack from '@mui/material/Stack'
import TextField from '@mui/material/TextField'
import Typography from '@mui/material/Typography'
import { useState, type FormEvent, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { aceitarConvite, type ResultadoAceite } from '../api/conta'
import { useConvite, useMe } from '../model/useConta'

interface Props {
  token: string
  /** Caminho do login com volta para este convite. */
  linkLogin: string
}

const SENHA_MINIMA = 8

/**
 * Aceite de convite (OIK-ADR-0003 §5; RN-02; CA-02): token inválido, usado ou vencido mostra "convite
 * inválido ou expirado". Sem sessão, pede a senha nova (conta nova); com sessão, liga a conta logada.
 * Quem já tem conta com o contato da ficha é levado ao login (`entre_para_aceitar`).
 */
export function PaginaConvite({ token, linkLogin }: Props): ReactNode {
  const { t } = useTranslation('identidade')
  const convite = useConvite(token)
  const me = useMe()
  const [senha, setSenha] = useState('')
  const [confirmacao, setConfirmacao] = useState('')
  const [erro, setErro] = useState<string | null>(null)
  const [resultado, setResultado] = useState<ResultadoAceite | null>(null)
  const [enviando, setEnviando] = useState(false)

  if (convite.isPending || me.isPending) {
    return <Box sx={{ display: 'grid', placeContent: 'center', minHeight: '50dvh' }}><CircularProgress aria-label={t('convite.carregando')} /></Box>
  }

  if (convite.data === null || convite.isError || resultado === 'invalido') {
    return (
      <Box component="main" sx={{ maxWidth: 480, mx: 'auto', p: 3 }}>
        <Alert severity="error">{t('convite.invalido')}</Alert>
      </Box>
    )
  }

  const logado = me.data !== null && me.data !== undefined

  const aceitar = async (evento: FormEvent): Promise<void> => {
    evento.preventDefault()
    setErro(null)
    if (!logado) {
      if (senha.length < SENHA_MINIMA) return setErro(t('convite.senhaCurta', { minimo: SENHA_MINIMA }))
      if (senha !== confirmacao) return setErro(t('convite.senhasDiferentes'))
    }
    setEnviando(true)
    try {
      setResultado(await aceitarConvite(token, logado ? undefined : senha))
    } catch {
      setErro(t('convite.senhaRecusada'))
    } finally {
      setEnviando(false)
    }
  }

  const dados = convite.data

  return (
    <Box component="main" sx={{ maxWidth: 480, mx: 'auto', p: 3 }}>
      <Stack spacing={2}>
        <Typography variant="h1" sx={{ fontSize: '1.75rem' }}>{t('convite.titulo', { organizacao: dados.organizacao.nome })}</Typography>
        <Typography color="text.secondary">
          {t('convite.texto', { pessoa: dados.pessoa.nome ?? '', papel: dados.papel.nome ?? '' })}
        </Typography>

        {resultado === 'aceito' && (
          <Alert severity="success">
            {t('convite.aceito')} <Link href={linkLogin}>{t('convite.entrar')}</Link>
          </Alert>
        )}
        {resultado === 'entre_para_aceitar' && (
          <Alert severity="info">
            {t('convite.entreParaAceitar')} <Link href={linkLogin}>{t('convite.entrar')}</Link>
          </Alert>
        )}
        {erro && <Alert severity="error">{erro}</Alert>}

        {resultado === null && (
          <Stack component="form" spacing={2} onSubmit={(e) => void aceitar(e)} noValidate>
            {logado ? (
              <Typography>{t('convite.comSessao')}</Typography>
            ) : (
              <>
                <Typography>{t('convite.definaSenha')}</Typography>
                <TextField label={t('convite.senha')} type="password" autoComplete="new-password" value={senha} onChange={(e) => setSenha(e.target.value)} required />
                <TextField label={t('convite.confirmarSenha')} type="password" autoComplete="new-password" value={confirmacao} onChange={(e) => setConfirmacao(e.target.value)} required />
                <Typography variant="body2">
                  {t('convite.jaTemConta')} <Link href={linkLogin}>{t('convite.entrar')}</Link>
                </Typography>
              </>
            )}
            <Button type="submit" variant="contained" disabled={enviando}>{t('convite.aceitar')}</Button>
          </Stack>
        )}
      </Stack>
    </Box>
  )
}
