import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Chip from '@mui/material/Chip'
import Paper from '@mui/material/Paper'
import Snackbar from '@mui/material/Snackbar'
import Switch from '@mui/material/Switch'
import Typography from '@mui/material/Typography'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useId, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { ErroHttp } from '../../../shared/http'
import { registrarEventoUi } from '../../../shared/observabilidade'
import { BotaoLink, ConfirmacaoIrreversivel, EstadoCarregando, EstadoErro, EstadoSucesso, EstadoVazio, PaginaDoPainel } from '../../../shared/ui'
import { definirPreferencia, desligarWhatsapp, listarAvisos, listarPreferencias, marcarAviso, marcarTodos, type Aviso } from '../api/avisos'

const PREFIXO_PAINEL = '/painel'
const URL_OAUTH = '/api/v1/painel/avisos/whatsapp/oauth'

function useMensagemDeErro(): (erro: unknown) => string {
  const { t, i18n } = useTranslation('avisos')
  return (erro) => {
    if (!(erro instanceof ErroHttp)) return t('erros.generico')
    const chave = erro.codigo ? `erros.codigos.${erro.codigo.replaceAll('.', '_')}` : undefined
    if (chave && i18n.exists(chave, { ns: 'avisos' })) return t(chave as never)
    return erro.status === 403 ? t('erros.semPermissao') : t('erros.generico')
  }
}

function useRetorno(): { retorno: ReactNode; avisar: (texto: string) => void } {
  const [texto, setTexto] = useState<string | null>(null)
  return {
    avisar: setTexto,
    retorno: (
      <Snackbar open={texto !== null} autoHideDuration={6000} onClose={() => setTexto(null)} anchorOrigin={{ vertical: 'bottom', horizontal: 'center' }}>
        <Alert severity="success" role="status" variant="filled" onClose={() => setTexto(null)} sx={{ width: '100%', maxWidth: 560 }}>
          {texto}
        </Alert>
      </Snackbar>
    ),
  }
}

/** Tipo `servico.exportacao_pronta` vira chave `servico_exportacao_pronta` (o i18next separa por ponto). */
const chaveDoTipo = (tipo: string): string => tipo.replaceAll('.', '_')

function quando(iso: string, idioma: string): string {
  const data = new Date(iso)
  if (Number.isNaN(data.getTime())) return ''
  const minutos = Math.round((data.getTime() - Date.now()) / 60_000)
  const relativo = new Intl.RelativeTimeFormat(idioma, { numeric: 'auto' })
  if (Math.abs(minutos) < 60) return relativo.format(minutos, 'minute')
  if (Math.abs(minutos) < 60 * 24) return relativo.format(Math.round(minutos / 60), 'hour')
  if (Math.abs(minutos) < 60 * 24 * 7) return relativo.format(Math.round(minutos / (60 * 24)), 'day')
  return new Intl.DateTimeFormat(idioma, { dateStyle: 'short' }).format(data)
}

