import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Chip from '@mui/material/Chip'
import Dialog from '@mui/material/Dialog'
import DialogActions from '@mui/material/DialogActions'
import DialogContent from '@mui/material/DialogContent'
import DialogContentText from '@mui/material/DialogContentText'
import DialogTitle from '@mui/material/DialogTitle'
import LinearProgress from '@mui/material/LinearProgress'
import MenuItem from '@mui/material/MenuItem'
import TextField from '@mui/material/TextField'
import Typography from '@mui/material/Typography'
import { keepPreviousData, useMutation, useQuery } from '@tanstack/react-query'
import { useId, useState, useDeferredValue, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { registrarEventoUi } from '../../../shared/observabilidade'
import { BotaoLink, EstadoErro, EstadoSucesso, EstadoVazio, LinkTexto, PaginaDoPainel } from '../../../shared/ui'
import { buscarPessoas, enviarRecurso, listarRecurso, type PessoaResumo } from '../api/pessoas'
import { useMensagemDeErro } from './comum'

const MINIMO = 3

/** Busca na denominação (T-41): resultados enquanto digita; fora do alcance, pedido de transferência com retorno. */
export function TelaBuscaPessoas(): ReactNode {
  const { t } = useTranslation('pessoas')
  const mensagemDeErro = useMensagemDeErro()
  const [termo, setTermo] = useState('')
  const termoAdiado = useDeferredValue(termo.trim())
  const [transferir, setTransferir] = useState<PessoaResumo | null>(null)
  const [pedido, setPedido] = useState<string | null>(null)
  const busca = useQuery({
    queryKey: ['pessoas', 'busca', termoAdiado],
    queryFn: () => buscarPessoas(termoAdiado),
    enabled: termoAdiado.length >= MINIMO,
    placeholderData: keepPreviousData,
  })
  const resultados = termoAdiado.length >= MINIMO ? (busca.data?.data ?? []) : []

  return (
    <PaginaDoPainel titulo={t('busca.titulo')} descricao={t('busca.descricao')} largura="media" voltar={<LinkTexto to="/pessoas">{t('comum.voltarPessoas')}</LinkTexto>}>
      <Box role="search" sx={{ display: 'grid', gap: 1 }}>
        <TextField
          label={t('busca.termo')}
          value={termo}
          onChange={(evento) => setTermo(evento.target.value)}
          helperText={t('busca.ajuda')}
          autoComplete="off"
          type="search"
        />
        <Box sx={{ minHeight: 8 }}>{busca.isFetching && <LinearProgress aria-label={t('busca.buscando')} />}</Box>
      </Box>

      {pedido && <EstadoSucesso titulo={t('busca.pedidoEnviado')} texto={t('busca.pedidoEnviadoTexto', { nome: pedido })} />}
      {busca.isError && <EstadoErro titulo={t('erros.tituloCarregar')} texto={mensagemDeErro(busca.error)} rotuloTentar={t('comum.tentar')} aoTentarDeNovo={() => void busca.refetch()} />}

      {termoAdiado.length < MINIMO && <EstadoVazio titulo={t('busca.inicioTitulo')} texto={t('busca.inicioTexto')} />}
      {termoAdiado.length >= MINIMO && busca.isSuccess && resultados.length === 0 && <EstadoVazio titulo={t('busca.vazioTitulo')} texto={t('busca.vazioTexto')} />}
      {resultados.length > 0 && (
        <Box sx={{ display: 'grid', gap: 1.5 }}>
          <Typography role="status" color="text.secondary">
            {t('busca.resultados', { count: resultados.length })}
          </Typography>
          <Box component="ul" sx={{ listStyle: 'none', m: 0, p: 0, display: 'grid', gap: 1.5, gridTemplateColumns: 'repeat(auto-fill, minmax(min(100%, 18rem), 1fr))' }}>
            {resultados.map((pessoa) => {
              const abre = pessoa.meta?.acoes?.includes('abrir') ?? false
              return (
                <Card component="li" key={pessoa.uuid}>
                  <CardContent sx={{ display: 'grid', gap: 1.25, height: '100%', alignContent: 'space-between' }}>
                    <Box sx={{ display: 'grid', gap: 0.5 }}>
                      <Typography component="h2" variant="h3">
                        {pessoa.nome}
                      </Typography>
                      <Typography variant="body2" color="text.secondary">
                        {pessoa.igreja?.nome ?? t('comum.semIgreja')}
                        {pessoa.vinculo ? ` · ${t(`vinculos.${pessoa.vinculo}` as never, { defaultValue: pessoa.vinculo })}` : ''}
                      </Typography>
                      {!abre && <Chip size="small" variant="outlined" label={t('busca.foraDoAlcance')} sx={{ justifySelf: 'start' }} />}
                    </Box>
                    {abre ? (
                      <BotaoLink to="/pessoas/$uuid" params={{ uuid: pessoa.uuid }} size="small" variant="outlined" sx={{ justifySelf: 'start' }} aria-label={t('comum.abrirFichaDe', { nome: pessoa.nome })}>
                        {t('comum.abrirFicha')}
                      </BotaoLink>
                    ) : (
                      <Button size="small" variant="outlined" sx={{ justifySelf: 'start' }} onClick={() => setTransferir(pessoa)}>
                        {t('busca.pedirTransferencia')}
                      </Button>
                    )}
                  </CardContent>
                </Card>
              )
            })}
          </Box>
        </Box>
      )}

      <DialogoTransferencia
        pessoa={transferir}
        aoFechar={() => setTransferir(null)}
        aoEnviar={(nome) => {
          setTransferir(null)
          setPedido(nome)
        }}
        mensagemDeErro={mensagemDeErro}
      />
    </PaginaDoPainel>
  )
}

interface PropsTransferencia {
  pessoa: PessoaResumo | null
  aoFechar: () => void
  aoEnviar: (nome: string) => void
  mensagemDeErro: (erro: unknown) => string
}

function DialogoTransferencia({ pessoa, aoFechar, aoEnviar, mensagemDeErro }: PropsTransferencia): ReactNode {
  const { t } = useTranslation('pessoas')
  const idTitulo = useId()
  const [destino, setDestino] = useState('')
  const igrejas = useQuery({ queryKey: ['pessoas', 'igrejas'], queryFn: () => listarRecurso('/painel/igrejas'), staleTime: 60_000, enabled: pessoa !== null })
  const pedir = useMutation({
    mutationFn: () => enviarRecurso('POST', '/painel/transferencias', { pessoa: pessoa?.uuid, igreja_destino: destino }),
    onSuccess: () => {
      registrarEventoUi('ui.transferencia_pedida')
      aoEnviar(pessoa?.nome ?? '')
      setDestino('')
    },
  })
  const fechar = (): void => {
    pedir.reset()
    setDestino('')
    aoFechar()
  }
  return (
    <Dialog open={pessoa !== null} onClose={fechar} fullWidth maxWidth="sm" aria-labelledby={idTitulo}>
      <DialogTitle id={idTitulo}>{t('busca.transferenciaTitulo', { nome: pessoa?.nome ?? '' })}</DialogTitle>
      <DialogContent sx={{ display: 'grid', gap: 2.5 }}>
        <DialogContentText>{t('busca.transferenciaTexto')}</DialogContentText>
        <TextField select required label={t('busca.igrejaDestino')} value={destino} onChange={(evento) => setDestino(evento.target.value)} disabled={igrejas.isPending}>
          {(igrejas.data?.data ?? [])
            .filter((igreja) => igreja.uuid !== pessoa?.igreja?.uuid)
            .map((igreja) => (
              <MenuItem key={igreja.uuid} value={igreja.uuid}>
                {String(igreja.nome ?? '')}
              </MenuItem>
            ))}
        </TextField>
        {pedir.isError && <Alert severity="error">{mensagemDeErro(pedir.error)}</Alert>}
      </DialogContent>
      <DialogActions sx={{ px: 3, pb: 2.5 }}>
        <Button onClick={fechar}>{t('comum.cancelar')}</Button>
        <Button variant="contained" disabled={!destino || pedir.isPending} onClick={() => pedir.mutate()}>
          {t('busca.enviarPedido')}
        </Button>
      </DialogActions>
    </Dialog>
  )
}
