import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Checkbox from '@mui/material/Checkbox'
import Dialog from '@mui/material/Dialog'
import DialogActions from '@mui/material/DialogActions'
import DialogContent from '@mui/material/DialogContent'
import DialogContentText from '@mui/material/DialogContentText'
import DialogTitle from '@mui/material/DialogTitle'
import FormControl from '@mui/material/FormControl'
import FormControlLabel from '@mui/material/FormControlLabel'
import FormGroup from '@mui/material/FormGroup'
import FormHelperText from '@mui/material/FormHelperText'
import FormLabel from '@mui/material/FormLabel'
import LinearProgress from '@mui/material/LinearProgress'
import Radio from '@mui/material/Radio'
import RadioGroup from '@mui/material/RadioGroup'
import { useMutation, useQuery } from '@tanstack/react-query'
import { useId, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { registrarEventoUi } from '../../../shared/observabilidade'
import { criarExportacao, obterExportacao } from '../api/pessoas'
import { useMensagemDeErro } from './comum'

type Categoria = 'gerais' | 'contatos' | 'fe'
type Formato = 'xlsx' | 'csv'
type EstadoExportacao = 'pendente' | 'processando' | 'concluida' | 'negada' | 'expirada'

const CATEGORIAS: readonly Categoria[] = ['gerais', 'contatos', 'fe']
const ESTADOS_FINAIS: ReadonlySet<string> = new Set(['concluida', 'negada', 'expirada'])
const CONSULTAR_A_CADA_MS = 3000

interface Props {
  aberto: boolean
  aoFechar: () => void
  tipo: 'pessoas' | 'novos_convertidos' | 'aniversarios'
  filtro?: Record<string, unknown>
  selecionados?: readonly string[]
  /** O que vai no arquivo, já traduzido. */
  texto: string
  /** Aviso extra (ex.: o período exportado difere do mostrado). */
  aviso?: string
}

/**
 * Exportação com retorno (T-44): a pessoa escolhe formato e categorias, o arquivo é gerado em segundo
 * plano e o diálogo acompanha o estado até ficar pronto, ser negado ou expirar; depois para de consultar.
 */
export function DialogoExportacao({ aberto, aoFechar, tipo, filtro = {}, selecionados = [], texto, aviso }: Props): ReactNode {
  const { t } = useTranslation('pessoas')
  const mensagemDeErro = useMensagemDeErro()
  const idCategorias = useId()
  const [formato, setFormato] = useState<Formato>('xlsx')
  const [categorias, setCategorias] = useState<ReadonlySet<Categoria>>(new Set(['gerais']))
  const [uuid, setUuid] = useState<string | null>(null)

  const criar = useMutation({
    mutationFn: () => criarExportacao({ tipo, formato, filtro, ...(selecionados.length > 0 ? { selecionados } : {}), categorias: [...categorias] }),
    onSuccess: (resposta) => {
      registrarEventoUi('ui.exportacao_pedida', { tipo, formato, categorias: categorias.size })
      setUuid(String(resposta.data.uuid))
    },
  })
  const estado = useQuery({
    queryKey: ['exportacao', uuid],
    queryFn: () => obterExportacao(uuid ?? ''),
    enabled: uuid !== null,
    refetchInterval: (consulta) => (ESTADOS_FINAIS.has(String(consulta.state.data?.data.estado)) ? false : CONSULTAR_A_CADA_MS),
  })

  const fechar = (): void => {
    setUuid(null)
    criar.reset()
    aoFechar()
  }
  const alternar = (categoria: Categoria, marcada: boolean): void => {
    const novas = new Set(categorias)
    if (marcada) novas.add(categoria)
    else novas.delete(categoria)
    setCategorias(novas)
  }

  const situacao = (estado.data?.data.estado ?? (uuid ? 'pendente' : undefined)) as EstadoExportacao | undefined
  const url = typeof estado.data?.data.url === 'string' ? estado.data.data.url : null
  const emAndamento = situacao === 'pendente' || situacao === 'processando'

  return (
    <Dialog open={aberto} onClose={fechar} fullWidth maxWidth="sm" aria-labelledby={`${idCategorias}-titulo`}>
      <DialogTitle id={`${idCategorias}-titulo`}>{t(`exportacao.tituloTipo.${tipo}`)}</DialogTitle>
      <DialogContent sx={{ display: 'grid', gap: 2.5 }}>
        {uuid === null ? (
          <>
            <DialogContentText>{texto}</DialogContentText>
            {aviso && <Alert severity="info">{aviso}</Alert>}
            <FormControl>
              <FormLabel id={`${idCategorias}-formato`}>{t('exportacao.formato')}</FormLabel>
              <RadioGroup row aria-labelledby={`${idCategorias}-formato`} value={formato} onChange={(_, valor) => setFormato(valor as Formato)}>
                <FormControlLabel value="xlsx" control={<Radio />} label="Excel (.xlsx)" />
                <FormControlLabel value="csv" control={<Radio />} label="CSV (.csv)" />
              </RadioGroup>
            </FormControl>
            <FormControl component="fieldset" error={categorias.size === 0}>
              <FormLabel component="legend">{t('exportacao.categorias')}</FormLabel>
              <FormGroup>
                {CATEGORIAS.map((categoria) => (
                  <FormControlLabel
                    key={categoria}
                    control={<Checkbox checked={categorias.has(categoria)} onChange={(_, marcada) => alternar(categoria, marcada)} />}
                    label={t(`exportacao.categoria.${categoria}`)}
                  />
                ))}
              </FormGroup>
              <FormHelperText>{t('exportacao.categoriasAjuda')}</FormHelperText>
            </FormControl>
            {criar.isError && <Alert severity="error">{mensagemDeErro(criar.error)}</Alert>}
          </>
        ) : (
          <Box role="status" sx={{ display: 'grid', gap: 1.5 }}>
            {emAndamento && (
              <>
                <Alert severity="info">{situacao ? t(`exportacao.estado.${situacao}`) : t('exportacao.gerando')}</Alert>
                <LinearProgress aria-label={t('exportacao.gerando')} />
                <DialogContentText>{t('exportacao.acompanhando')}</DialogContentText>
              </>
            )}
            {situacao === 'concluida' && <Alert severity="success">{`${t('exportacao.estado.concluida')}. ${t('exportacao.prontoTexto')}`}</Alert>}
            {situacao === 'negada' && <Alert severity="error">{t('exportacao.negadaTexto')}</Alert>}
            {situacao === 'expirada' && <Alert severity="warning">{t('exportacao.expiradaTexto')}</Alert>}
            {estado.isError && <Alert severity="error">{mensagemDeErro(estado.error)}</Alert>}
          </Box>
        )}
      </DialogContent>
      <DialogActions sx={{ px: 3, pb: 2.5, flexWrap: 'wrap', gap: 1 }}>
        <Button onClick={fechar}>{uuid === null ? t('comum.cancelar') : t('comum.fechar')}</Button>
        {uuid === null && (
          <Button variant="contained" disabled={categorias.size === 0 || criar.isPending} onClick={() => criar.mutate()}>
            {criar.isPending ? t('exportacao.gerando') : t('exportacao.gerar')}
          </Button>
        )}
        {situacao === 'concluida' && url && (
          <Button variant="contained" component="a" href={url} download>
            {t('exportacao.baixar')}
          </Button>
        )}
      </DialogActions>
    </Dialog>
  )
}
