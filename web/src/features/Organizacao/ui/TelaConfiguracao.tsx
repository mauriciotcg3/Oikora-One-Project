import Alert from '@mui/material/Alert'
import Autocomplete from '@mui/material/Autocomplete'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Checkbox from '@mui/material/Checkbox'
import Chip from '@mui/material/Chip'
import FormControl from '@mui/material/FormControl'
import FormControlLabel from '@mui/material/FormControlLabel'
import FormLabel from '@mui/material/FormLabel'
import MenuItem from '@mui/material/MenuItem'
import Paper from '@mui/material/Paper'
import Radio from '@mui/material/Radio'
import RadioGroup from '@mui/material/RadioGroup'
import Snackbar from '@mui/material/Snackbar'
import Tab from '@mui/material/Tab'
import Tabs from '@mui/material/Tabs'
import TextField from '@mui/material/TextField'
import ToggleButton from '@mui/material/ToggleButton'
import ToggleButtonGroup from '@mui/material/ToggleButtonGroup'
import Typography from '@mui/material/Typography'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate, useSearch } from '@tanstack/react-router'
import { useId, useMemo, useState, type ChangeEvent, type FormEvent, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { ErroHttp } from '../../../shared/http'
import { registrarEventoUi } from '../../../shared/observabilidade'
import { corLegivel, EstadoCarregando, EstadoErro, EstadoVazio, PaginaDoPainel } from '../../../shared/ui'
import {
  criarIgreja,
  criarValor,
  editarIgreja,
  editarValor,
  listarIgrejas,
  listarValores,
  obterObrigatorios,
  obterOrganizacao,
  salvarObrigatorios,
  salvarOrganizacao,
  type Igreja,
  type ItemLista,
  type Organizacao,
} from '../api/configuracao'

type Aba = 'organizacao' | 'igrejas' | 'listas' | 'obrigatorios'
const ABAS: readonly Aba[] = ['organizacao', 'igrejas', 'listas', 'obrigatorios']
const LISTAS = ['como_chegou', 'motivo_vinculo', 'motivo_inativacao', 'motivo_reativacao', 'tipo_membro', 'funcao'] as const
const VINCULOS = ['visitante', 'frequentador', 'membro'] as const
const COR_HEX = /^#[0-9A-Fa-f]{6}$/
const COR_INICIAL = '#5D4432'

/** Campos da ficha que podem ser obrigatórios, por seção (igual à `CampoFicha` da API). */
const CAMPOS_POR_SECAO: readonly (readonly [string, readonly string[]])[] = [
  ['pessoais', ['nome', 'nascimento', 'sexo', 'foto_path', 'estado_civil', 'data_casamento']],
  ['documentos', ['cpf', 'rg', 'rg_pais']],
  ['contatos', ['celular', 'e_whatsapp', 'telefone', 'email']],
  ['endereco', ['cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'estado', 'pais']],
  ['saude', ['necessidades_especiais', 'tipo_sanguineo']],
  ['eclesiasticos', ['aceitou_jesus', 'conversao_data', 'conversao_canal', 'batismo_data', 'batizado_por', 'igreja_origem', 'e_lider', 'e_pastor', 'novo_convertido']],
  ['adicionais', ['escolaridade', 'profissao', 'nacionalidade', 'naturalidade']],
]

/** Texto do erro para a pessoa: código conhecido traduzido; senão, pelo status. Nunca o código cru. */
function useMensagemDeErro(): (erro: unknown) => string {
  const { t, i18n } = useTranslation('organizacao')
  return (erro) => {
    if (!(erro instanceof ErroHttp)) return t('erros.generico')
    const codigo = erro.codigo ?? Object.values(erro.corpo.errors ?? {}).flat()[0]
    const chave = codigo ? `erros.codigos.${codigo.replaceAll('.', '_')}` : undefined
    if (chave && i18n.exists(chave, { ns: 'organizacao' })) return t(chave as never)
    if (erro.status === 403) return t('erros.semPermissao')
    if (erro.status === 409) return t('erros.conflito')
    if (erro.status === 422) return t('erros.validacao')
    return t('erros.generico')
  }
}

/** Retorno curto depois de salvar (anunciado como `status`, some sozinho). */
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

function Secao({ titulo, texto, children }: { titulo: string; texto?: string; children: ReactNode }): ReactNode {
  const id = useId()
  return (
    <Paper component="section" aria-labelledby={id} variant="outlined" sx={{ p: { xs: 2, md: 3 }, borderRadius: 4, display: 'grid', gap: 2.5 }}>
      <Box sx={{ display: 'grid', gap: 0.5 }}>
        <Typography id={id} component="h2" variant="h2">
          {titulo}
        </Typography>
        {texto && <Typography color="text.secondary">{texto}</Typography>}
      </Box>
      {children}
    </Paper>
  )
}

/** Configuração da organização (T-39): abas na URL, formulários com retorno e nada de nome interno na tela. */
export function TelaConfiguracao(): ReactNode {
  const { t } = useTranslation('organizacao')
  const navegar = useNavigate()
  const busca = useSearch({ strict: false }) as { aba?: unknown }
  const aba: Aba = ABAS.includes(busca.aba as Aba) ? (busca.aba as Aba) : 'organizacao'
  const idAbas = useId()
  const organizacao = useQuery({ queryKey: ['organizacao-config'], queryFn: obterOrganizacao })

  return (
    <PaginaDoPainel titulo={t('titulo')} descricao={t('descricao')} largura="media">
      <Tabs
        value={aba}
        onChange={(_, nova: Aba) => void navegar({ to: '.', search: ((anterior: Record<string, unknown>) => ({ ...anterior, aba: nova === 'organizacao' ? undefined : nova })) as never, replace: true })}
        variant="scrollable"
        allowScrollButtonsMobile
        aria-label={t('abas')}
      >
        {ABAS.map((item) => (
          <Tab key={item} value={item} label={t(`aba.${item}`)} id={`${idAbas}-${item}`} aria-controls={`${idAbas}-painel`} />
        ))}
      </Tabs>
      <Box role="tabpanel" id={`${idAbas}-painel`} aria-labelledby={`${idAbas}-${aba}`} sx={{ minWidth: 0 }}>
        {aba === 'organizacao' && (
          <>
            {organizacao.isPending && <EstadoCarregando texto={t('carregando')} />}
            {organizacao.isError && <EstadoErro titulo={t('indisponivel')} texto={t('erros.carregar')} rotuloTentar={t('tentar')} aoTentarDeNovo={() => void organizacao.refetch()} />}
            {organizacao.isSuccess && <FormularioOrganizacao inicial={organizacao.data.data} />}
          </>
        )}
        {aba === 'igrejas' && <EditorIgrejas />}
        {aba === 'listas' && <EditorListas />}
        {aba === 'obrigatorios' && <EditorObrigatorios />}
      </Box>
    </PaginaDoPainel>
  )
}

function fusos(): readonly string[] {
  try {
    return Intl.supportedValuesOf('timeZone')
  } catch {
    return ['America/Sao_Paulo']
  }
}

function FormularioOrganizacao({ inicial }: { inicial: Organizacao }): ReactNode {
  const { t } = useTranslation('organizacao')
  const mensagemDeErro = useMensagemDeErro()
  const cliente = useQueryClient()
  const { retorno, avisar } = useRetorno()
  const listaDeFusos = useMemo(() => fusos(), [])
  const [form, setForm] = useState({
    nome: inicial.nome,
    fuso: inicial.fuso,
    cor_primaria: inicial.cor_primaria ?? '',
    modo_matricula: inicial.modo_matricula,
    limite_whatsapp_mensal: inicial.limite_whatsapp_mensal?.toString() ?? '',
    email_contato: inicial.email_contato ?? '',
  })
  const corValida = form.cor_primaria === '' || COR_HEX.test(form.cor_primaria)
  const salvar = useMutation({
    mutationFn: () =>
      salvarOrganizacao({
        ...form,
        cor_primaria: form.cor_primaria || null,
        limite_whatsapp_mensal: form.limite_whatsapp_mensal === '' ? null : Number(form.limite_whatsapp_mensal),
        email_contato: form.email_contato || null,
      }),
    onSuccess: () => {
      registrarEventoUi('ui.organizacao_salva')
      avisar(t('organizacao.sucesso'))
      // O shell relê a organização: nome e cor novos aparecem sem recarregar.
      void cliente.invalidateQueries({ queryKey: ['organizacao'] })
      void cliente.invalidateQueries({ queryKey: ['organizacao-config'] })
      void cliente.invalidateQueries({ queryKey: ['me'] })
    },
  })
  const erros = salvar.error instanceof ErroHttp ? (salvar.error.corpo.errors ?? {}) : {}
  const erroCampo = (campo: string): string | undefined => {
    const codigo = erros[campo]?.[0]
    if (!codigo) return undefined
    return t(`erros.codigos.${codigo.replaceAll('.', '_')}` as never, { defaultValue: t('erros.validacao') })
  }
  const enviar = (evento: FormEvent): void => {
    evento.preventDefault()
    if (corValida) salvar.mutate()
  }
  const previaClara = corLegivel(form.cor_primaria, 'light')
  const previaEscura = corLegivel(form.cor_primaria, 'dark')

  return (
    <Box component="form" onSubmit={enviar} noValidate sx={{ display: 'grid', gap: 2.5 }}>
      <Secao titulo={t('organizacao.identidade')} texto={t('organizacao.identidadeTexto')}>
        <TextField required label={t('organizacao.nome')} value={form.nome} onChange={(e) => setForm({ ...form, nome: e.target.value })} error={Boolean(erroCampo('nome'))} helperText={erroCampo('nome')} />
        <Box sx={{ display: 'grid', gap: 1.5 }}>
          <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1.5, alignItems: 'flex-start' }}>
            <TextField
              label={t('organizacao.cor')}
              value={form.cor_primaria}
              onChange={(e) => setForm({ ...form, cor_primaria: e.target.value.trim() })}
              error={!corValida || Boolean(erroCampo('cor_primaria'))}
              helperText={!corValida ? t('erros.codigos.cor_invalida') : (erroCampo('cor_primaria') ?? t('organizacao.corAjuda'))}
              sx={{ flex: '1 1 16rem' }}
              slotProps={{
                input: {
                  startAdornment: (
                    <Box
                      component="input"
                      type="color"
                      aria-label={t('organizacao.corEscolher')}
                      value={COR_HEX.test(form.cor_primaria) ? form.cor_primaria : COR_INICIAL}
                      onChange={(e: ChangeEvent<HTMLInputElement>) => setForm({ ...form, cor_primaria: e.target.value.toUpperCase() })}
                      sx={{ width: 32, height: 32, p: 0, mr: 1.5, border: 1, borderColor: 'divider', borderRadius: 1, bgcolor: 'transparent', cursor: 'pointer', flexShrink: 0 }}
                    />
                  ),
                },
              }}
            />
            {form.cor_primaria !== '' && (
              <Button variant="text" onClick={() => setForm({ ...form, cor_primaria: '' })} sx={{ mt: 0.75 }}>
                {t('organizacao.corPadrao')}
              </Button>
            )}
          </Box>
          {corValida && form.cor_primaria !== '' && previaClara && previaEscura && (
            <Box aria-label={t('organizacao.corPrevia')} role="group" sx={{ display: 'grid', gap: 1, gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 12rem), 1fr))' }}>
              {[
                { fundo: '#FFFFFF', cor: previaClara, texto: '#3E2B1E' },
                { fundo: '#211913', cor: previaEscura, texto: '#F5ECE4' },
              ].map((previa) => (
                <Box key={previa.fundo} sx={{ p: 2, borderRadius: 3, border: 1, borderColor: 'divider', bgcolor: previa.fundo, color: previa.texto, display: 'flex', gap: 1.5, alignItems: 'center', flexWrap: 'wrap' }}>
                  <Box component="span" sx={{ px: 2, py: 0.75, borderRadius: 999, bgcolor: previa.cor, color: theme => theme.palette.getContrastText(previa.cor), fontWeight: 600, fontSize: '0.875rem' }}>
                    {t('organizacao.corPreviaBotao')}
                  </Box>
                  <Box component="span" sx={{ color: previa.cor, fontWeight: 600, textDecoration: 'underline', textUnderlineOffset: 4 }}>
                    {t('organizacao.corPreviaLink')}
                  </Box>
                </Box>
              ))}
            </Box>
          )}
        </Box>
      </Secao>

      <Secao titulo={t('organizacao.funcionamento')} texto={t('organizacao.funcionamentoTexto')}>
        <Autocomplete
          options={listaDeFusos}
          value={form.fuso}
          onChange={(_, fuso) => fuso && setForm({ ...form, fuso })}
          disableClearable
          renderInput={(parametros) => (
            <TextField {...parametros} required label={t('organizacao.fuso')} error={Boolean(erroCampo('fuso'))} helperText={erroCampo('fuso') ?? t('organizacao.fusoAjuda')} />
          )}
        />
        <FormControl>
          <FormLabel id="modo-matricula">{t('organizacao.matricula')}</FormLabel>
          <RadioGroup aria-labelledby="modo-matricula" value={form.modo_matricula} onChange={(_, valor) => setForm({ ...form, modo_matricula: valor })} sx={{ gap: 0.5, mt: 0.5 }}>
            {(['automatica', 'manual'] as const).map((modo) => (
              <FormControlLabel
                key={modo}
                value={modo}
                control={<Radio />}
                label={
                  <Box sx={{ py: 0.5 }}>
                    <Typography sx={{ fontWeight: 500 }}>{t(modo === 'automatica' ? 'organizacao.matriculaAutomatica' : 'organizacao.matriculaManual')}</Typography>
                    <Typography variant="body2" color="text.secondary">
                      {t(modo === 'automatica' ? 'organizacao.matriculaAutomaticaTexto' : 'organizacao.matriculaManualTexto')}
                    </Typography>
                  </Box>
                }
              />
            ))}
          </RadioGroup>
        </FormControl>
        <Box sx={{ display: 'grid', gap: 2.5, gridTemplateColumns: { xs: 'minmax(0, 1fr)', sm: 'repeat(2, minmax(0, 1fr))' } }}>
          <TextField
            type="number"
            label={t('organizacao.limiteWhatsapp')}
            value={form.limite_whatsapp_mensal}
            onChange={(e) => setForm({ ...form, limite_whatsapp_mensal: e.target.value })}
            error={Boolean(erroCampo('limite_whatsapp_mensal'))}
            helperText={erroCampo('limite_whatsapp_mensal') ?? t('organizacao.limiteWhatsappAjuda')}
            slotProps={{ htmlInput: { min: 0 } }}
          />
          <TextField
            type="email"
            label={t('organizacao.email')}
            value={form.email_contato}
            onChange={(e) => setForm({ ...form, email_contato: e.target.value })}
            error={Boolean(erroCampo('email_contato'))}
            helperText={erroCampo('email_contato') ?? t('organizacao.emailAjuda')}
          />
        </Box>
      </Secao>

      {salvar.isError && <Alert severity="error">{mensagemDeErro(salvar.error)}</Alert>}
      <Box>
        <Button type="submit" variant="contained" disabled={salvar.isPending || !corValida}>
          {salvar.isPending ? t('salvando') : t('organizacao.salvar')}
        </Button>
      </Box>
      {retorno}
    </Box>
  )
}

