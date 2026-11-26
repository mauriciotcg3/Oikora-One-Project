import Alert from '@mui/material/Alert'
import Autocomplete from '@mui/material/Autocomplete'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Chip from '@mui/material/Chip'
import MenuItem from '@mui/material/MenuItem'
import Paper from '@mui/material/Paper'
import TextField from '@mui/material/TextField'
import Typography from '@mui/material/Typography'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useDeferredValue, useState, type FormEvent, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { ConfirmacaoIrreversivel, EstadoCarregando, EstadoErro, EstadoVazio, LinkTexto } from '../../../shared/ui'
import { buscarPessoas, enviarRecurso, listarRecurso, type PessoaResumo, type RegistroGenerico } from '../api/pessoas'
import { formatarData, useMensagemDeErro } from './comum'
import { useRetorno } from './Retorno'

type TipoRegistro = 'linha-do-tempo' | 'relacionamentos' | 'funcoes'

interface EventoLinhaDoTempo extends RegistroGenerico {
  tipo: string
  ocorrido_em: string
  descricao?: string | null
  autor?: { nome?: string | null } | null
}
interface Relacionamento extends RegistroGenerico {
  tipo: string
  descricao?: string | null
  relacionada?: { uuid?: string | null; nome?: string | null }
}
interface FuncaoDaPessoa extends RegistroGenerico {
  funcao?: { nome?: string | null }
  desde: string
  ate?: string | null
}

const TIPOS_RELACIONAMENTO = ['conjuge', 'pai_mae', 'filho', 'irmao', 'avo', 'neto', 'responsavel_legal', 'dependente', 'outro'] as const
const hoje = (): string => new Date().toISOString().slice(0, 10)
const humanizar = (codigo: string): string => {
  const texto = codigo.replaceAll('_', ' ').replaceAll('.', ' ')
  return texto.charAt(0).toUpperCase() + texto.slice(1)
}

