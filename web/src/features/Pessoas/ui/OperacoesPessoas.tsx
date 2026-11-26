import Alert from '@mui/material/Alert'
import AlertTitle from '@mui/material/AlertTitle'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Chip from '@mui/material/Chip'
import FormControl from '@mui/material/FormControl'
import FormControlLabel from '@mui/material/FormControlLabel'
import FormLabel from '@mui/material/FormLabel'
import Paper from '@mui/material/Paper'
import Radio from '@mui/material/Radio'
import RadioGroup from '@mui/material/RadioGroup'
import Tab from '@mui/material/Tab'
import Tabs from '@mui/material/Tabs'
import TextField from '@mui/material/TextField'
import Typography from '@mui/material/Typography'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate, useSearch } from '@tanstack/react-router'
import { useId, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { registrarEventoUi } from '../../../shared/observabilidade'
import { ConfirmacaoIrreversivel, EstadoCarregando, EstadoErro, EstadoVazio, LinkTexto, PaginaDoPainel } from '../../../shared/ui'
import { enviarRecurso, listarRecurso, obterFicha, type RegistroGenerico } from '../api/pessoas'
import { formatarData, nomeDoCampo, useFormatarValor, useMensagemDeErro } from './comum'
import { useRetorno } from './Retorno'

interface PessoaDoPar {
  uuid: string
  nome: string
  nascimento?: string | null
  vinculo?: string
  igreja?: { nome?: string }
}
interface ParDuplicado extends RegistroGenerico {
  pessoas: PessoaDoPar[]
  criterios: string[]
  meta: { acoes: string[] }
}
/** `GET /painel/transferencias` traz a pessoa achatada; `pessoa`/`acoes` ficam por compatibilidade. */
interface Transferencia extends RegistroGenerico {
  pessoa_nome?: string
  pessoa_uuid?: string
  pessoa?: { uuid: string; nome: string }
  estado: string
  justificativa?: string | null
  created_at?: string
  acoes?: { decidir?: boolean }
}
interface ItemLixeira extends RegistroGenerico {
  nome: string
  igreja?: { nome?: string } | null
  motivo: string
  excluida_em: string
  recuperavel_ate: string
  meta: { acoes: string[] }
}
interface Mesclagem {
  operacao: string
  mantida: string
  absorvida: string
}

type Aba = 'duplicados' | 'transferencias' | 'lixeira'
const ABAS: readonly Aba[] = ['duplicados', 'transferencias', 'lixeira']
const CHAVE = ['operacoes-pessoas'] as const

interface Props {
  /** A pessoa tem `transferencias.aprovar` em algum escopo (o servidor decide cada pedido). */
  podeDecidir?: boolean
}

/** Duplicados, transferências e lixeira (T-43): aba na URL, confirmação antes de mesclar e retorno de cada ação. */
export function TelaOperacoesPessoasCompleta({ podeDecidir = false }: Props): ReactNode {
  const { t } = useTranslation('pessoas')
  const navegar = useNavigate()
  const busca = useSearch({ strict: false }) as { aba?: unknown }
  const aba: Aba = ABAS.includes(busca.aba as Aba) ? (busca.aba as Aba) : 'duplicados'
  const idAbas = useId()
  // A mesclagem feita fica aqui, fora da lista: recarregar os pares não some com o "Desfazer".
  const [mesclagem, setMesclagem] = useState<Mesclagem | null>(null)

  return (
    <PaginaDoPainel titulo={t('operacoes.titulo')} descricao={t('operacoes.descricao')}>
      <Tabs
        value={aba}
        onChange={(_, nova: Aba) => void navegar({ to: '.', search: ((anterior: Record<string, unknown>) => ({ ...anterior, aba: nova === 'duplicados' ? undefined : nova })) as never, replace: true })}
        variant="scrollable"
        allowScrollButtonsMobile
        aria-label={t('operacoes.abas')}
      >
        {ABAS.map((item) => (
          <Tab key={item} value={item} label={t(`operacoes.${item}`)} id={`${idAbas}-${item}`} aria-controls={`${idAbas}-painel`} />
        ))}
      </Tabs>
      <Box role="tabpanel" id={`${idAbas}-painel`} aria-labelledby={`${idAbas}-${aba}`} sx={{ minWidth: 0 }}>
        {aba === 'duplicados' && <Duplicados mesclagem={mesclagem} aoMesclar={setMesclagem} />}
        {aba === 'transferencias' && <Transferencias podeDecidir={podeDecidir} />}
        {aba === 'lixeira' && <Lixeira />}
      </Box>
    </PaginaDoPainel>
  )
}

function Duplicados({ mesclagem, aoMesclar }: { mesclagem: Mesclagem | null; aoMesclar: (mesclagem: Mesclagem | null) => void }): ReactNode {
  const { t } = useTranslation('pessoas')
  const mensagemDeErro = useMensagemDeErro()
  const cliente = useQueryClient()
  const { retorno, avisar } = useRetorno()
  const consulta = useQuery({ queryKey: [...CHAVE, 'duplicados'], queryFn: () => listarRecurso('/painel/duplicados') })
  const atualizar = (): void => void cliente.invalidateQueries({ queryKey: [...CHAVE, 'duplicados'] })
  const desfazer = useMutation({
    mutationFn: (operacao: string) => enviarRecurso<{ conflitos?: string[] }>('POST', `/painel/mesclagens/${operacao}/desfazer`),
    onSuccess: (resposta) => {
      const conflitos = (resposta.conflitos ?? []).map((campo) => t(`campos.${nomeDoCampo(campo)}` as never, { defaultValue: campo }))
      avisar(conflitos.length > 0 ? t('operacoes.desfeitaConflitos', { campos: conflitos.join(', ') }) : t('operacoes.desfeita'))
      aoMesclar(null)
      atualizar()
    },
  })

  return (
    <Box sx={{ display: 'grid', gap: 2 }}>
      {mesclagem && (
        <Alert
          severity="success"
          role="status"
          action={
            <Button color="inherit" size="small" variant="outlined" disabled={desfazer.isPending} onClick={() => desfazer.mutate(mesclagem.operacao)}>
              {t('operacoes.desfazer')}
            </Button>
          }
          sx={{ flexWrap: 'wrap', '& .MuiAlert-action': { ml: { xs: 0, sm: 'auto' }, pl: { xs: 0, sm: 2 } } }}
        >
          <AlertTitle>{t('operacoes.mescladas')}</AlertTitle>
          {t('operacoes.mescladasTexto', { mantida: mesclagem.mantida, absorvida: mesclagem.absorvida })}
        </Alert>
      )}
      {desfazer.isError && <Alert severity="error">{mensagemDeErro(desfazer.error)}</Alert>}
      {consulta.isPending && <EstadoCarregando texto={t('operacoes.carregandoDuplicados')} />}
      {consulta.isError && <EstadoErro titulo={t('erros.tituloCarregar')} texto={mensagemDeErro(consulta.error)} rotuloTentar={t('comum.tentar')} aoTentarDeNovo={() => void consulta.refetch()} />}
      {consulta.isSuccess && consulta.data.data.length === 0 && (
        <Paper variant="outlined" sx={{ borderRadius: 4 }}>
          <EstadoVazio titulo={t('operacoes.vazioDuplicados')} texto={t('operacoes.vazioDuplicadosTexto')} />
        </Paper>
      )}
      {consulta.isSuccess &&
        (consulta.data.data as ParDuplicado[]).map((par) => (
          <ComparacaoDuplicado
            key={par.pessoas.map((pessoa) => pessoa.uuid).join('-')}
            par={par}
            aoMesclar={(nova) => {
              aoMesclar(nova)
              atualizar()
            }}
            aoSeparar={(texto) => {
              avisar(texto)
              atualizar()
            }}
          />
        ))}
      {retorno}
    </Box>
  )
}

interface PropsComparacao {
  par: ParDuplicado
  aoMesclar: (mesclagem: Mesclagem) => void
  aoSeparar: (texto: string) => void
}

function ComparacaoDuplicado({ par, aoMesclar, aoSeparar }: PropsComparacao): ReactNode {
  const { t, i18n } = useTranslation('pessoas')
  const mensagemDeErro = useMensagemDeErro()
  const formatar = useFormatarValor()
  const idTitulo = useId()
  const [mantida, absorvida] = par.pessoas
  const [escolhas, setEscolhas] = useState<Record<string, 'mantida' | 'absorvida'>>({})
  const [confirmando, setConfirmando] = useState(false)
  const podeMesclar = par.meta.acoes.includes('mesclar')
  const podeSeparar = par.meta.acoes.includes('nao_duplicadas')
  const fichaMantida = useQuery({ queryKey: ['comparacao-mesclagem', mantida?.uuid], queryFn: () => obterFicha(mantida?.uuid ?? ''), enabled: Boolean(mantida) && podeMesclar })
  const fichaAbsorvida = useQuery({ queryKey: ['comparacao-mesclagem', absorvida?.uuid], queryFn: () => obterFicha(absorvida?.uuid ?? ''), enabled: Boolean(absorvida) && podeMesclar })
  const mesclar = useMutation({
    mutationFn: () => enviarRecurso<{ operacao: string }>('POST', '/painel/mesclagens', { mantida: mantida?.uuid, absorvida: absorvida?.uuid, valores: escolhas }),
    onSuccess: (resposta) => {
      registrarEventoUi('ui.pessoas_mescladas', { campos_escolhidos: Object.keys(escolhas).length })
      aoMesclar({ operacao: resposta.operacao, mantida: mantida?.nome ?? '', absorvida: absorvida?.nome ?? '' })
    },
  })
  const separar = useMutation({
    mutationFn: () => enviarRecurso('POST', '/painel/duplicados/nao-duplicados', { pessoa: mantida?.uuid, outra: absorvida?.uuid }),
    onSuccess: () => aoSeparar(t('operacoes.marcadasDiferentes', { a: mantida?.nome ?? '', b: absorvida?.nome ?? '' })),
  })
  if (!mantida || !absorvida) return null

  const camposMantida = fichaMantida.data?.data.campos ?? {}
  const camposAbsorvida = fichaAbsorvida.data?.data.campos ?? {}
  const divergentes = [...new Set([...Object.keys(camposMantida), ...Object.keys(camposAbsorvida)])].filter(
    (campo) => nomeDoCampo(campo) !== 'foto_path' && JSON.stringify(camposMantida[campo]) !== JSON.stringify(camposAbsorvida[campo]),
  )
  const comparando = podeMesclar && (fichaMantida.isPending || fichaAbsorvida.isPending)
  const criterios = par.criterios.map((criterio) => t(`operacoes.criterio.${criterio}` as never, { defaultValue: criterio.replaceAll('_', ' ') })).join(', ')
  const ocupado = mesclar.isPending || separar.isPending

  return (
    <Paper component="section" aria-labelledby={idTitulo} variant="outlined" sx={{ p: { xs: 2, md: 3 }, borderRadius: 4, display: 'grid', gap: 2.5 }}>
      <Box sx={{ display: 'grid', gap: 0.5 }}>
        <Typography id={idTitulo} component="h2" variant="h2">
          {mantida.nome} × {absorvida.nome}
        </Typography>
        <Typography variant="body2" color="text.secondary">
          {t('operacoes.criterios', { criterios })}
        </Typography>
      </Box>

      <Box sx={{ display: 'grid', gap: 1.5, gridTemplateColumns: { xs: 'minmax(0, 1fr)', sm: 'repeat(2, minmax(0, 1fr))' } }}>
        {[
          { pessoa: mantida, papel: t('operacoes.mantida') },
          { pessoa: absorvida, papel: t('operacoes.absorvida') },
        ].map(({ pessoa, papel }) => (
          <Box key={pessoa.uuid} sx={{ p: 2, borderRadius: 3, bgcolor: 'afundado', display: 'grid', gap: 0.5, minWidth: 0 }}>
            <Typography variant="overline" color="text.secondary">
              {papel}
            </Typography>
            <LinkTexto to="/pessoas/$uuid" params={{ uuid: pessoa.uuid }} sx={{ fontWeight: 600, justifySelf: 'start' }}>
              {pessoa.nome}
            </LinkTexto>
            <Typography variant="body2" color="text.secondary">
              {pessoa.igreja?.nome ?? t('comum.semIgreja')}
              {pessoa.vinculo ? ` · ${t(`vinculos.${pessoa.vinculo}` as never, { defaultValue: pessoa.vinculo })}` : ''}
            </Typography>
            <Typography variant="body2" color="text.secondary">
              {`${t('operacoes.nascimento')}: ${pessoa.nascimento ? formatarData(pessoa.nascimento, i18n.language) : t('comum.naoInformado')}`}
            </Typography>
          </Box>
        ))}
      </Box>

      {comparando && <EstadoCarregando texto={t('operacoes.comparando')} />}
      {podeMesclar && !comparando && divergentes.length === 0 && (
        <Typography variant="body2" color="text.secondary">
          {t('operacoes.semDivergencias')}
        </Typography>
      )}
      {podeMesclar && !comparando && divergentes.length > 0 && (
        <Box sx={{ display: 'grid', gap: 2 }}>
          <Typography component="h3" variant="h3">
            {t('operacoes.escolhaValores')}
          </Typography>
          {divergentes.map((campo) => (
            <FormControl key={campo} component="fieldset" sx={{ p: 2, borderRadius: 3, border: 1, borderColor: 'divider', minWidth: 0 }}>
              <FormLabel component="legend" sx={{ px: 0.5 }}>
                {t(`campos.${nomeDoCampo(campo)}` as never, { defaultValue: nomeDoCampo(campo) })}
              </FormLabel>
              <RadioGroup
                value={escolhas[campo] ?? 'mantida'}
                onChange={(evento) => setEscolhas((atuais) => ({ ...atuais, [campo]: evento.target.value as 'mantida' | 'absorvida' }))}
                sx={{ flexDirection: { xs: 'column', md: 'row' }, columnGap: 3 }}
              >
                <FormControlLabel value="mantida" control={<Radio />} label={`${mantida.nome}: ${formatar(camposMantida[campo])}`} />
                <FormControlLabel value="absorvida" control={<Radio />} label={`${absorvida.nome}: ${formatar(camposAbsorvida[campo])}`} />
              </RadioGroup>
            </FormControl>
          ))}
        </Box>
      )}

      {mesclar.isError && <Alert severity="error">{mensagemDeErro(mesclar.error)}</Alert>}
      {separar.isError && <Alert severity="error">{mensagemDeErro(separar.error)}</Alert>}

      {(podeMesclar || podeSeparar) && (
        <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1 }}>
          {podeMesclar && (
            <Button variant="contained" disabled={ocupado || comparando} onClick={() => setConfirmando(true)}>
              {t('operacoes.mesclar')}
            </Button>
          )}
          {podeSeparar && (
            <Button variant="outlined" disabled={ocupado} onClick={() => separar.mutate()}>
              {t('operacoes.naoDuplicadas')}
            </Button>
          )}
        </Box>
      )}

      <ConfirmacaoIrreversivel
        aberta={confirmando}
        titulo={t('operacoes.confirmarMesclarTitulo')}
        texto={t('operacoes.confirmarMesclarTexto', { mantida: mantida.nome, absorvida: absorvida.nome })}
        rotuloConfirmar={t('operacoes.confirmarMesclar')}
        rotuloCancelar={t('comum.cancelar')}
        aoCancelar={() => setConfirmando(false)}
        aoConfirmar={() => {
          setConfirmando(false)
          mesclar.mutate()
        }}
      />
    </Paper>
  )
}