function EditorIgrejas(): ReactNode {
  const { t } = useTranslation('organizacao')
  const mensagemDeErro = useMensagemDeErro()
  const cliente = useQueryClient()
  const { retorno, avisar } = useRetorno()
  const [nome, setNome] = useState('')
  const consulta = useQuery({ queryKey: ['igrejas-config'], queryFn: listarIgrejas })
  const atualizar = (): Promise<unknown> => cliente.invalidateQueries({ queryKey: ['igrejas-config'] })
  const criar = useMutation({
    mutationFn: () => criarIgreja(nome.trim()),
    onSuccess: (resposta) => {
      avisar(t('igrejas.adicionada', { nome: resposta.data.nome }))
      setNome('')
      void atualizar()
      void cliente.invalidateQueries({ queryKey: ['pessoas', 'igrejas'] })
    },
  })
  const enviar = (evento: FormEvent): void => {
    evento.preventDefault()
    if (nome.trim().length >= 2) criar.mutate()
  }

  return (
    <Box sx={{ display: 'grid', gap: 2.5 }}>
      <Secao titulo={t('igrejas.nova')}>
        <Box component="form" onSubmit={enviar} sx={{ display: 'flex', flexWrap: 'wrap', gap: 1.5, alignItems: 'flex-start' }}>
          <TextField label={t('igrejas.nome')} value={nome} onChange={(e) => setNome(e.target.value)} sx={{ flex: '1 1 16rem' }} />
          <Button type="submit" variant="contained" disabled={nome.trim().length < 2 || criar.isPending} sx={{ mt: 0.75 }}>
            {t('igrejas.adicionar')}
          </Button>
        </Box>
        {criar.isError && <Alert severity="error">{mensagemDeErro(criar.error)}</Alert>}
      </Secao>

      <Secao titulo={t('igrejas.lista')} texto={t('igrejas.semExclusao')}>
        {consulta.isPending && <EstadoCarregando texto={t('carregando')} />}
        {consulta.isError && <EstadoErro titulo={t('indisponivel')} texto={t('erros.carregar')} rotuloTentar={t('tentar')} aoTentarDeNovo={() => void consulta.refetch()} />}
        {consulta.isSuccess && consulta.data.data.length === 0 && <EstadoVazio titulo={t('igrejas.vazio')} texto={t('igrejas.vazioTexto')} />}
        {consulta.isSuccess && consulta.data.data.length > 0 && (
          <Box component="ul" sx={{ listStyle: 'none', m: 0, p: 0, display: 'grid', gap: 1.5 }}>
            {consulta.data.data.map((igreja) => (
              <EditorIgreja key={igreja.uuid} igreja={igreja} aoSalvar={() => { avisar(t('igrejas.salva')); void atualizar() }} />
            ))}
          </Box>
        )}
      </Secao>
      {retorno}
    </Box>
  )
}