/** Linha do tempo, relacionamentos e funções da ficha (T-42), cada um com formulário, retorno e erro traduzido. */
export function RegistrosFicha({ uuid, tipo, podeEditar }: { uuid: string; tipo: TipoRegistro; podeEditar: boolean }): ReactNode {
  const { t, i18n } = useTranslation('pessoas')
  const mensagemDeErro = useMensagemDeErro()
  const cliente = useQueryClient()
  const { retorno, avisar } = useRetorno()
  const caminho = `/painel/pessoas/${uuid}/${tipo}`
  const consulta = useQuery({ queryKey: ['recurso', caminho], queryFn: () => listarRecurso(caminho) })
  const [remover, setRemover] = useState<Relacionamento | null>(null)
  const atualizar = (): void => {
    void cliente.invalidateQueries({ queryKey: ['recurso', caminho] })
    void cliente.invalidateQueries({ queryKey: ['ficha', uuid] })
  }
  const acao = useMutation({
    mutationFn: ({ caminhoAcao, metodo, dados }: { caminhoAcao: string; metodo: 'DELETE' | 'PATCH'; dados?: unknown; sucesso: string }) => enviarRecurso(metodo, caminhoAcao, dados),
    onSuccess: (_, { sucesso }) => {
      avisar(sucesso)
      atualizar()
    },
  })
  const data = (valor: string): string => formatarData(valor, i18n.language)

  if (consulta.isPending) return <EstadoCarregando texto={t('registros.carregando')} />
  if (consulta.isError) return <EstadoErro titulo={t('erros.tituloCarregar')} texto={mensagemDeErro(consulta.error)} rotuloTentar={t('comum.tentar')} aoTentarDeNovo={() => void consulta.refetch()} />

  const itens = consulta.data.data
  return (
    <Box sx={{ display: 'grid', gap: 2.5, gridTemplateColumns: { xs: 'minmax(0, 1fr)', lg: podeEditar ? 'minmax(0, 1fr) minmax(18rem, 24rem)' : 'minmax(0, 1fr)' }, alignItems: 'start' }}>
      <Box sx={{ display: 'grid', gap: 1.5, minWidth: 0 }}>
        {acao.isError && <Alert severity="error">{mensagemDeErro(acao.error)}</Alert>}
        {itens.length === 0 && (
          <Paper variant="outlined" sx={{ borderRadius: 4 }}>
            <EstadoVazio titulo={t('registros.vazio')} texto={t('registros.vazioTexto')} />
          </Paper>
        )}
        {itens.length > 0 && (
          <Box component="ul" sx={{ listStyle: 'none', m: 0, p: 0, display: 'grid', gap: 1.5 }}>
            {tipo === 'linha-do-tempo' &&
              (itens as EventoLinhaDoTempo[]).map((evento) => (
                <Paper component="li" key={evento.uuid} variant="outlined" sx={{ p: 2, borderRadius: 4, display: 'grid', gap: 0.5, borderLeft: 4, borderLeftColor: 'primary.main' }}>
                  <Typography variant="body2" color="text.secondary">
                    {data(evento.ocorrido_em)}
                    {evento.autor?.nome ? ` · ${evento.autor.nome}` : ''}
                  </Typography>
                  <Typography sx={{ fontWeight: 600 }}>{t(`registros.linhaDoTempo.tipos.${evento.tipo}` as never, { defaultValue: humanizar(evento.tipo) })}</Typography>
                  {evento.descricao && <Typography>{evento.descricao}</Typography>}
                </Paper>
              ))}
            {tipo === 'relacionamentos' &&
              (itens as Relacionamento[]).map((item) => {
                const nome = item.relacionada?.nome ?? t('comum.naoInformado')
                return (
                  <Paper component="li" key={item.uuid} variant="outlined" sx={{ p: 2, borderRadius: 4, display: 'flex', flexWrap: 'wrap', gap: 1.5, alignItems: 'center', justifyContent: 'space-between' }}>
                    <Box sx={{ display: 'grid', gap: 0.25, minWidth: 0 }}>
                      {item.relacionada?.uuid ? (
                        <LinkTexto to="/pessoas/$uuid" params={{ uuid: item.relacionada.uuid }} sx={{ fontWeight: 600 }}>
                          {nome}
                        </LinkTexto>
                      ) : (
                        <Typography sx={{ fontWeight: 600 }}>{nome}</Typography>
                      )}
                      <Typography variant="body2" color="text.secondary">
                        {item.tipo === 'outro' && item.descricao ? item.descricao : t(`registros.relacionamentos.tipos.${item.tipo}` as never, { defaultValue: humanizar(item.tipo) })}
                      </Typography>
                    </Box>
                    {podeEditar && (
                      <Button size="small" color="error" variant="outlined" aria-label={t('registros.relacionamentos.removerDe', { nome })} onClick={() => setRemover(item)}>
                        {t('registros.relacionamentos.remover')}
                      </Button>
                    )}
                  </Paper>
                )
              })}
            {tipo === 'funcoes' &&
              (itens as FuncaoDaPessoa[]).map((item) => {
                const nome = item.funcao?.nome ?? t('comum.naoInformado')
                return (
                  <Paper component="li" key={item.uuid} variant="outlined" sx={{ p: 2, borderRadius: 4, display: 'flex', flexWrap: 'wrap', gap: 1.5, alignItems: 'center', justifyContent: 'space-between' }}>
                    <Box sx={{ display: 'grid', gap: 0.25, minWidth: 0 }}>
                      <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1, alignItems: 'center' }}>
                        <Typography sx={{ fontWeight: 600 }}>{nome}</Typography>
                        {!item.ate && <Chip size="small" color="success" variant="outlined" label={t('registros.funcoes.atual')} />}
                      </Box>
                      <Typography variant="body2" color="text.secondary">
                        {`${t('registros.funcoes.desde')} ${data(item.desde)}`}
                        {item.ate ? ` · ${t('registros.funcoes.ate')} ${data(item.ate)}` : ''}
                      </Typography>
                    </Box>
                    {podeEditar && !item.ate && (
                      <Button
                        size="small"
                        variant="outlined"
                        disabled={acao.isPending}
                        aria-label={t('registros.funcoes.encerrarDe', { funcao: nome })}
                        onClick={() => acao.mutate({ caminhoAcao: `${caminho}/${item.uuid}`, metodo: 'PATCH', dados: { ate: hoje() }, sucesso: t('registros.funcoes.encerrada') })}
                      >
                        {t('registros.funcoes.encerrar')}
                      </Button>
                    )}
                  </Paper>
                )
              })}
          </Box>
        )}
      </Box>

      {podeEditar && (
        <Paper variant="outlined" sx={{ p: { xs: 2, md: 2.5 }, borderRadius: 4, display: 'grid', gap: 2 }}>
          <Typography component="h2" variant="h3">
            {t('registros.novo')}
          </Typography>
          {tipo === 'linha-do-tempo' && <FormularioLinhaDoTempo uuid={uuid} aoSalvar={(texto) => { avisar(texto); atualizar() }} />}
          {tipo === 'relacionamentos' && <FormularioRelacionamento uuid={uuid} aoSalvar={(texto) => { avisar(texto); atualizar() }} />}
          {tipo === 'funcoes' && <FormularioFuncao uuid={uuid} aoSalvar={(texto) => { avisar(texto); atualizar() }} />}
        </Paper>
      )}

      <ConfirmacaoIrreversivel
        aberta={remover !== null}
        titulo={t('registros.relacionamentos.confirmarTitulo')}
        texto={t('registros.relacionamentos.confirmarTexto', { nome: remover?.relacionada?.nome ?? '' })}
        rotuloConfirmar={t('registros.relacionamentos.remover')}
        rotuloCancelar={t('comum.cancelar')}
        aoCancelar={() => setRemover(null)}
        aoConfirmar={() => {
          if (remover) acao.mutate({ caminhoAcao: `${caminho}/${remover.uuid}`, metodo: 'DELETE', sucesso: t('registros.relacionamentos.removido') })
          setRemover(null)
        }}
      />
      {retorno}
    </Box>
  )
}

