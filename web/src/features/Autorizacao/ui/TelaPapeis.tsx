import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Checkbox from '@mui/material/Checkbox'
import Chip from '@mui/material/Chip'
import FormControlLabel from '@mui/material/FormControlLabel'
import FormGroup from '@mui/material/FormGroup'
import List from '@mui/material/List'
import ListItemButton from '@mui/material/ListItemButton'
import Stack from '@mui/material/Stack'
import TextField from '@mui/material/TextField'
import Typography from '@mui/material/Typography'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { listarPapeis, listarPermissoes, salvarPapel, type Papel, type Recusa } from '../api/acessos'
import { agrupar } from '../lib/catalogo'

interface PropsEditor {
  papel: Papel | null
  catalogo: string[]
  aoSalvar: () => void
}

function EditorDePapel({ papel, catalogo, aoSalvar }: PropsEditor): ReactNode {
  const { t } = useTranslation('autorizacao')
  const [nome, setNome] = useState(papel?.nome ?? '')
  const [marcadas, setMarcadas] = useState<Set<string>>(new Set(papel?.permissoes ?? []))
  const [recusa, setRecusa] = useState<Recusa | null>(null)

  const alternar = (permissao: string): void => {
    const proximas = new Set(marcadas)
    if (proximas.has(permissao)) proximas.delete(permissao)
    else proximas.add(permissao)
    setMarcadas(proximas)
  }

  const enviar = async (evento: FormEvent): Promise<void> => {
    evento.preventDefault()
    const resultado = await salvarPapel({ uuid: papel?.uuid, nome: nome.trim(), permissoes: [...marcadas] })
    setRecusa(resultado)
    if (resultado === null) aoSalvar()
  }

  return (
    <Stack component="form" spacing={2} onSubmit={(e) => void enviar(e)} aria-label={t('papeis.editor')}>
      {recusa && (
        <Alert severity="error">{t(`recusas.${recusa.codigo}`, { defaultValue: t('recusas.falha') })}</Alert>
      )}
      <TextField label={t('papeis.nome')} value={nome} onChange={(e) => setNome(e.target.value)} required />
      {agrupar(catalogo).map((grupo) => (
        <Box component="fieldset" key={`${grupo.grupo}-${grupo.categoria ?? ''}`} sx={{ border: 0, p: 0, m: 0 }}>
          <Typography component="legend" variant="subtitle1" sx={{ display: 'flex', gap: 1, alignItems: 'center' }}>
            {grupo.categoria ? t(`papeis.categorias.${grupo.categoria}`) : t(`papeis.grupos.${grupo.grupo}`)}
            {grupo.grupo === 'sensiveis' && <Chip size="small" color="warning" label={t('papeis.sensivel')} />}
          </Typography>
          <FormGroup>
            {grupo.permissoes.map((permissao) => (
              <FormControlLabel
                key={permissao}
                control={<Checkbox checked={marcadas.has(permissao)} onChange={() => alternar(permissao)} />}
                label={t(`permissoes.${permissao.replaceAll('.', '_')}`, { defaultValue: permissao })}
              />
            ))}
          </FormGroup>
        </Box>
      ))}
      <Box><Button type="submit" variant="contained" disabled={nome.trim() === ''}>{t('papeis.salvar')}</Button></Box>
    </Stack>
  )
}

/**
 * Papéis (T-40; OIK-ADR-0004 §2, §3): criar e editar com as permissões do catálogo do servidor, agrupadas,
 * e as sensíveis marcadas por categoria. `ultimo_administrador`, `administrador_sem_governanca` e
 * `escalada_bloqueada` têm mensagens próprias.
 */
export function TelaPapeis(): ReactNode {
  const { t } = useTranslation('autorizacao')
  const cliente = useQueryClient()
  const papeis = useQuery({ queryKey: ['autorizacao', 'papeis'], queryFn: listarPapeis })
  const catalogo = useQuery({ queryKey: ['autorizacao', 'permissoes'], queryFn: listarPermissoes, staleTime: Infinity })
  const [editando, setEditando] = useState<Papel | 'novo' | null>(null)
  const [salvo, setSalvo] = useState(false)

  const aoSalvar = async (): Promise<void> => {
    setSalvo(true)
    setEditando(null)
    await cliente.invalidateQueries({ queryKey: ['autorizacao', 'papeis'] })
  }

  return (
    <Box component="main" sx={{ p: 3, maxWidth: 960, mx: 'auto' }}>
      <Stack spacing={2}>
        <Typography variant="h1" sx={{ fontSize: '1.75rem' }}>{t('papeis.titulo')}</Typography>
        {salvo && <Alert severity="success">{t('papeis.salvoOk')}</Alert>}
        <List aria-label={t('papeis.titulo')}>
          {(papeis.data ?? []).map((papel) => (
            <ListItemButton key={papel.uuid} onClick={() => { setSalvo(false); setEditando(papel) }}>
              {papel.nome}
            </ListItemButton>
          ))}
        </List>
        <Box><Button onClick={() => { setSalvo(false); setEditando('novo') }}>{t('papeis.novo')}</Button></Box>
        {editando !== null && catalogo.data && (
          <EditorDePapel
            key={editando === 'novo' ? 'novo' : editando.uuid}
            papel={editando === 'novo' ? null : editando}
            catalogo={catalogo.data}
            aoSalvar={() => void aoSalvar()}
          />
        )}
      </Stack>
    </Box>
  )
}