function EditorIgreja({ igreja, aoSalvar }: { igreja: Igreja; aoSalvar: () => void }): ReactNode {
  const { t } = useTranslation('organizacao')
  const mensagemDeErro = useMensagemDeErro()
  const [nome, setNome] = useState(igreja.nome)
  const salvar = useMutation({ mutationFn: () => editarIgreja(igreja.uuid, { nome: nome.trim() }), onSuccess: aoSalvar })
  const enviar = (evento: FormEvent): void => {
    evento.preventDefault()
    salvar.mutate()
  }
  return (
    <Box component="li" sx={{ p: 2, borderRadius: 3, bgcolor: 'afundado', display: 'grid', gap: 1 }}>
      <Box component="form" onSubmit={enviar} sx={{ display: 'flex', flexWrap: 'wrap', gap: 1.5, alignItems: 'center' }}>
        <TextField size="small" label={t('igrejas.nomeIgreja')} value={nome} onChange={(e) => setNome(e.target.value)} sx={{ flex: '1 1 14rem' }} slotProps={{ htmlInput: { 'aria-label': t('igrejas.renomearDe', { nome: igreja.nome }) } }} />
        <Chip size="small" variant="outlined" label={igreja.regional?.nome ? t('igrejas.regional', { nome: igreja.regional.nome }) : t('igrejas.semRegional')} />
        <Button type="submit" size="small" variant="outlined" disabled={nome.trim().length < 2 || nome.trim() === igreja.nome || salvar.isPending} aria-label={t('igrejas.salvarDe', { nome: igreja.nome })}>
          {t('salvar')}
        </Button>
      </Box>
      {salvar.isError && <Alert severity="error">{mensagemDeErro(salvar.error)}</Alert>}
    </Box>
  )
}

