import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Link from '@mui/material/Link'
import Typography from '@mui/material/Typography'
import { styled } from '@mui/material/styles'
import { useEffect, useRef, useState, type ClipboardEvent, type KeyboardEvent, type ReactNode } from 'react'
import { Trans, useTranslation } from 'react-i18next'
import type { DesafioDoisFatores } from '../api/autenticacao'
import { Cabecalho, Dica, Tela, Voltar } from './partes'

// Código de 6 dígitos (OIK-ADR-0003 §7): avança sozinho, aceita colar e confirma ao completar.

const TOTAL = 6
const ESPERA_REENVIO = 30
const vazio = (): string[] => Array.from({ length: TOTAL }, () => '')
const somenteDigitos = (valor: string): string => valor.replace(/\D/g, '')

const CaixaDigito = styled('input')(({ theme }) => ({
  width: '100%',
  minWidth: 0,
  height: 60,
  textAlign: 'center',
  font: 'inherit',
  fontWeight: 600,
  fontSize: '1.5rem',
  fontVariantNumeric: 'tabular-nums',
  color: theme.vars?.palette.text.primary,
  background: theme.vars?.palette.background.paper,
  border: '1px solid #CDBFB3',
  borderRadius: 10,
  outline: 0,
  transition: 'border-color .15s, box-shadow .15s',
  '&:hover': { borderColor: theme.vars?.palette.text.secondary },
  '&:focus': {
    borderColor: theme.vars?.palette.primary.main,
    boxShadow: `0 0 0 3px rgba(${theme.vars?.palette.primary.mainChannel} / 0.22)`,
  },
  '&[aria-invalid="true"]': { borderColor: theme.vars?.palette.error.main },
  '@media (max-width: 360px)': { height: 52, fontSize: '1.25rem' },
}))

/** Resultado de uma ação de servidor: `null` deu certo; texto é a mensagem para a pessoa. */
export type RespostaAcao = string | null

interface Props {
  desafio: DesafioDoisFatores
  aoConfirmar: (codigo: string) => Promise<RespostaAcao>
  confirmando: boolean
  aoReenviar: () => Promise<RespostaAcao>
  aoTrocarCanal: () => Promise<RespostaAcao>
  aoUsarRecuperacao: () => void
  aoVoltar: () => void
}

