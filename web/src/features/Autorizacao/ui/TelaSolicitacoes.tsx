import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import FormControl from '@mui/material/FormControl'
import InputLabel from '@mui/material/InputLabel'
import MenuItem from '@mui/material/MenuItem'
import Select from '@mui/material/Select'
import Stack from '@mui/material/Stack'
import TextField from '@mui/material/TextField'
import Typography from '@mui/material/Typography'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import {
  aprovarSolicitacao, cancelarSolicitacao, catalogoSolicitavel, criarSolicitacao,
  listarSolicitacoes, recusarSolicitacao, revogarConcessao, type RecusaSolicitacao,
  type SolicitacaoPermissao,
} from '../api/solicitacoes'

const CHAVE = ['solicitacoes-permissao'] as const

function categoria(chave: string): string | null {
  const partes = chave.match(/^pessoas\.sensiveis\.([^.]+)\./)
  return partes?.[1] ?? null
}

function mensagem(erro?: RecusaSolicitacao): string | null {
  if (!erro) return null
  if (erro.status === 409) return 'A solicitação foi alterada por outra pessoa. A versão atual foi recarregada; confirme novamente se ainda desejar.'
  const conhecidas: Record<string, string> = {
    sem_permissao: 'Você não tem permissão para esta ação.', escalada_bloqueada: 'O pedido ultrapassa o seu teto de permissões.',
    autoaprovacao_negada: 'Quem solicitou não pode aprovar o próprio pedido.', ultimo_administrador: 'A ação deixaria a organização sem administrador.',
  }
  return conhecidas[erro.codigo] ?? Object.values(erro.campos ?? {})[0] ?? 'Não foi possível concluir a ação.'
}

function Cartao({ item, aprovar, aoMudar }: { item: SolicitacaoPermissao; aprovar: boolean; aoMudar: (erro?: RecusaSolicitacao) => void }): ReactNode {
  const [motivo, setMotivo] = useState('')
  const executar = useMutation({
    mutationFn: async (acao: 'aprovar' | 'recusar' | 'cancelar' | 'revogar') => {
      if (acao === 'aprovar') return aprovarSolicitacao(item.uuid, item.versao)
      if (acao === 'recusar') return recusarSolicitacao(item.uuid, item.versao, motivo)
      if (acao === 'revogar' && item.concessao) return revogarConcessao(item.concessao.uuid, motivo)
      return cancelarSolicitacao(item.uuid)
    },
    onSuccess: (resultado) => aoMudar(resultado.erro),
  })
  return <Box component="article" sx={{ border: 1, borderColor: 'divider', borderRadius: 2, p: 2 }}>
    <Typography component="h3" variant="h6">{item.estado}</Typography>
    <Typography>{item.justificativa}</Typography>
    <Typography variant="body2">{item.duracao_horas}h · {item.permissoes.map((p) => p.chave).join(', ')}</Typography>
    <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} sx={{ mt: 2 }}>
      {aprovar && item.estado === 'pendente' && <>
        <Button variant="contained" disabled={executar.isPending} onClick={() => executar.mutate('aprovar')}>Aprovar</Button>
        <TextField size="small" label="Justificativa da recusa" value={motivo} onChange={(e) => setMotivo(e.target.value)} />
        <Button disabled={!motivo || executar.isPending} onClick={() => executar.mutate('recusar')}>Recusar</Button>
      </>}
      {!aprovar && item.estado === 'pendente' && <Button onClick={() => executar.mutate('cancelar')}>Cancelar solicitação</Button>}
      {aprovar && item.concessao && item.concessao.revogada_em === null && <>
        <TextField size="small" label="Motivo da revogação" value={motivo} onChange={(e) => setMotivo(e.target.value)} />
        <Button color="error" onClick={() => executar.mutate('revogar')}>Revogar concessão</Button>
      </>}
    </Stack>
  </Box>
}