function EditorListas(): ReactNode {
  const { t } = useTranslation('organizacao')
  const mensagemDeErro = useMensagemDeErro()
  const cliente = useQueryClient()
  const { retorno, avisar } = useRetorno()
  const [lista, setLista] = useState<(typeof LISTAS)[number]>(LISTAS[0])
  const [nome, setNome] = useState('')
  const chave = ['lista-config', lista]
  const consulta = useQuery({ queryKey: chave, queryFn: () => listarValores(lista) })
  const atualizar = (): void => {
    void cliente.invalidateQueries({ queryKey: chave })
    void cliente.invalidateQueries({ queryKey: ['listas', lista] })
  }
  const criar = useMutation({
    mutationFn: () => criarValor(lista, nome.trim()),
    onSuccess: (resposta) => {
      avisar(t('listas.adicionado', { nome: resposta.data.nome }))
      setNome('')
      atualizar()
    },
  })
  const editar = useMutation({
    mutationFn: async (mudancas: ReadonlyArray<{ item: ItemLista; dados: Partial<Pick<ItemLista, 'nome' | 'ordem' | 'ativo'>> }>) => {
      for (const { item, dados } of mudancas) await editarValor(lista, item.uuid, dados)
    },
    onSuccess: () => {
      avisar(t('listas.salvo'))
      atualizar()
    },
    onError: atualizar,
  })
  const itens = [...(consulta.data?.data ?? [])].sort((a, b) => a.ordem - b.ordem || a.nome.localeCompare(b.nome))
  // Mover troca a posição com o vizinho; a ordem é renumerada para nunca empatar.
  const mover = (indice: number, direcao: -1 | 1): void => {
    const reordenados = [...itens]
    const [movido] = reordenados.splice(indice, 1)
    if (!movido) return
    reordenados.splice(indice + direcao, 0, movido)
    const mudancas = reordenados.flatMap((item, posicao) => (item.ordem === posicao + 1 ? [] : [{ item, dados: { ordem: posicao + 1 } }]))
    editar.mutate(mudancas)
  }
  const enviar = (evento: FormEvent): void => {
    evento.preventDefault()
    if (nome.trim()) criar.mutate()
  }

  return (
    <Box sx={{ display: 'grid', gap: 2.5 }}>
      <Secao titulo={t(`listas.nomes.${lista}`)} texto={t('listas.ajuda')}>
        <TextField select label={t('listas.lista')} value={lista} onChange={(e) => setLista(e.target.value as (typeof LISTAS)[number])}>
          {LISTAS.map((item) => (
            <MenuItem key={item} value={item}>
              {t(`listas.nomes.${item}`)}
            </MenuItem>
          ))}
        </TextField>
        <Box component="form" onSubmit={enviar} sx={{ display: 'flex', flexWrap: 'wrap', gap: 1.5, alignItems: 'flex-start' }}>
          <TextField label={t('listas.novo')} value={nome} onChange={(e) => setNome(e.target.value)} sx={{ flex: '1 1 16rem' }} />
          <Button type="submit" variant="contained" disabled={!nome.trim() || criar.isPending} sx={{ mt: 0.75 }}>
            {t('listas.adicionar')}
          </Button>
        </Box>
        {criar.isError && <Alert severity="error">{mensagemDeErro(criar.error)}</Alert>}
        {editar.isError && <Alert severity="error">{mensagemDeErro(editar.error)}</Alert>}
        {consulta.isPending && <EstadoCarregando texto={t('carregando')} />}
        {consulta.isError && <EstadoErro titulo={t('indisponivel')} texto={t('erros.carregar')} rotuloTentar={t('tentar')} aoTentarDeNovo={() => void consulta.refetch()} />}
        {consulta.isSuccess && itens.length === 0 && <EstadoVazio titulo={t('listas.vazio')} texto={t('listas.vazioTexto')} />}
        {itens.length > 0 && (
          <Box component="ol" sx={{ listStyle: 'none', m: 0, p: 0, display: 'grid', gap: 1 }}>
            {itens.map((item, indice) => (
              <ItemDaLista
                key={item.uuid}
                item={item}
                primeiro={indice === 0}
                ultimo={indice === itens.length - 1}
                ocupado={editar.isPending}
                aoSalvarNome={(novo) => editar.mutate([{ item, dados: { nome: novo } }])}
                aoAlternar={() => editar.mutate([{ item, dados: { ativo: !item.ativo } }])}
                aoMover={(direcao) => mover(indice, direcao)}
              />
            ))}
          </Box>
        )}
      </Secao>
      {retorno}
    </Box>
  )
}

