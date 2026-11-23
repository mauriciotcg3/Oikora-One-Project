import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Stack from '@mui/material/Stack'
import Table from '@mui/material/Table'
import TableBody from '@mui/material/TableBody'
import TableCell from '@mui/material/TableCell'
import TableHead from '@mui/material/TableHead'
import TableRow from '@mui/material/TableRow'
import TextField from '@mui/material/TextField'
import Typography from '@mui/material/Typography'
import { useQuery } from '@tanstack/react-query'
import { useState, type FormEvent, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { consultarAuditoria, type FiltroAuditoria } from '../api/auditoria'

/**
 * Consulta da auditoria (T-40; OIK-ADR-0005 §5): filtra por período e evento e mostra quem, quando, o
 * quê e em qual igreja. Campos alterados aparecem só pelo nome: a API não manda valores, e a tela
 * também não mostra os metadados técnicos.
 */
export function TelaAuditoria(): ReactNode {
  const { t, i18n } = useTranslation('auditoria')
  const [rascunho, setRascunho] = useState<FiltroAuditoria>({})
  const [filtro, setFiltro] = useState<FiltroAuditoria>({})
  const consulta = useQuery({ queryKey: ['auditoria', filtro], queryFn: () => consultarAuditoria(filtro) })

  const aplicar = (evento: FormEvent): void => {
    evento.preventDefault()
    setFiltro({ ...rascunho, pagina: 1 })
  }

  const data = (iso: string): string =>
    new Intl.DateTimeFormat(i18n.language, { dateStyle: 'short', timeStyle: 'short' }).format(new Date(iso))

  return (
    <Box component="main" sx={{ p: 3, maxWidth: 1100, mx: 'auto' }}>
      <Stack spacing={2}>
        <Typography variant="h1" sx={{ fontSize: '1.75rem' }}>{t('titulo')}</Typography>
        <Stack component="form" direction={{ xs: 'column', sm: 'row' }} spacing={2} onSubmit={aplicar} aria-label={t('filtros')}>
          <TextField label={t('de')} type="date" value={rascunho.de ?? ''} onChange={(e) => setRascunho({ ...rascunho, de: e.target.value })} slotProps={{ inputLabel: { shrink: true } }} />
          <TextField label={t('ate')} type="date" value={rascunho.ate ?? ''} onChange={(e) => setRascunho({ ...rascunho, ate: e.target.value })} slotProps={{ inputLabel: { shrink: true } }} />
          <TextField label={t('evento')} placeholder="pessoa.atualizada" value={rascunho.evento ?? ''} onChange={(e) => setRascunho({ ...rascunho, evento: e.target.value.trim() })} />
          <Button type="submit" variant="contained">{t('filtrar')}</Button>
        </Stack>

        {consulta.isError && <Alert severity="error">{t('erro')}</Alert>}
        {consulta.data && <Typography color="text.secondary">{t('total', { count: consulta.data.total })}</Typography>}

        <Table size="small" aria-label={t('titulo')}>
          <TableHead>
            <TableRow>
              <TableCell>{t('quando')}</TableCell>
              <TableCell>{t('evento')}</TableCell>
              <TableCell>{t('alvo')}</TableCell>
              <TableCell>{t('igreja')}</TableCell>
              <TableCell>{t('campos')}</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {(consulta.data?.itens ?? []).map((linha, indice) => (
              <TableRow key={`${linha.ocorrido_em}-${indice}`}>
                <TableCell>{data(linha.ocorrido_em)}</TableCell>
                <TableCell>{t(`eventos.${linha.evento.replaceAll('.', '_')}`, { defaultValue: linha.evento })}</TableCell>
                <TableCell>{linha.alvo.tipo}</TableCell>
                <TableCell>{linha.igreja?.nome ?? '—'}</TableCell>
                <TableCell>{linha.campos.length > 0 ? linha.campos.join(', ') : '—'}</TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </Stack>
    </Box>
  )
}