/** Integração com o WhatsApp (T-39): passos claros, conectar pela Meta e desconectar com confirmação e retorno. */
export function TelaWhatsapp(): ReactNode {
  const { t } = useTranslation('avisos')
  const mensagemDeErro = useMensagemDeErro()
  const [confirmando, setConfirmando] = useState(false)
  const desligar = useMutation({
    mutationFn: desligarWhatsapp,
    onSuccess: () => registrarEventoUi('ui.whatsapp_desconectado'),
  })
  const idPassos = useId()

  return (
    <PaginaDoPainel titulo={t('whatsapp.titulo')} descricao={t('whatsapp.descricao')} largura="estreita">
      {desligar.isSuccess && <EstadoSucesso titulo={t('whatsapp.desconectado')} texto={t('whatsapp.desconectadoTexto')} />}
      <Paper component="section" aria-labelledby={idPassos} variant="outlined" sx={{ p: { xs: 2, md: 3 }, borderRadius: 4, display: 'grid', gap: 2.5 }}>
        <Typography id={idPassos} component="h2" variant="h2">
          {t('whatsapp.passos')}
        </Typography>
        <Box component="ol" sx={{ m: 0, p: 0, listStyle: 'none', display: 'grid', gap: 1.5, counterReset: 'passo' }}>
          {(['passo1', 'passo2', 'passo3'] as const).map((passo) => (
            <Box
              component="li"
              key={passo}
              sx={{
                display: 'grid',
                gridTemplateColumns: '32px minmax(0, 1fr)',
                gap: 1.5,
                alignItems: 'start',
                counterIncrement: 'passo',
                '&::before': {
                  content: 'counter(passo)',
                  display: 'grid',
                  placeItems: 'center',
                  width: 32,
                  height: 32,
                  borderRadius: '50%',
                  bgcolor: 'afundado',
                  color: 'primary.main',
                  fontWeight: 600,
                },
              }}
            >
              <Typography sx={{ pt: 0.5 }}>{t(`whatsapp.${passo}`)}</Typography>
            </Box>
          ))}
        </Box>
        <Box sx={{ display: 'grid', gap: 1 }}>
          <Box>
            {/* Sai do painel de propósito: a API redireciona para a autorização da Meta. */}
            <Button variant="contained" href={URL_OAUTH}>
              {t('whatsapp.conectar')}
            </Button>
          </Box>
          <Typography variant="body2" color="text.secondary">
            {t('whatsapp.conectarTexto')}
          </Typography>
        </Box>
      </Paper>

      <Paper component="section" aria-label={t('whatsapp.gerenciar')} variant="outlined" sx={{ p: { xs: 2, md: 3 }, borderRadius: 4, display: 'grid', gap: 1.5 }}>
        <Typography component="h2" variant="h2">
          {t('whatsapp.gerenciar')}
        </Typography>
        <Typography color="text.secondary">{t('whatsapp.desconectarTexto')}</Typography>
        {desligar.isError && <Alert severity="error">{mensagemDeErro(desligar.error)}</Alert>}
        <Box>
          <Button variant="outlined" color="error" disabled={desligar.isPending} onClick={() => setConfirmando(true)}>
            {t('whatsapp.desconectar')}
          </Button>
        </Box>
      </Paper>

      <Alert severity="info">{t('whatsapp.ajuda')}</Alert>

      <ConfirmacaoIrreversivel
        aberta={confirmando}
        titulo={t('whatsapp.desconectarTitulo')}
        texto={t('whatsapp.desconectarConfirmacao')}
        rotuloConfirmar={t('whatsapp.desconectar')}
        rotuloCancelar={t('cancelar')}
        aoCancelar={() => setConfirmando(false)}
        aoConfirmar={() => {
          setConfirmando(false)
          desligar.mutate()
        }}
      />
    </PaginaDoPainel>
  )
}

