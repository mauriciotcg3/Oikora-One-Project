import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Chip from '@mui/material/Chip'
import Dialog from '@mui/material/Dialog'
import DialogActions from '@mui/material/DialogActions'
import DialogContent from '@mui/material/DialogContent'
import DialogTitle from '@mui/material/DialogTitle'
import FormControl from '@mui/material/FormControl'
import InputLabel from '@mui/material/InputLabel'
import ListSubheader from '@mui/material/ListSubheader'
import MenuItem from '@mui/material/MenuItem'
import Select from '@mui/material/Select'
import Stack from '@mui/material/Stack'
import TextField from '@mui/material/TextField'
import Typography from '@mui/material/Typography'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useMemo, useState, type FormEvent, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { registrarEventoUi } from '../../../shared/observabilidade'
import {
  aprovarSolicitacao, cancelarSolicitacao, catalogoSolicitavel, criarSolicitacao,
  listarEscoposDisponiveis, listarSolicitacoes, recusarSolicitacao, revogarConcessao,
  type EscopoSolicitado, type RecusaSolicitacao, type SolicitacaoPermissao,
} from '../api/solicitacoes'

const CHAVE = ['solicitacoes-permissao'] as const

function mensagem(erro?: RecusaSolicitacao): string | null {
  if (!erro) return null
  if (erro.status === 409) return 'A solicitação foi alterada por outra pessoa. A versão atual foi recarregada; confirme novamente se ainda desejar.'
  if (erro.status === 403) return 'Você não tem permissão para esta ação.'
  const conhecidas: Record<string, string> = {
    sem_permissao: 'Você não tem permissão para esta ação.',
    escalada_bloqueada: 'O pedido ultrapassa o seu teto de permissões.',
    autoaprovacao_negada: 'Quem solicitou não pode aprovar o próprio pedido.',
    ultimo_administrador: 'A ação deixaria a organização sem administrador.',
  }
  return conhecidas[erro.codigo] ?? Object.values(erro.campos ?? {})[0] ?? 'Não foi possível concluir a ação.'
}

function descricaoEscopo(item: SolicitacaoPermissao): string {
  const primeiro = item.permissoes[0]
  if (!primeiro) return 'Escopo não informado'
  return primeiro.escopo_tipo === 'organizacao'
    ? 'Toda a organização'
    : `${primeiro.escopo_tipo === 'regional' ? 'Regional' : 'Igreja'}: ${primeiro.escopo_id}`
}

function Cartao({ item, aprovavel, aoMudar }: { item: SolicitacaoPermissao; aprovavel: boolean; aoMudar: (erro?: RecusaSolicitacao) => void }): ReactNode {
  const [motivo, setMotivo] = useState('')
  const [confirmarRevogacao, setConfirmarRevogacao] = useState(false)
  const executar = useMutation({
    mutationFn: async (acao: 'aprovar' | 'recusar' | 'cancelar' | 'revogar') => {
      if (acao === 'aprovar') return aprovarSolicitacao(item.uuid, item.versao)
      if (acao === 'recusar') return recusarSolicitacao(item.uuid, item.versao, motivo)
      if (acao === 'revogar' && item.concessao) return revogarConcessao(item.concessao.uuid, motivo)
      return cancelarSolicitacao(item.uuid)
    },
    onSuccess: (resultado) => {
      registrarEventoUi('ui.solicitacao_permissao_alterada', { estado: item.estado, sucesso: !resultado.erro })
      setConfirmarRevogacao(false)
      aoMudar(resultado.erro)
    },
  })

  return <Box component="article" sx={{ border: 1, borderColor: 'divider', borderRadius: 2, p: 2 }}>
    <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap">
      <Typography component="h3" variant="h6">{item.estado}</Typography>
      {item.permissoes.map((permissao) => permissao.categoria_sensivel
        ? <Chip key={permissao.chave} size="small" color="warning" label={`Sensível: ${permissao.categoria_sensivel}`} />
        : null)}
    </Stack>
    {aprovavel && <Typography variant="body2">Solicitante: {item.solicitante.acesso}</Typography>}
    <Typography>{item.justificativa}</Typography>
    <Typography variant="body2">Permissões: {item.permissoes.map((p) => p.chave).join(', ')}</Typography>
    <Typography variant="body2">Escopo: {descricaoEscopo(item)} · Duração: {item.duracao_horas}h</Typography>
    <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} sx={{ mt: 2 }}>
      {aprovavel && item.estado === 'pendente' && <>
        <Button variant="contained" disabled={executar.isPending} onClick={() => executar.mutate('aprovar')}>Aprovar</Button>
        <TextField size="small" label="Justificativa da recusa" value={motivo} onChange={(e) => setMotivo(e.target.value)} />
        <Button disabled={!motivo.trim() || executar.isPending} onClick={() => executar.mutate('recusar')}>Recusar</Button>
      </>}
      {!aprovavel && item.estado === 'pendente' && <Button disabled={executar.isPending} onClick={() => executar.mutate('cancelar')}>Cancelar solicitação</Button>}
      {aprovavel && item.concessao && item.concessao.revogada_em === null && <>
        <TextField size="small" label="Motivo da revogação" value={motivo} onChange={(e) => setMotivo(e.target.value)} />
        <Button color="error" disabled={!motivo.trim()} onClick={() => setConfirmarRevogacao(true)}>Revogar concessão</Button>
      </>}
    </Stack>
    <Dialog open={confirmarRevogacao} onClose={() => setConfirmarRevogacao(false)}>
      <DialogTitle>Confirmar revogação?</DialogTitle>
      <DialogContent>O acesso temporário deixará de valer imediatamente.</DialogContent>
      <DialogActions>
        <Button onClick={() => setConfirmarRevogacao(false)}>Voltar</Button>
        <Button color="error" disabled={executar.isPending} onClick={() => executar.mutate('revogar')}>Confirmar revogação</Button>
      </DialogActions>
    </Dialog>
  </Box>
}