function Transferencias({ podeDecidir }: { podeDecidir: boolean }): ReactNode {
  const { t, i18n } = useTranslation('pessoas')
  const mensagemDeErro = useMensagemDeErro()
  const cliente = useQueryClient()
  const { retorno, avisar } = useRetorno()
  const [justificativas, setJustificativas] = useState<Record<string, string>>({})
  const consulta = useQuery({ queryKey: [...CHAVE, 'transferencias'], queryFn: () => listarRecurso('/painel/transferencias') })
  const decidir = useMutation({
    mutationFn: ({ uuid, decisao }: { uuid: string; decisao: 'aprovar' | 'recusar'; nome: string }) =>
      enviarRecurso('POST', `/painel/transferencias/${uuid}/${decisao}`, decisao === 'recusar' ? { justificativa: justificativas[uuid] ?? '' } : undefined),
    onSuccess: (_, { decisao, nome }) => {
      registrarEventoUi('ui.transferencia_decidida', { decisao })
      avisar(decisao === 'aprovar' ? t('operacoes.aprovada', { nome }) : t('operacoes.recusada', { nome }))
      void cliente.invalidateQueries({ queryKey: [...CHAVE, 'transferencias'] })
    },
  })

  if (consulta.isPending) return <EstadoCarregando texto={t('operacoes.carregandoTransferencias')} />
  if (consulta.isError) return <EstadoErro titulo={t('erros.tituloCarregar')} texto={mensagemDeErro(consulta.error)} rotuloTentar={t('comum.tentar')} aoTentarDeNovo={() => void consulta.refetch()} />
  const itens = consulta.data.data as Transferencia[]
  if (itens.length === 0) {
    return (
      <Paper variant="outlined" sx={{ borderRadius: 4 }}>
        <EstadoVazio titulo={t('operacoes.vazioTransferencias')} texto={t('operacoes.vazioTransferenciasTexto')} />
      </Paper>
    )
  }
  return (
    <Box sx={{ display: 'grid', gap: 1.5 }}>
      {decidir.isError && <Alert severity="error">{mensagemDeErro(decidir.error)}</Alert>}
      <Box component="ul" sx={{ listStyle: 'none', m: 0, p: 0, display: 'grid', gap: 1.5 }}>
        {itens.map((item) => {
          const nome = item.pessoa_nome ?? item.pessoa?.nome ?? t('comum.naoInformado')
          const pessoaUuid = item.pessoa_uuid ?? item.pessoa?.uuid
          const pendente = item.estado === 'solicitada'
          const decide = pendente && (item.acoes?.decidir ?? podeDecidir)
          return (
            <Paper component="li" key={item.uuid} variant="outlined" sx={{ p: { xs: 2, md: 2.5 }, borderRadius: 4, display: 'grid', gap: 1.5 }}>
              <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1, alignItems: 'center', justifyContent: 'space-between' }}>
                <Box sx={{ display: 'grid', gap: 0.25, minWidth: 0 }}>
                  {pessoaUuid ? (
                    <LinkTexto to="/pessoas/$uuid" params={{ uuid: pessoaUuid }} sx={{ fontWeight: 600 }}>
                      {nome}
                    </LinkTexto>
                  ) : (
                    <Typography sx={{ fontWeight: 600 }}>{nome}</Typography>
                  )}
                  {item.created_at && (
                    <Typography variant="body2" color="text.secondary">
                      {t('operacoes.pedidoEm', { data: formatarData(item.created_at, i18n.language) })}
                    </Typography>
                  )}
                </Box>
                <Chip
                  size="small"
                  variant="outlined"
                  color={item.estado === 'aprovada' ? 'success' : item.estado === 'recusada' ? 'error' : 'warning'}
                  label={t(`operacoes.estadoTransferencia.${item.estado}` as never, { defaultValue: item.estado })}
                />
              </Box>
              {item.justificativa && (
                <Typography variant="body2">
                  <Box component="span" sx={{ color: 'text.secondary' }}>{`${t('operacoes.justificativa')}: `}</Box>
                  {item.justificativa}
                </Typography>
              )}
              {decide && (
                <Box sx={{ display: 'grid', gap: 1.5 }}>
                  <TextField
                    size="small"
                    label={t('operacoes.justificativaRecusa')}
                    helperText={t('operacoes.justificativaAjuda')}
                    value={justificativas[item.uuid] ?? ''}
                    onChange={(evento) => setJustificativas((atual) => ({ ...atual, [item.uuid]: evento.target.value }))}
                  />
                  <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1 }}>
                    <Button variant="contained" disabled={decidir.isPending} onClick={() => decidir.mutate({ uuid: item.uuid, decisao: 'aprovar', nome })}>
                      {t('operacoes.aprovar')}
                    </Button>
                    <Button
                      variant="outlined"
                      color="error"
                      disabled={decidir.isPending || !justificativas[item.uuid]?.trim()}
                      onClick={() => decidir.mutate({ uuid: item.uuid, decisao: 'recusar', nome })}
                    >
                      {t('operacoes.recusar')}
                    </Button>
                  </Box>
                </Box>
              )}
            </Paper>
          )
        })}
      </Box>
      {retorno}
    </Box>
  )
}