/** Caixa de avisos (T-44): não lidos em destaque, ação do aviso dentro do painel e preferências de canal. */
export function TelaAvisos(): ReactNode {
  const { t, i18n } = useTranslation('avisos')
  const mensagemDeErro = useMensagemDeErro()
  const cliente = useQueryClient()
  const { retorno, avisar } = useRetorno()
  const idPreferencias = useId()
  const avisos = useQuery({ queryKey: ['avisos'], queryFn: listarAvisos, refetchInterval: 60_000 })
  const preferencias = useQuery({ queryKey: ['preferencias-avisos'], queryFn: listarPreferencias })
  const { refetch } = avisos
  useEffect(() => {
    const atualizar = (): void => {
      if (document.visibilityState === 'visible') void refetch()
    }
    document.addEventListener('visibilitychange', atualizar)
    return () => document.removeEventListener('visibilitychange', atualizar)
  }, [refetch])
  const todos = useMutation({
    mutationFn: marcarTodos,
    onSuccess: () => {
      avisar(t('caixa.todosLidos'))
      void cliente.invalidateQueries({ queryKey: ['avisos'] })
    },
  })
  const ler = useMutation({ mutationFn: marcarAviso, onSuccess: () => void cliente.invalidateQueries({ queryKey: ['avisos'] }) })
  const preferir = useMutation({
    mutationFn: ({ tipo, canal, ligado }: { tipo: string; canal: string; ligado: boolean }) => definirPreferencia(tipo, canal, ligado),
    onSuccess: () => {
      avisar(t('preferencias.salvo'))
      void cliente.invalidateQueries({ queryKey: ['preferencias-avisos'] })
    },
  })
  const naoLidos = avisos.data?.meta.nao_lidos ?? 0
  const nomeDoTipo = (tipo: string): string => t(`preferencias.tipos.${chaveDoTipo(tipo)}` as never, { defaultValue: tipo.split('.').at(-1)?.replaceAll('_', ' ') ?? tipo })

  return (
    <PaginaDoPainel
      titulo={t('caixa.titulo')}
      descricao={t('caixa.descricao')}
      largura="media"
      acoes={
        <Button variant="outlined" onClick={() => todos.mutate()} disabled={naoLidos === 0 || todos.isPending}>
          {t('caixa.marcarTodos')}
        </Button>
      }
    >
      <Box sx={{ display: 'grid', gap: 1.5 }}>
        <Typography component="h2" variant="h2" aria-live="polite">
          {t('caixa.naoLidos', { count: naoLidos })}
        </Typography>
        {avisos.isPending && <EstadoCarregando texto={t('carregando')} />}
        {avisos.isError && <EstadoErro titulo={t('erroTitulo')} texto={mensagemDeErro(avisos.error)} rotuloTentar={t('tentar')} aoTentarDeNovo={() => void avisos.refetch()} />}
        {(todos.isError || ler.isError) && <Alert severity="error">{mensagemDeErro(todos.error ?? ler.error)}</Alert>}
        {avisos.isSuccess && avisos.data.data.length === 0 && (
          <Paper variant="outlined" sx={{ borderRadius: 4 }}>
            <EstadoVazio titulo={t('caixa.vazioTitulo')} texto={t('caixa.vazioTexto')} />
          </Paper>
        )}
        {avisos.isSuccess && avisos.data.data.length > 0 && (
          <Box component="ul" aria-label={t('caixa.lista')} sx={{ listStyle: 'none', m: 0, p: 0, display: 'grid', gap: 1 }}>
            {avisos.data.data.map((aviso) => (
              <ItemAviso key={aviso.id} aviso={aviso} idioma={i18n.language} ocupado={ler.isPending} aoLer={() => ler.mutate(aviso.id)} />
            ))}
          </Box>
        )}
      </Box>

      <Paper component="section" aria-labelledby={idPreferencias} variant="outlined" sx={{ p: { xs: 2, md: 3 }, borderRadius: 4, display: 'grid', gap: 2 }}>
        <Box sx={{ display: 'grid', gap: 0.5 }}>
          <Typography id={idPreferencias} component="h2" variant="h2">
            {t('preferencias.titulo')}
          </Typography>
          <Typography color="text.secondary">{t('preferencias.texto')}</Typography>
        </Box>
        {preferencias.isPending && <EstadoCarregando texto={t('carregando')} />}
        {preferencias.isError && <EstadoErro titulo={t('erroTitulo')} texto={mensagemDeErro(preferencias.error)} rotuloTentar={t('tentar')} aoTentarDeNovo={() => void preferencias.refetch()} />}
        {preferir.isError && <Alert severity="error">{mensagemDeErro(preferir.error)}</Alert>}
        {preferencias.isSuccess && preferencias.data.data.length === 0 && <EstadoVazio titulo={t('preferencias.vazio')} texto={t('preferencias.vazioTexto')} />}
        {preferencias.isSuccess && preferencias.data.data.length > 0 && (
          <Box component="ul" sx={{ listStyle: 'none', m: 0, p: 0, display: 'grid' }}>
            {preferencias.data.data.map((preferencia, indice) => {
              const bloqueada = preferencia.obrigatorio || !preferencia.permite_escolher_canal
              return (
                <Box
                  component="li"
                  key={preferencia.tipo}
                  sx={{ py: 1.75, borderTop: indice === 0 ? 0 : 1, borderColor: 'divider', display: 'flex', flexWrap: 'wrap', gap: 1.5, alignItems: 'center', justifyContent: 'space-between' }}
                >
                  <Box sx={{ display: 'grid', gap: 0.5, flex: '1 1 14rem', minWidth: 0 }}>
                    <Typography sx={{ fontWeight: 600 }}>{nomeDoTipo(preferencia.tipo)}</Typography>
                    {bloqueada && (
                      <Typography variant="body2" color="text.secondary">
                        {preferencia.obrigatorio ? t('preferencias.obrigatorio') : t('preferencias.fixo')}
                      </Typography>
                    )}
                  </Box>
                  <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 2 }}>
                    {preferencia.canais.map((canal) => {
                      const rotuloCanal = t(`preferencias.canais.${canal.canal}` as never, { defaultValue: canal.canal })
                      return (
                        <Box component="label" key={canal.canal} sx={{ display: 'inline-flex', alignItems: 'center', gap: 0.5, cursor: bloqueada ? 'default' : 'pointer' }}>
                          <Switch
                            checked={canal.ligado}
                            disabled={bloqueada || preferir.isPending}
                            onChange={(_, ligado) => preferir.mutate({ tipo: preferencia.tipo, canal: canal.canal, ligado })}
                            slotProps={{ input: { 'aria-label': t('preferencias.canalPara', { canal: rotuloCanal, tipo: nomeDoTipo(preferencia.tipo) }) } }}
                          />
                          <Typography component="span" variant="body2">
                            {rotuloCanal}
                          </Typography>
                        </Box>
                      )
                    })}
                  </Box>
                </Box>
              )
            })}
          </Box>
        )}
      </Paper>
      {retorno}
    </PaginaDoPainel>
  )
}