function EstadoLista({ carregando, falhou, vazio }: { carregando: boolean; falhou: boolean; vazio: boolean }): ReactNode {
  if (carregando) return <Typography>Carregando solicitações…</Typography>
  if (falhou) return <Alert severity="error">Não foi possível carregar as solicitações ou você não tem acesso a esta fila.</Alert>
  if (vazio) return <Typography>Nenhuma solicitação encontrada.</Typography>
  return null
}

export function TelaSolicitacoes({ podeAprovar = false }: { podeAprovar?: boolean }): ReactNode {
  const { t } = useTranslation('autorizacao')
  const cliente = useQueryClient()
  const [permissao, setPermissao] = useState('')
  const [escopoTipo, setEscopoTipo] = useState<EscopoSolicitado>('organizacao')
  const [escopoId, setEscopoId] = useState('')
  const [duracao, setDuracao] = useState(1)
  const [justificativa, setJustificativa] = useState('')
  const [aviso, setAviso] = useState<string | null>(null)
  const catalogo = useQuery({ queryKey: ['autorizacao', 'permissoes-solicitaveis'], queryFn: catalogoSolicitavel })
  const escopos = useQuery({ queryKey: ['autorizacao', 'escopos-solicitaveis'], queryFn: listarEscoposDisponiveis })
  const proprias = useQuery({ queryKey: [...CHAVE, 'proprias'], queryFn: () => listarSolicitacoes('proprias') })
  const aprovaveis = useQuery({ queryKey: [...CHAVE, 'aprovaveis'], queryFn: () => listarSolicitacoes('aprovaveis'), enabled: podeAprovar })
  const permissaoEscolhida = catalogo.data?.find((item) => item.chave === permissao)
  const grupos = useMemo(() => [...new Set((catalogo.data ?? []).map((item) => item.grupo))], [catalogo.data])
  const regionais = useMemo(() => [...new Map((escopos.data ?? []).flatMap((item) => item.regional ? [[item.regional.uuid, item.regional] as const] : [])).values()], [escopos.data])

  const recarregar = async (erro?: RecusaSolicitacao): Promise<void> => {
    setAviso(mensagem(erro))
    await Promise.all([
      cliente.invalidateQueries({ queryKey: CHAVE }),
      cliente.invalidateQueries({ queryKey: ['me'] }),
    ])
  }
  const criar = useMutation({
    mutationFn: () => criarSolicitacao({
      permissoes: [{
        chave: permissao,
        categoria_sensivel: permissaoEscolhida?.categoria_sensivel ?? null,
        escopo_tipo: escopoTipo,
        escopo_id: escopoTipo === 'organizacao' ? null : escopoId,
      }],
      duracao_horas: duracao,
      justificativa: justificativa.trim(),
    }),
    onSuccess: async (resultado) => {
      const erro = resultado.erro
      registrarEventoUi('ui.solicitacao_permissao_criada', { sucesso: !erro, duracao_horas: duracao })
      setAviso(erro ? mensagem(erro) : t('solicitacoes.enviada'))
      if (!erro) {
        setJustificativa('')
        await cliente.invalidateQueries({ queryKey: CHAVE })
      }
    },
  })
  const enviar = (evento: FormEvent): void => { evento.preventDefault(); criar.mutate() }
  const escopoValido = escopoTipo === 'organizacao' || escopoId !== ''

  return <Box sx={{ maxWidth: 960 }}>
    <Typography component="h1" variant="h4" gutterBottom>{t('solicitacoes.titulo')}</Typography>
    {aviso && <Alert severity={aviso === t('solicitacoes.enviada') ? 'success' : 'warning'} sx={{ mb: 2 }}>{aviso}</Alert>}
    {(catalogo.isError || escopos.isError) && <Alert severity="error" sx={{ mb: 2 }}>Não foi possível carregar as opções do pedido.</Alert>}
    <Stack component="form" aria-label={t('solicitacoes.formulario')} onSubmit={enviar} spacing={2} sx={{ mb: 4 }}>
      <FormControl required><InputLabel id="permissao-label">{t('solicitacoes.permissao')}</InputLabel>
        <Select labelId="permissao-label" label={t('solicitacoes.permissao')} value={permissao} onChange={(e) => setPermissao(e.target.value)}>
          {grupos.flatMap((grupo) => [
            <ListSubheader key={`${grupo}-cabecalho`}>{grupo}</ListSubheader>,
            ...(catalogo.data ?? []).filter((item) => item.grupo === grupo).map((item) =>
              <MenuItem key={item.chave} value={item.chave}>{item.rotulo}{item.sensivel ? ` — sensível: ${item.categoria_sensivel}` : ''}</MenuItem>),
          ])}
        </Select>
      </FormControl>
      <FormControl required><InputLabel id="escopo-tipo-label">Tipo de escopo</InputLabel>
        <Select labelId="escopo-tipo-label" label="Tipo de escopo" value={escopoTipo} onChange={(e) => { setEscopoTipo(e.target.value as EscopoSolicitado); setEscopoId('') }}>
          <MenuItem value="organizacao">Toda a organização</MenuItem><MenuItem value="regional">Regional</MenuItem><MenuItem value="igreja">Igreja</MenuItem>
        </Select>
      </FormControl>
      {escopoTipo !== 'organizacao' && <FormControl required><InputLabel id="escopo-label">Escopo</InputLabel>
        <Select labelId="escopo-label" label="Escopo" value={escopoId} onChange={(e) => setEscopoId(e.target.value)}>
          {(escopoTipo === 'regional' ? regionais : (escopos.data ?? [])).map((item) => <MenuItem key={item.uuid} value={item.uuid}>{item.nome}</MenuItem>)}
        </Select>
      </FormControl>}
      <TextField required type="number" label={t('solicitacoes.duracao')} slotProps={{ htmlInput: { min: 1, max: 72 } }} value={duracao} onChange={(e) => setDuracao(Number(e.target.value))} />
      <TextField required multiline minRows={3} label={t('solicitacoes.justificativa')} value={justificativa} onChange={(e) => setJustificativa(e.target.value)} />
      <Button type="submit" variant="contained" disabled={!permissao || !justificativa.trim() || !escopoValido || duracao < 1 || duracao > 72 || criar.isPending}>{t('solicitacoes.enviar')}</Button>
    </Stack>

    <Stack component="section" aria-label={t('solicitacoes.minhas')} spacing={2} sx={{ mb: 4 }}>
      <Typography component="h2" variant="h5">{t('solicitacoes.minhas')}</Typography>
      <EstadoLista carregando={proprias.isLoading} falhou={proprias.isError} vazio={proprias.data?.length === 0} />
      {(proprias.data ?? []).map((item) => <Cartao key={item.uuid} item={item} aprovavel={false} aoMudar={(erro) => void recarregar(erro)} />)}
    </Stack>
    {podeAprovar && <Stack component="section" aria-label={t('solicitacoes.aprovaveis')} spacing={2}>
      <Typography component="h2" variant="h5">{t('solicitacoes.aprovaveis')}</Typography>
      <EstadoLista carregando={aprovaveis.isLoading} falhou={aprovaveis.isError} vazio={aprovaveis.data?.length === 0} />
      {(aprovaveis.data ?? []).map((item) => <Cartao key={item.uuid} item={item} aprovavel aoMudar={(erro) => void recarregar(erro)} />)}
    </Stack>}
  </Box>
}