export function FormularioCodigo(props: Props): ReactNode {
  const { desafio, aoConfirmar, confirmando, aoReenviar, aoTrocarCanal, aoUsarRecuperacao, aoVoltar } = props
  const { t } = useTranslation('identidade')
  const [digitos, setDigitos] = useState<string[]>(vazio)
  const [dica, setDica] = useState<{ texto: string; erro: boolean } | null>(null)
  const [segundos, setSegundos] = useState(ESPERA_REENVIO)
  const caixas = useRef<(HTMLInputElement | null)[]>([])
  const contando = segundos > 0
  const porMensagem = desafio.canal !== 'totp'

  const focar = (indice: number): void => caixas.current[indice]?.focus()

  useEffect(() => focar(0), [])

  useEffect(() => {
    if (!contando) return
    const id = window.setInterval(() => setSegundos((s) => Math.max(0, s - 1)), 1000)
    return () => window.clearInterval(id)
  }, [contando])

  const verificar = async (codigo: string[]): Promise<void> => {
    const vazioEm = codigo.findIndex((d) => !d)
    if (vazioEm >= 0) {
      setDica({ texto: t('validacao.codigoIncompleto'), erro: true })
      focar(vazioEm)
      return
    }
    const mensagem = await aoConfirmar(codigo.join(''))
    if (mensagem) {
      setDica({ texto: mensagem, erro: true })
      setDigitos(vazio())
      focar(0)
    }
  }

  const preencher = (inicio: number, novos: string): void => {
    const proximo = [...digitos]
    novos
      .slice(0, TOTAL - inicio)
      .split('')
      .forEach((d, i) => {
        proximo[inicio + i] = d
      })
    setDigitos(proximo)
    setDica(null)
    focar(Math.min(inicio + novos.length, TOTAL - 1))
    if (proximo.every(Boolean)) void verificar(proximo)
  }

  const aoDigitar = (indice: number, valor: string): void => {
    let novos = somenteDigitos(valor)
    const atual = digitos[indice] ?? ''
    if (atual && novos.length > 1 && novos.startsWith(atual)) novos = novos.slice(1)
    if (novos) {
      preencher(indice, novos)
      return
    }
    const proximo = [...digitos]
    proximo[indice] = ''
    setDigitos(proximo)
  }

  const aoTecla = (indice: number, evento: KeyboardEvent<HTMLInputElement>): void => {
    if (evento.key === 'Backspace' && !digitos[indice] && indice > 0) {
      evento.preventDefault()
      const proximo = [...digitos]
      proximo[indice - 1] = ''
      setDigitos(proximo)
      focar(indice - 1)
    }
    if (evento.key === 'ArrowLeft' && indice > 0) focar(indice - 1)
    if (evento.key === 'ArrowRight' && indice < TOTAL - 1) focar(indice + 1)
  }

  const aoColar = (evento: ClipboardEvent<HTMLInputElement>): void => {
    const colado = somenteDigitos(evento.clipboardData.getData('text'))
    if (!colado) return
    evento.preventDefault()
    preencher(0, colado)
  }

  const reenviarPor = async (acao: () => Promise<RespostaAcao>, aviso: string): Promise<void> => {
    const mensagem = await acao()
    setDigitos(vazio())
    setDica({ texto: mensagem ?? aviso, erro: Boolean(mensagem) })
    setSegundos(ESPERA_REENVIO)
    focar(0)
  }

  const destino = (
    <Trans
      t={t}
      i18nKey={desafio.canal === 'whatsapp' ? 'codigo.enviadoWhatsapp' : 'codigo.enviadoEmail'}
      values={{ destino: desafio.destino }}
      components={[<strong key="destino" />]}
    />
  )
  const invalido = Boolean(dica?.erro)

  return (
    <Tela rotuloId="titulo-codigo">
      <Voltar rotulo={t('voltar')} aoClicar={aoVoltar} />
      <Cabecalho id="titulo-codigo" titulo={t('codigo.titulo')}>
        {porMensagem ? destino : t('codigo.autenticador')}
      </Cabecalho>
      <Box
        component="form"
        noValidate
        onSubmit={(evento) => {
          evento.preventDefault()
          void verificar(digitos)
        }}
        sx={{ display: 'grid', gap: 2 }}
      >
        <Box component="fieldset" sx={{ border: 0, m: 0, p: 0, minWidth: 0, display: 'grid', gap: 1 }}>
          <Box component="legend" sx={visualmenteOculto}>
            {t('codigo.legenda')}
          </Box>
          <Box sx={{ display: 'grid', gridTemplateColumns: `repeat(${TOTAL}, minmax(0, 1fr))`, gap: { xs: 0.75, sm: 1 } }}>
            {digitos.map((digito, indice) => (
              <CaixaDigito
                // A posição é a identidade de cada caixa.
                key={indice}
                ref={(elemento) => {
                  caixas.current[indice] = elemento
                }}
                value={digito}
                inputMode="numeric"
                name={indice === 0 ? 'codigo' : undefined}
                autoComplete={indice === 0 ? 'one-time-code' : 'off'}
                aria-label={t('codigo.digito', { numero: indice + 1 })}
                aria-invalid={invalido || undefined}
                aria-describedby="codigo-dica"
                onChange={(evento) => aoDigitar(indice, evento.target.value)}
                onKeyDown={(evento) => aoTecla(indice, evento)}
                onPaste={aoColar}
                onFocus={(evento) => evento.target.select()}
              />
            ))}
          </Box>
          <Dica id="codigo-dica" erro={invalido}>
            {dica?.texto}
          </Dica>
        </Box>
        <Button type="submit" variant="contained" fullWidth loading={confirmando} loadingPosition="start">
          {confirmando ? t('codigo.confirmando') : t('codigo.confirmar')}
        </Button>
      </Box>
      <Box sx={{ display: 'grid', gap: 0.5, justifyItems: 'start', p: 2, borderRadius: '16px', bgcolor: 'afundado' }}>
        {porMensagem ? (
          <>
            <Typography sx={{ m: 0, mb: 0.5, fontSize: '0.875rem', color: 'text.secondary' }}>{t('codigo.naoChegou')}</Typography>
            <Link
              component="button"
              type="button"
              disabled={contando}
              onClick={() => void reenviarPor(aoReenviar, t('codigo.reenviado'))}
              sx={{ py: 0.5, '&:disabled': { color: 'text.secondary', cursor: 'default', textDecoration: 'none', fontWeight: 400 } }}
            >
              {contando ? t('codigo.reenviarEm', { segundos: String(segundos).padStart(2, '0') }) : t('codigo.reenviar')}
            </Link>
            <Link component="button" type="button" onClick={() => void reenviarPor(aoTrocarCanal, t('codigo.canalTrocado'))} sx={{ py: 0.5 }}>
              {desafio.canal === 'email' ? t('codigo.receberWhatsapp') : t('codigo.receberEmail')}
            </Link>
          </>
        ) : null}
        <Link component="button" type="button" onClick={aoUsarRecuperacao} sx={{ py: 0.5 }}>
          {t('codigo.usarRecuperacao')}
        </Link>
      </Box>
    </Tela>
  )
}

const visualmenteOculto = {
  position: 'absolute',
  width: '1px',
  height: '1px',
  overflow: 'hidden',
  clip: 'rect(0 0 0 0)',
  whiteSpace: 'nowrap',
} as const