interface PropsItem {
  item: ItemLista
  primeiro: boolean
  ultimo: boolean
  ocupado: boolean
  aoSalvarNome: (nome: string) => void
  aoAlternar: () => void
  aoMover: (direcao: -1 | 1) => void
}

function ItemDaLista({ item, primeiro, ultimo, ocupado, aoSalvarNome, aoAlternar, aoMover }: PropsItem): ReactNode {
  const { t } = useTranslation('organizacao')
  const [nome, setNome] = useState(item.nome)
  const enviar = (evento: FormEvent): void => {
    evento.preventDefault()
    if (nome.trim() && nome.trim() !== item.nome) aoSalvarNome(nome.trim())
  }
  return (
    <Box component="li" sx={{ p: 1.5, borderRadius: 3, bgcolor: 'afundado', opacity: item.ativo ? 1 : 0.85 }}>
      <Box component="form" onSubmit={enviar} sx={{ display: 'flex', flexWrap: 'wrap', gap: 1, alignItems: 'center' }}>
        <TextField
          size="small"
          value={nome}
          onChange={(e) => setNome(e.target.value)}
          sx={{ flex: '1 1 12rem' }}
          slotProps={{ htmlInput: { 'aria-label': t('listas.valorDe', { nome: item.nome }) } }}
        />
        {!item.ativo && <Chip size="small" label={t('listas.inativo')} variant="outlined" />}
        <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 0.5 }}>
          <Button type="submit" size="small" disabled={ocupado || !nome.trim() || nome.trim() === item.nome} aria-label={t('listas.salvarDe', { nome: item.nome })}>
            {t('salvar')}
          </Button>
          <Button size="small" disabled={ocupado || primeiro} onClick={() => aoMover(-1)} aria-label={t('listas.subirDe', { nome: item.nome })}>
            {t('listas.subir')}
          </Button>
          <Button size="small" disabled={ocupado || ultimo} onClick={() => aoMover(1)} aria-label={t('listas.descerDe', { nome: item.nome })}>
            {t('listas.descer')}
          </Button>
          <Button size="small" color={item.ativo ? 'error' : 'primary'} disabled={ocupado} onClick={aoAlternar}>
            {item.ativo ? t('listas.desativar') : t('listas.reativar')}
          </Button>
        </Box>
      </Box>
    </Box>
  )
}

