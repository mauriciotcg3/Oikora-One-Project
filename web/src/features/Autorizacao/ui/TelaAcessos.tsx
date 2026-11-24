import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Dialog from '@mui/material/Dialog'
import DialogActions from '@mui/material/DialogActions'
import DialogContent from '@mui/material/DialogContent'
import DialogContentText from '@mui/material/DialogContentText'
import DialogTitle from '@mui/material/DialogTitle'
import FormControlLabel from '@mui/material/FormControlLabel'
import MenuItem from '@mui/material/MenuItem'
import Radio from '@mui/material/Radio'
import RadioGroup from '@mui/material/RadioGroup'
import Stack from '@mui/material/Stack'
import Table from '@mui/material/Table'
import TableBody from '@mui/material/TableBody'
import TableCell from '@mui/material/TableCell'
import TableHead from '@mui/material/TableHead'
import TableRow from '@mui/material/TableRow'
import TextField from '@mui/material/TextField'
import Typography from '@mui/material/Typography'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import {
  buscarPessoas,
  convidar,
  listarAcessos,
  listarIgrejas,
  listarPapeis,
  revogarAcesso,
  type Acesso,
  type EscopoTipo,
  type PessoaAchada,
  type Recusa,
} from '../api/acessos'

const chave = ['autorizacao', 'acessos'] as const

/** Mensagem de recusa com código próprio; o resto cai na genérica. */
function useMensagemRecusa(): (recusa: Recusa) => string {
  const { t } = useTranslation('autorizacao')
  return (recusa) => t(`recusas.${recusa.codigo}`, { defaultValue: t('recusas.falha') })
}

function FormularioConvite(): ReactNode {
  const { t } = useTranslation('autorizacao')
  const mensagem = useMensagemRecusa()
  const cliente = useQueryClient()
  const papeis = useQuery({ queryKey: ['autorizacao', 'papeis'], queryFn: listarPapeis })
  const igrejas = useQuery({ queryKey: ['autorizacao', 'igrejas'], queryFn: listarIgrejas })
  const [termo, setTermo] = useState('')
  const [achadas, setAchadas] = useState<PessoaAchada[] | null>(null)
  const [pessoa, setPessoa] = useState('')
  const [papel, setPapel] = useState('')
  const [escopoTipo, setEscopoTipo] = useState<EscopoTipo>('igreja')
  const [igreja, setIgreja] = useState('')
  const [recusa, setRecusa] = useState<Recusa | null>(null)
  const [enviado, setEnviado] = useState(false)

  const buscar = async (): Promise<void> => {
    if (termo.trim().length < 3) return
    setAchadas(await buscarPessoas(termo.trim()))
  }

  const enviar = async (evento: FormEvent): Promise<void> => {
    evento.preventDefault()
    setEnviado(false)
    const resultado = await convidar({
      pessoa,
      papel,
      escopo_tipo: escopoTipo,
      ...(escopoTipo === 'igreja' ? { escopo: igreja } : {}),
    })
    setRecusa(resultado)
    if (resultado === null) {
      setEnviado(true)
      await cliente.invalidateQueries({ queryKey: chave })
    }
  }

  const erroCampo = (campo: string): string | undefined =>
    recusa?.campos?.[campo] ? t(`campos.${recusa.campos[campo]}`, { defaultValue: recusa.campos[campo] }) : undefined

  return (
    <Stack component="form" spacing={2} onSubmit={(e) => void enviar(e)} aria-labelledby="titulo-convidar">
      <Typography id="titulo-convidar" variant="h2" sx={{ fontSize: '1.25rem' }}>{t('convidar.titulo')}</Typography>
      {enviado && <Alert severity="success">{t('convidar.enviado')}</Alert>}
      {recusa && !recusa.campos && <Alert severity="error">{mensagem(recusa)}</Alert>}

      <Stack direction="row" spacing={1}>
        <TextField label={t('convidar.buscar')} value={termo} onChange={(e) => setTermo(e.target.value)} size="small" fullWidth />
        <Button onClick={() => void buscar()}>{t('convidar.botaoBuscar')}</Button>
      </Stack>
      {achadas !== null && (
        <TextField
          select
          label={t('convidar.pessoa')}
          value={pessoa}
          onChange={(e) => setPessoa(e.target.value)}
          error={erroCampo('pessoa') !== undefined}
          helperText={erroCampo('pessoa') ?? (achadas.length === 0 ? t('convidar.ninguem') : undefined)}
        >
          {achadas.map((achada) => (
            <MenuItem key={achada.uuid} value={achada.uuid}>
              {achada.nome}{achada.igreja ? ` — ${achada.igreja.nome}` : ''}
            </MenuItem>
          ))}
        </TextField>
      )}

      <TextField select label={t('convidar.papel')} value={papel} onChange={(e) => setPapel(e.target.value)} error={erroCampo('papel') !== undefined} helperText={erroCampo('papel')}>
        {(papeis.data ?? []).map((p) => <MenuItem key={p.uuid} value={p.uuid}>{p.nome}</MenuItem>)}
      </TextField>

      <RadioGroup row aria-label={t('convidar.escopo')} value={escopoTipo} onChange={(e) => setEscopoTipo(e.target.value as EscopoTipo)}>
        <FormControlLabel value="igreja" control={<Radio />} label={t('convidar.escopoIgreja')} />
        <FormControlLabel value="organizacao" control={<Radio />} label={t('convidar.escopoOrganizacao')} />
      </RadioGroup>
      {escopoTipo === 'igreja' && (
        <TextField select label={t('convidar.igreja')} value={igreja} onChange={(e) => setIgreja(e.target.value)} error={erroCampo('escopo') !== undefined} helperText={erroCampo('escopo')}>
          {(igrejas.data ?? []).map((i) => <MenuItem key={i.uuid} value={i.uuid}>{i.nome}</MenuItem>)}
        </TextField>
      )}

      <Box><Button type="submit" variant="contained" disabled={pessoa === '' || papel === ''}>{t('convidar.enviar')}</Button></Box>
    </Stack>
  )
}