function ItemAviso({ aviso, idioma, ocupado, aoLer }: { aviso: Aviso; idioma: string; ocupado: boolean; aoLer: () => void }): ReactNode {
  const { t } = useTranslation('avisos')
  const novo = aviso.lido_em === null
  const interno = aviso.destino?.startsWith(`${PREFIXO_PAINEL}/`) ? aviso.destino.slice(PREFIXO_PAINEL.length) : null
  return (
    <Paper
      component="li"
      variant="outlined"
      sx={{
        p: { xs: 2, md: 2.5 },
        borderRadius: 4,
        display: 'grid',
        gap: 1,
        borderLeft: 4,
        borderLeftColor: novo ? 'primary.main' : 'divider',
        bgcolor: novo ? 'background.paper' : 'transparent',
      }}
    >
      <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1, alignItems: 'center', justifyContent: 'space-between' }}>
        <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1, alignItems: 'center', minWidth: 0 }}>
          <Typography component="h3" variant="h3">
            {aviso.titulo}
          </Typography>
          {novo && <Chip size="small" color="primary" label={t('caixa.novo')} />}
        </Box>
        <Typography variant="body2" color="text.secondary" component="time" dateTime={aviso.criado_em}>
          {quando(aviso.criado_em, idioma)}
        </Typography>
      </Box>
      <Typography color="text.secondary">{aviso.texto}</Typography>
      <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1, pt: 0.5 }}>
        {aviso.destino && interno !== null && (
          <BotaoLink to={interno as never} size="small" variant="contained" onClick={novo ? aoLer : undefined}>
            {aviso.acao}
          </BotaoLink>
        )}
        {aviso.destino && interno === null && (
          <Button size="small" variant="contained" href={aviso.destino}>
            {aviso.acao}
          </Button>
        )}
        {novo && (
          <Button size="small" disabled={ocupado} onClick={aoLer} aria-label={t('caixa.marcarLidoDe', { titulo: aviso.titulo })}>
            {t('caixa.marcarLido')}
          </Button>
        )}
      </Box>
    </Paper>
  )
}