function EditorObrigatorios(): ReactNode {
  const { t } = useTranslation('organizacao')
  const mensagemDeErro = useMensagemDeErro()
  const cliente = useQueryClient()
  const { retorno, avisar } = useRetorno()
  const consulta = useQuery({ queryKey: ['obrigatorios-config'], queryFn: obterObrigatorios })
  const [vinculo, setVinculo] = useState<(typeof VINCULOS)[number]>('visitante')
  const [rascunhos, setRascunhos] = useState<Partial<Record<string, ReadonlySet<string>>>>({})
  const marcados = rascunhos[vinculo] ?? new Set(consulta.data?.data[vinculo] ?? [])
  const salvar = useMutation({
    mutationFn: () => salvarObrigatorios(vinculo, [...marcados]),
    onSuccess: () => {
      avisar(t('obrigatorios.sucesso', { vinculo: t(`vinculos.${vinculo}`) }))
      setRascunhos((atuais) => Object.fromEntries(Object.entries(atuais).filter(([chave]) => chave !== vinculo)))
      void cliente.invalidateQueries({ queryKey: ['obrigatorios-config'] })
    },
  })
  const alternar = (campo: string, marcado: boolean): void => {
    const novos = new Set(marcados)
    if (marcado) novos.add(campo)
    else novos.delete(campo)
    setRascunhos({ ...rascunhos, [vinculo]: novos })
  }

  if (consulta.isPending) return <EstadoCarregando texto={t('carregando')} />
  if (consulta.isError) return <EstadoErro titulo={t('indisponivel')} texto={mensagemDeErro(consulta.error)} rotuloTentar={t('tentar')} aoTentarDeNovo={() => void consulta.refetch()} />

  return (
    <Box sx={{ display: 'grid', gap: 2.5 }}>
      <Secao titulo={t('aba.obrigatorios')} texto={t('obrigatorios.texto')}>
        <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1.5, alignItems: 'center', justifyContent: 'space-between' }}>
          <ToggleButtonGroup exclusive size="small" color="primary" value={vinculo} aria-label={t('obrigatorios.vinculo')} onChange={(_, novo: (typeof VINCULOS)[number] | null) => novo && setVinculo(novo)}>
            {VINCULOS.map((item) => (
              <ToggleButton key={item} value={item} sx={{ px: 2, textTransform: 'none', fontWeight: 600 }}>
                {t(`vinculos.${item}`)}
              </ToggleButton>
            ))}
          </ToggleButtonGroup>
          <Typography role="status" color="text.secondary">
            {t('obrigatorios.marcados', { count: marcados.size })}
          </Typography>
        </Box>
        <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: 'repeat(auto-fill, minmax(min(100%, 15rem), 1fr))' }}>
          {CAMPOS_POR_SECAO.map(([secao, campos]) => (
            <FormControl key={secao} component="fieldset" sx={{ p: 2, borderRadius: 3, bgcolor: 'afundado', minWidth: 0 }}>
              <FormLabel component="legend" sx={{ float: 'left', width: '100%', mb: 0.5, fontWeight: 600 }}>
                {t(`obrigatorios.secoes.${secao}`)}
              </FormLabel>
              {campos.map((campo) => (
                <FormControlLabel
                  key={campo}
                  control={<Checkbox size="small" checked={marcados.has(campo)} onChange={(_, marcado) => alternar(campo, marcado)} />}
                  label={t(`campos.${campo}`)}
                />
              ))}
            </FormControl>
          ))}
        </Box>
        {salvar.isError && <Alert severity="error">{mensagemDeErro(salvar.error)}</Alert>}
        <Box>
          <Button variant="contained" disabled={salvar.isPending || rascunhos[vinculo] === undefined} onClick={() => salvar.mutate()}>
            {salvar.isPending ? t('salvando') : t('obrigatorios.salvar')}
          </Button>
        </Box>
      </Secao>
      {retorno}
    </Box>
  )
}