/**
 * Acessos e convites (T-40; OIK-ADR-0003 §5): convidar uma Pessoa com papel e escopo, revogar acesso
 * com confirmação. Os botões seguem `meta.acoes`; o servidor continua sendo a autoridade.
 */
export function TelaAcessos(): ReactNode {
  const { t } = useTranslation('autorizacao')
  const mensagem = useMensagemRecusa()
  const cliente = useQueryClient()
  const lista = useQuery({ queryKey: chave, queryFn: listarAcessos })
  const [revogando, setRevogando] = useState<Acesso | null>(null)
  const [recusa, setRecusa] = useState<Recusa | null>(null)
  const revogar = useMutation({
    mutationFn: (uuid: string) => revogarAcesso(uuid),
    onSuccess: async (resultado) => {
      setRecusa(resultado)
      setRevogando(null)
      await cliente.invalidateQueries({ queryKey: chave })
    },
  })

  return (
    <Box component="main" sx={{ p: 3, maxWidth: 960, mx: 'auto' }}>
      <Stack spacing={3}>
        <Typography variant="h1" sx={{ fontSize: '1.75rem' }}>{t('acessos.titulo')}</Typography>
        {recusa && <Alert severity="error">{mensagem(recusa)}</Alert>}
        {lista.isError && <Alert severity="error">{t('recusas.falha')}</Alert>}

        <Table aria-label={t('acessos.titulo')} size="small">
          <TableHead>
            <TableRow>
              <TableCell>{t('acessos.pessoa')}</TableCell>
              <TableCell>{t('acessos.papeis')}</TableCell>
              <TableCell>{t('acessos.estado')}</TableCell>
              <TableCell align="right">{t('acessos.acoes')}</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {(lista.data?.acessos ?? []).map((acesso) => (
              <TableRow key={acesso.uuid}>
                <TableCell>{acesso.pessoa.nome}</TableCell>
                <TableCell>{acesso.papeis.map((p) => p.nome).join(', ')}</TableCell>
                <TableCell>{t(`acessos.estados.${acesso.estado}`)}</TableCell>
                <TableCell align="right">
                  {acesso.meta.acoes.includes('revogar') && (
                    <Button color="error" onClick={() => setRevogando(acesso)} aria-label={t('acessos.revogarDe', { nome: acesso.pessoa.nome })}>
                      {t('acessos.revogar')}
                    </Button>
                  )}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>

        {(lista.data?.convites.length ?? 0) > 0 && (
          <Stack spacing={1}>
            <Typography variant="h2" sx={{ fontSize: '1.25rem' }}>{t('acessos.convitesPendentes')}</Typography>
            {lista.data?.convites.map((c) => <Typography key={c.uuid}>{c.pessoa.nome}</Typography>)}
          </Stack>
        )}

        <FormularioConvite />
      </Stack>

      <Dialog open={revogando !== null} onClose={() => setRevogando(null)} aria-labelledby="titulo-revogar">
        <DialogTitle id="titulo-revogar">{t('acessos.confirmarTitulo')}</DialogTitle>
        <DialogContent>
          <DialogContentText>{t('acessos.confirmarTexto', { nome: revogando?.pessoa.nome ?? '' })}</DialogContentText>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setRevogando(null)}>{t('acessos.cancelar')}</Button>
          <Button color="error" disabled={revogar.isPending} onClick={() => revogando && revogar.mutate(revogando.uuid)}>
            {t('acessos.confirmarRevogar')}
          </Button>
        </DialogActions>
      </Dialog>
    </Box>
  )
}
