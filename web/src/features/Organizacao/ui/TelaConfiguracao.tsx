import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import MenuItem from '@mui/material/MenuItem'
import Stack from '@mui/material/Stack'
import Tab from '@mui/material/Tab'
import Tabs from '@mui/material/Tabs'
import TextField from '@mui/material/TextField'
import Typography from '@mui/material/Typography'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent, type ReactNode } from 'react'
import { ErroHttp } from '../../../shared/http'
import { EstadoCarregando, EstadoErro } from '../../../shared/ui'
import { criarIgreja, criarValor, editarIgreja, editarValor, listarIgrejas, listarValores, obterObrigatorios, obterOrganizacao, salvarObrigatorios, salvarOrganizacao, type Igreja, type ItemLista } from '../api/configuracao'

const LISTAS = ['como_chegou', 'motivo_vinculo', 'motivo_inativacao', 'motivo_reativacao', 'tipo_membro', 'funcao'] as const
const erroDe = (erro: unknown): string => erro instanceof ErroHttp ? (erro.codigo ?? 'Verifique os campos informados.') : 'Não foi possível salvar.'

export function TelaConfiguracao(): ReactNode {
  const [aba, setAba] = useState(0)
  const organizacao = useQuery({ queryKey: ['organizacao-config'], queryFn: obterOrganizacao })
  if (organizacao.isPending) return <EstadoCarregando texto="Carregando configuração…" />
  if (organizacao.isError) return <EstadoErro titulo="Configuração indisponível" texto="Não foi possível carregar os dados." rotuloTentar="Tentar novamente" aoTentarDeNovo={() => void organizacao.refetch()} />
  return <Stack spacing={3}><Typography variant="h4">Configuração</Typography><Tabs value={aba} onChange={(_, valor: number) => setAba(valor)} variant="scrollable"><Tab label="Organização" /><Tab label="Igrejas" /><Tab label="Listas" /><Tab label="Campos obrigatórios" /></Tabs>{aba === 0 && <FormularioOrganizacao inicial={organizacao.data.data} />}{aba === 1 && <EditorIgrejas />}{aba === 2 && <EditorListas />}{aba === 3 && <EditorObrigatorios />}</Stack>
}

function FormularioOrganizacao({ inicial }: { inicial: Awaited<ReturnType<typeof obterOrganizacao>>['data'] }): ReactNode {
  const [form, setForm] = useState({ nome: inicial.nome, fuso: inicial.fuso, cor_primaria: inicial.cor_primaria ?? '', modo_matricula: inicial.modo_matricula, limite_whatsapp_mensal: inicial.limite_whatsapp_mensal?.toString() ?? '', email_contato: inicial.email_contato ?? '' })
  const salvar = useMutation({ mutationFn: () => salvarOrganizacao({ ...form, cor_primaria: form.cor_primaria || null, limite_whatsapp_mensal: form.limite_whatsapp_mensal === '' ? null : Number(form.limite_whatsapp_mensal), email_contato: form.email_contato || null }) })
  return <Stack component="form" spacing={2} maxWidth={720} onSubmit={(e: FormEvent) => { e.preventDefault(); salvar.mutate() }}><TextField required label="Nome" value={form.nome} onChange={(e) => setForm({ ...form, nome: e.target.value })} /><TextField required label="Fuso horário" value={form.fuso} onChange={(e) => setForm({ ...form, fuso: e.target.value })} /><TextField label="Cor primária" placeholder="#1565C0" value={form.cor_primaria} onChange={(e) => setForm({ ...form, cor_primaria: e.target.value })} error={salvar.isError && erroDe(salvar.error).includes('cor')} helperText={salvar.isError ? erroDe(salvar.error) : 'Formato hexadecimal, por exemplo #1565C0.'} /><TextField select label="Modo da matrícula" value={form.modo_matricula} onChange={(e) => setForm({ ...form, modo_matricula: e.target.value })}><MenuItem value="automatica">Automática</MenuItem><MenuItem value="manual">Manual</MenuItem></TextField><TextField type="number" label="Limite mensal do WhatsApp" value={form.limite_whatsapp_mensal} onChange={(e) => setForm({ ...form, limite_whatsapp_mensal: e.target.value })} /><TextField type="email" label="E-mail de contato" value={form.email_contato} onChange={(e) => setForm({ ...form, email_contato: e.target.value })} /><Button type="submit" variant="contained" disabled={salvar.isPending}>Salvar organização</Button></Stack>
}

function EditorIgrejas(): ReactNode {
  const cliente = useQueryClient(); const [nome, setNome] = useState('')
  const consulta = useQuery({ queryKey: ['igrejas-config'], queryFn: listarIgrejas })
  const criar = useMutation({ mutationFn: () => criarIgreja(nome), onSuccess: () => { setNome(''); void cliente.invalidateQueries({ queryKey: ['igrejas-config'] }) } })
  return <Stack spacing={2}><Stack direction="row" gap={1}><TextField label="Nome da nova igreja" value={nome} onChange={(e) => setNome(e.target.value)} /><Button onClick={() => criar.mutate()} disabled={nome.trim().length < 2}>Adicionar</Button></Stack>{consulta.data?.data.map((igreja) => <EditorIgreja key={igreja.uuid} igreja={igreja} aoSalvar={() => cliente.invalidateQueries({ queryKey: ['igrejas-config'] })} />)}</Stack>
}

