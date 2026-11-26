import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Divider from '@mui/material/Divider'
import Stack from '@mui/material/Stack'
import Switch from '@mui/material/Switch'
import Typography from '@mui/material/Typography'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, type ReactNode } from 'react'
import { EstadoCarregando, EstadoVazio } from '../../../shared/ui'
import { definirPreferencia, desligarWhatsapp, listarAvisos, listarPreferencias, marcarAviso, marcarTodos } from '../api/avisos'

export function TelaWhatsapp(): ReactNode {
  const desligar = useMutation({ mutationFn: desligarWhatsapp })
  return <Stack spacing={3} maxWidth={760}><Typography variant="h4">Integração com WhatsApp</Typography><Typography color="text.secondary">Conecte o WhatsApp Business da organização para enviar avisos. A Meta abrirá a autorização em uma nova etapa.</Typography><Stack direction={{ xs: 'column', sm: 'row' }} gap={1}><Button variant="contained" href="/api/v1/painel/avisos/whatsapp/oauth">Conectar com a Meta</Button><Button color="error" onClick={() => desligar.mutate()} disabled={desligar.isPending}>Desconectar número atual</Button></Stack><Typography variant="body2">A conexão exige credenciais válidas do provedor no ambiente. Se a Meta não abrir, confirme a configuração do backend.</Typography></Stack>
}

export function TelaAvisos(): ReactNode {
  const cliente = useQueryClient()
  const avisos = useQuery({ queryKey: ['avisos'], queryFn: listarAvisos, refetchInterval: 60_000 })
  const preferencias = useQuery({ queryKey: ['preferencias-avisos'], queryFn: listarPreferencias })
  useEffect(() => { const atualizar = (): void => { if (document.visibilityState === 'visible') void avisos.refetch() }; window.addEventListener('focus', atualizar); document.addEventListener('visibilitychange', atualizar); return () => { window.removeEventListener('focus', atualizar); document.removeEventListener('visibilitychange', atualizar) } }, [avisos])
  const todos = useMutation({ mutationFn: marcarTodos, onSuccess: () => void cliente.invalidateQueries({ queryKey: ['avisos'] }) })
  const ler = useMutation({ mutationFn: marcarAviso, onSuccess: () => void cliente.invalidateQueries({ queryKey: ['avisos'] }) })
  const preferir = useMutation({ mutationFn: ({ tipo, canal, ligado }: { tipo: string; canal: string; ligado: boolean }) => definirPreferencia(tipo, canal, ligado), onSuccess: () => void cliente.invalidateQueries({ queryKey: ['preferencias-avisos'] }) })
  if (avisos.isPending || preferencias.isPending) return <EstadoCarregando texto="Carregando avisos…" />
  return <Stack spacing={3}><Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" gap={1}><Typography variant="h4">Avisos ({avisos.data?.meta.nao_lidos ?? 0} não lidos)</Typography><Button onClick={() => todos.mutate()} disabled={(avisos.data?.meta.nao_lidos ?? 0) === 0}>Marcar todos como lidos</Button></Stack>{avisos.data?.data.length === 0 && <EstadoVazio titulo="Tudo em dia" texto="Não há avisos para mostrar." />}{avisos.data?.data.map((aviso) => <Card key={aviso.id} variant={aviso.lido_em ? 'outlined' : 'elevation'}><CardContent><Typography variant="h6">{aviso.titulo}</Typography><Typography>{aviso.texto}</Typography><Stack direction="row" gap={1} mt={1}>{aviso.destino && <Button href={aviso.destino}>{aviso.acao}</Button>}{!aviso.lido_em && <Button onClick={() => ler.mutate(aviso.id)}>Marcar como lido</Button>}</Stack></CardContent></Card>)}<Divider /><Typography variant="h5">Preferências</Typography>{preferencias.data?.data.map((preferencia) => <Card key={preferencia.tipo} variant="outlined"><CardContent><Typography fontWeight={700}>{preferencia.tipo.replaceAll('.', ' ')}</Typography><Stack direction="row" gap={2}>{preferencia.canais.map((canal) => <Stack key={canal.canal} direction="row" alignItems="center"><Switch checked={canal.ligado} disabled={preferencia.obrigatorio || !preferencia.permite_escolher_canal} onChange={(_, ligado) => preferir.mutate({ tipo: preferencia.tipo, canal: canal.canal, ligado })} slotProps={{ input: { 'aria-label': `${canal.canal} para ${preferencia.tipo}` } }} /><Typography>{canal.canal}</Typography></Stack>)}</Stack>{preferencia.obrigatorio && <Typography variant="caption">Aviso obrigatório</Typography>}</CardContent></Card>)}</Stack>
}