export function TelaSolicitacoes({ podeAprovar = false }: { podeAprovar?: boolean }): ReactNode {
  const { t } = useTranslation('autorizacao')
  const cliente = useQueryClient()
  const [permissao, setPermissao] = useState('')
  const [duracao, setDuracao] = useState(1)
  const [justificativa, setJustificativa] = useState('')
  const [aviso, setAviso] = useState<string | null>(null)
  const catalogo = useQuery({ queryKey: ['permissoes'], queryFn: catalogoSolicitavel })
  const proprias = useQuery({ queryKey: [...CHAVE, 'proprias'], queryFn: () => listarSolicitacoes('proprias') })
  const aprovaveis = useQuery({ queryKey: [...CHAVE, 'aprovaveis'], queryFn: () => listarSolicitacoes('aprovaveis'), enabled: podeAprovar })
  const recarregar = async (erro?: RecusaSolicitacao): Promise<void> => {
    setAviso(mensagem(erro))
    await cliente.invalidateQueries({ queryKey: CHAVE })
  }
  const criar = useMutation({
    mutationFn: () => criarSolicitacao({
      permissoes: [{ chave: permissao, categoria_sensivel: categoria(permissao), escopo_tipo: 'organizacao', escopo_id: null }],
      duracao_horas: duracao, justificativa,
    }),
    onSuccess: async (resultado) => {
      const erro = resultado.erro
      setAviso(erro ? mensagem(erro) : t('solicitacoes.enviada'))
      if (!erro) { setJustificativa(''); await cliente.invalidateQueries({ queryKey: CHAVE }) }
    },
  })
  const enviar = (evento: FormEvent): void => { evento.preventDefault(); criar.mutate() }

  return <Box sx={{ maxWidth: 960 }}>
    <Typography component="h1" variant="h4" gutterBottom>{t('solicitacoes.titulo')}</Typography>
    {aviso && <Alert severity={aviso === t('solicitacoes.enviada') ? 'success' : 'warning'} sx={{ mb: 2 }}>{aviso}</Alert>}
    <Stack component="form" aria-label={t('solicitacoes.formulario')} onSubmit={enviar} spacing={2} sx={{ mb: 4 }}>
      <FormControl required><InputLabel id="permissao-label">{t('solicitacoes.permissao')}</InputLabel>
        <Select labelId="permissao-label" label={t('solicitacoes.permissao')} value={permissao} onChange={(e) => setPermissao(e.target.value)}>
          {(catalogo.data ?? []).map((item) => <MenuItem key={item} value={item}>{item}</MenuItem>)}
        </Select>
      </FormControl>
      <TextField required type="number" label={t('solicitacoes.duracao')} slotProps={{ htmlInput: { min: 1, max: 72 } }} value={duracao} onChange={(e) => setDuracao(Number(e.target.value))} />
      <TextField required multiline minRows={3} label={t('solicitacoes.justificativa')} value={justificativa} onChange={(e) => setJustificativa(e.target.value)} />
      <Button type="submit" variant="contained" disabled={!permissao || !justificativa || duracao < 1 || duracao > 72 || criar.isPending}>{t('solicitacoes.enviar')}</Button>
    </Stack>

    <Stack component="section" aria-label={t('solicitacoes.minhas')} spacing={2} sx={{ mb: 4 }}>
      <Typography component="h2" variant="h5">{t('solicitacoes.minhas')}</Typography>
      {(proprias.data ?? []).map((item) => <Cartao key={item.uuid} item={item} aprovar={false} aoMudar={(erro) => void recarregar(erro)} />)}
    </Stack>
    {podeAprovar && <Stack component="section" aria-label={t('solicitacoes.aprovaveis')} spacing={2}>
      <Typography component="h2" variant="h5">{t('solicitacoes.aprovaveis')}</Typography>
      {(aprovaveis.data ?? []).map((item) => <Cartao key={item.uuid} item={item} aprovar aoMudar={(erro) => void recarregar(erro)} />)}
    </Stack>}
  </Box>
}