function Lixeira(): ReactNode {
  const { t, i18n } = useTranslation('pessoas')
  const mensagemDeErro = useMensagemDeErro()
  const cliente = useQueryClient()
  const { retorno, avisar } = useRetorno()
  const consulta = useQuery({ queryKey: [...CHAVE, 'lixeira'], queryFn: () => listarRecurso('/painel/lixeira') })
  const recuperar = useMutation({
    mutationFn: ({ uuid }: { uuid: string; nome: string }) => enviarRecurso('POST', `/painel/lixeira/${uuid}/recuperar`),
    onSuccess: (_, { nome }) => {
      registrarEventoUi('ui.pessoa_recuperada')
      avisar(t('operacoes.recuperada', { nome }))
      void cliente.invalidateQueries({ queryKey: [...CHAVE, 'lixeira'] })
      void cliente.invalidateQueries({ queryKey: ['pessoas', 'lista'] })
    },
  })

  if (consulta.isPending) return <EstadoCarregando texto={t('operacoes.carregandoLixeira')} />
  if (consulta.isError) return <EstadoErro titulo={t('erros.tituloCarregar')} texto={mensagemDeErro(consulta.error)} rotuloTentar={t('comum.tentar')} aoTentarDeNovo={() => void consulta.refetch()} />
  const itens = consulta.data.data as ItemLixeira[]
  if (itens.length === 0) {
    return (
      <Paper variant="outlined" sx={{ borderRadius: 4 }}>
        <EstadoVazio titulo={t('operacoes.vazioLixeira')} texto={t('operacoes.vazioLixeiraTexto')} />
      </Paper>
    )
  }
  return (
    <Box sx={{ display: 'grid', gap: 1.5 }}>
      {recuperar.isError && <Alert severity="error">{mensagemDeErro(recuperar.error)}</Alert>}
      <Box component="ul" sx={{ listStyle: 'none', m: 0, p: 0, display: 'grid', gap: 1.5, gridTemplateColumns: 'repeat(auto-fill, minmax(min(100%, 20rem), 1fr))' }}>
        {itens.map((item) => {
          const recuperavel = item.meta.acoes.includes('recuperar')
          return (
            <Paper component="li" key={item.uuid} variant="outlined" sx={{ p: { xs: 2, md: 2.5 }, borderRadius: 4, display: 'grid', gap: 1, alignContent: 'space-between' }}>
              <Box sx={{ display: 'grid', gap: 0.5 }}>
                <Typography component="h2" variant="h3">
                  {item.nome}
                </Typography>
                <Typography variant="body2" color="text.secondary">
                  {item.igreja?.nome ?? t('comum.semIgreja')}
                </Typography>
                <Typography variant="body2">{`${t('operacoes.motivoExclusao')}: ${item.motivo}`}</Typography>
                <Typography variant="body2" color="text.secondary">
                  {t('operacoes.excluidaEm', { data: formatarData(item.excluida_em, i18n.language) })}
                </Typography>
                {recuperavel ? (
                  <Typography variant="body2" color="text.secondary">
                    {t('operacoes.recuperavelAte', { data: formatarData(item.recuperavel_ate, i18n.language) })}
                  </Typography>
                ) : (
                  <Chip size="small" variant="outlined" label={t('operacoes.prazoVencido')} sx={{ justifySelf: 'start' }} />
                )}
              </Box>
              {recuperavel && (
                <Button
                  variant="outlined"
                  size="small"
                  sx={{ justifySelf: 'start', mt: 1 }}
                  disabled={recuperar.isPending}
                  aria-label={t('operacoes.recuperarDe', { nome: item.nome })}
                  onClick={() => recuperar.mutate({ uuid: item.uuid, nome: item.nome })}
                >
                  {t('operacoes.recuperar')}
                </Button>
              )}
            </Paper>
          )
        })}
      </Box>
      {retorno}
    </Box>
  )
}