function EditorIgreja({ igreja, aoSalvar }: { igreja: Igreja; aoSalvar: () => Promise<unknown> }): ReactNode {
  const [nome, setNome] = useState(igreja.nome)
  const salvar = useMutation({ mutationFn: () => editarIgreja(igreja.uuid, { nome }), onSuccess: () => { void aoSalvar() } })
  return <Card variant="outlined"><CardContent><Stack direction={{ xs: 'column', sm: 'row' }} gap={1} alignItems={{ sm: 'center' }}><TextField label="Nome da igreja" value={nome} onChange={(e) => setNome(e.target.value)} sx={{ flex: 1 }} /><Typography color="text.secondary">{igreja.regional?.nome ?? 'Sem regional'}</Typography><Button onClick={() => salvar.mutate()} disabled={nome.trim().length < 2 || salvar.isPending}>Salvar</Button></Stack></CardContent></Card>
}

function EditorListas(): ReactNode {
  const cliente = useQueryClient(); const [lista, setLista] = useState<string>(LISTAS[0]); const [nome, setNome] = useState('')
  const consulta = useQuery({ queryKey: ['lista-config', lista], queryFn: () => listarValores(lista) })
  const criar = useMutation({ mutationFn: () => criarValor(lista, nome), onSuccess: () => { setNome(''); void cliente.invalidateQueries({ queryKey: ['lista-config', lista] }) } })
  return <Stack spacing={2}><TextField select label="Lista" value={lista} onChange={(e) => setLista(e.target.value)}>{LISTAS.map((item) => <MenuItem key={item} value={item}>{item.replaceAll('_', ' ')}</MenuItem>)}</TextField><Stack direction="row" gap={1}><TextField label="Novo valor" value={nome} onChange={(e) => setNome(e.target.value)} /><Button onClick={() => criar.mutate()} disabled={!nome.trim()}>Adicionar</Button></Stack>{consulta.data?.data.map((item) => <EditorItemLista key={item.uuid} lista={lista} item={item} aoSalvar={() => cliente.invalidateQueries({ queryKey: ['lista-config', lista] })} />)}</Stack>
}

function EditorItemLista({ lista, item, aoSalvar }: { lista: string; item: ItemLista; aoSalvar: () => Promise<unknown> }): ReactNode {
  const [nome, setNome] = useState(item.nome)
  const salvar = useMutation({ mutationFn: (mudancas: Partial<Pick<ItemLista, 'nome' | 'ordem' | 'ativo'>>) => editarValor(lista, item.uuid, mudancas), onSuccess: () => { void aoSalvar() } })
  return <Card variant="outlined"><CardContent><Stack direction={{ xs: 'column', sm: 'row' }} gap={1} alignItems={{ sm: 'center' }}><TextField label="Valor" value={nome} onChange={(e) => setNome(e.target.value)} sx={{ flex: 1 }} /><Button onClick={() => salvar.mutate({ nome })}>Salvar</Button><Button onClick={() => salvar.mutate({ ordem: Math.max(0, item.ordem - 1) })}>Subir</Button><Button onClick={() => salvar.mutate({ ordem: item.ordem + 1 })}>Descer</Button><Button color={item.ativo ? 'error' : 'primary'} onClick={() => salvar.mutate({ ativo: !item.ativo })}>{item.ativo ? 'Desativar' : 'Reativar'}</Button></Stack></CardContent></Card>
}

function EditorObrigatorios(): ReactNode {
  const consulta = useQuery({ queryKey: ['obrigatorios-config'], queryFn: obterObrigatorios }); const [vinculo, setVinculo] = useState('visitante'); const [rascunhos, setRascunhos] = useState<Record<string, string>>({})
  const campos = rascunhos[vinculo] ?? (consulta.data?.data[vinculo] ?? []).join(', ')
  const salvar = useMutation({ mutationFn: () => salvarObrigatorios(vinculo, campos.split(',').map((c) => c.trim()).filter(Boolean)) })
  return <Stack spacing={2}><TextField select label="Vínculo" value={vinculo} onChange={(e) => setVinculo(e.target.value)}>{['visitante', 'frequentador', 'membro'].map((v) => <MenuItem key={v} value={v}>{v}</MenuItem>)}</TextField><TextField multiline minRows={3} label="Campos obrigatórios (separados por vírgula)" value={campos} onChange={(e) => setRascunhos({ ...rascunhos, [vinculo]: e.target.value })} /><Button variant="contained" onClick={() => salvar.mutate()}>Salvar campos</Button></Stack>
}