interface PropsFormulario {
  uuid: string
  aoSalvar: (texto: string) => void
}

function FormularioLinhaDoTempo({ uuid, aoSalvar }: PropsFormulario): ReactNode {
  const { t } = useTranslation('pessoas')
  const mensagemDeErro = useMensagemDeErro()
  const [ocorridoEm, setOcorridoEm] = useState('')
  const [descricao, setDescricao] = useState('')
  const salvar = useMutation({
    mutationFn: () => enviarRecurso('POST', `/painel/pessoas/${uuid}/linha-do-tempo`, { ocorrido_em: ocorridoEm, descricao }),
    onSuccess: () => {
      setDescricao('')
      setOcorridoEm('')
      aoSalvar(t('registros.linhaDoTempo.sucesso'))
    },
  })
  const enviar = (evento: FormEvent): void => {
    evento.preventDefault()
    salvar.mutate()
  }
  return (
    <Box component="form" onSubmit={enviar} sx={{ display: 'grid', gap: 2 }}>
      <TextField required type="datetime-local" label={t('registros.linhaDoTempo.ocorridoEm')} value={ocorridoEm} onChange={(e) => setOcorridoEm(e.target.value)} slotProps={{ inputLabel: { shrink: true } }} />
      <TextField required multiline minRows={3} label={t('registros.linhaDoTempo.descricao')} value={descricao} onChange={(e) => setDescricao(e.target.value)} />
      {salvar.isError && <Alert severity="error">{mensagemDeErro(salvar.error)}</Alert>}
      <Button type="submit" variant="contained" disabled={!ocorridoEm || !descricao.trim() || salvar.isPending} sx={{ justifySelf: 'start' }}>
        {t('registros.linhaDoTempo.salvar')}
      </Button>
    </Box>
  )
}

function FormularioRelacionamento({ uuid, aoSalvar }: PropsFormulario): ReactNode {
  const { t } = useTranslation('pessoas')
  const mensagemDeErro = useMensagemDeErro()
  const [relacionada, setRelacionada] = useState<PessoaResumo | null>(null)
  const [termo, setTermo] = useState('')
  const termoAdiado = useDeferredValue(termo.trim())
  const [tipo, setTipo] = useState<(typeof TIPOS_RELACIONAMENTO)[number]>('conjuge')
  const [descricao, setDescricao] = useState('')
  const busca = useQuery({ queryKey: ['pessoas', 'busca', termoAdiado], queryFn: () => buscarPessoas(termoAdiado), enabled: termoAdiado.length >= 3 })
  const opcoes = (busca.data?.data ?? []).filter((pessoa) => pessoa.uuid !== uuid && (pessoa.meta?.acoes?.includes('abrir') ?? false))
  const salvar = useMutation({
    mutationFn: () => enviarRecurso('POST', `/painel/pessoas/${uuid}/relacionamentos`, { relacionada: relacionada?.uuid, tipo, ...(descricao.trim() ? { descricao } : {}) }),
    onSuccess: () => {
      setRelacionada(null)
      setTermo('')
      setDescricao('')
      aoSalvar(t('registros.relacionamentos.sucesso'))
    },
  })
  const enviar = (evento: FormEvent): void => {
    evento.preventDefault()
    salvar.mutate()
  }
  return (
    <Box component="form" onSubmit={enviar} sx={{ display: 'grid', gap: 2 }}>
      <Autocomplete
        options={opcoes}
        value={relacionada}
        onChange={(_, pessoa) => setRelacionada(pessoa)}
        inputValue={termo}
        onInputChange={(_, valor) => setTermo(valor)}
        getOptionLabel={(pessoa) => pessoa.nome}
        getOptionKey={(pessoa) => pessoa.uuid}
        isOptionEqualToValue={(a, b) => a.uuid === b.uuid}
        filterOptions={(lista) => lista}
        loading={busca.isFetching}
        noOptionsText={termoAdiado.length < 3 ? t('registros.relacionamentos.pessoaAjuda') : t('registros.relacionamentos.semResultados')}
        loadingText={t('comum.carregando')}
        renderOption={(props, pessoa) => (
          <li {...props} key={pessoa.uuid}>
            <Box>
              <Typography>{pessoa.nome}</Typography>
              <Typography variant="body2" color="text.secondary">
                {pessoa.igreja?.nome ?? t('comum.semIgreja')}
              </Typography>
            </Box>
          </li>
        )}
        renderInput={(parametros) => <TextField {...parametros} required label={t('registros.relacionamentos.pessoa')} helperText={t('registros.relacionamentos.pessoaAjuda')} />}
      />
      <TextField select label={t('registros.relacionamentos.tipo')} value={tipo} onChange={(e) => setTipo(e.target.value as (typeof TIPOS_RELACIONAMENTO)[number])}>
        {TIPOS_RELACIONAMENTO.map((valor) => (
          <MenuItem key={valor} value={valor}>
            {t(`registros.relacionamentos.tipos.${valor}`)}
          </MenuItem>
        ))}
      </TextField>
      {tipo === 'outro' && <TextField required label={t('registros.relacionamentos.descricao')} value={descricao} onChange={(e) => setDescricao(e.target.value)} />}
      {salvar.isError && <Alert severity="error">{mensagemDeErro(salvar.error)}</Alert>}
      <Button type="submit" variant="contained" disabled={!relacionada || (tipo === 'outro' && !descricao.trim()) || salvar.isPending} sx={{ justifySelf: 'start' }}>
        {t('registros.relacionamentos.salvar')}
      </Button>
    </Box>
  )
}

function FormularioFuncao({ uuid, aoSalvar }: PropsFormulario): ReactNode {
  const { t } = useTranslation('pessoas')
  const mensagemDeErro = useMensagemDeErro()
  const catalogo = useQuery({ queryKey: ['lista-config', 'funcao'], queryFn: () => listarRecurso('/painel/listas/funcao') })
  const [funcao, setFuncao] = useState('')
  const [desde, setDesde] = useState(hoje())
  const opcoes = (catalogo.data?.data ?? []).filter((item) => item.ativo !== false)
  const salvar = useMutation({
    mutationFn: () => enviarRecurso('POST', `/painel/pessoas/${uuid}/funcoes`, { funcao, desde }),
    onSuccess: () => {
      setFuncao('')
      aoSalvar(t('registros.funcoes.sucesso'))
    },
  })
  const enviar = (evento: FormEvent): void => {
    evento.preventDefault()
    salvar.mutate()
  }
  return (
    <Box component="form" onSubmit={enviar} sx={{ display: 'grid', gap: 2 }}>
      <TextField
        select
        required
        label={t('registros.funcoes.funcao')}
        value={funcao}
        onChange={(e) => setFuncao(e.target.value)}
        disabled={catalogo.isPending}
        helperText={catalogo.isSuccess && opcoes.length === 0 ? t('registros.funcoes.semCatalogo') : undefined}
      >
        {opcoes.map((item) => (
          <MenuItem key={item.uuid} value={item.uuid}>
            {String(item.nome ?? '')}
          </MenuItem>
        ))}
      </TextField>
      <TextField required type="date" label={t('registros.funcoes.desde')} value={desde} onChange={(e) => setDesde(e.target.value)} slotProps={{ inputLabel: { shrink: true } }} />
      {salvar.isError && <Alert severity="error">{mensagemDeErro(salvar.error)}</Alert>}
      <Button type="submit" variant="contained" disabled={!funcao || !desde || salvar.isPending} sx={{ justifySelf: 'start' }}>
        {t('registros.funcoes.salvar')}
      </Button>
    </Box>
  )
}
