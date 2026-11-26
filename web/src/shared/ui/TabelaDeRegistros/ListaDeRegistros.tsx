import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Checkbox from '@mui/material/Checkbox'
import FormControlLabel from '@mui/material/FormControlLabel'
import LinearProgress from '@mui/material/LinearProgress'
import MenuItem from '@mui/material/MenuItem'
import TextField from '@mui/material/TextField'
import Typography from '@mui/material/Typography'
import { useId, type ReactNode } from 'react'
import { TAMANHOS_DE_PAGINA } from '../FiltrosNaUrl/paginacao'
import { mesclarSelecao } from './selecao'
import type { TabelaDeRegistrosProps } from './TabelaDeRegistros'

/**
 * A mesma tabela em tela estreita: um cartão por linha, com o rótulo de cada coluna ao lado do valor.
 * Não carrega o DataGrid; a paginação continua no servidor.
 */
export function ListaDeRegistros<L extends { id: string; falha?: boolean }>({
  rotulo,
  colunas,
  linhas,
  textoFalha,
  rotuloAbrir,
  textoAbrir,
  aoAbrir,
  paginacao,
  textos,
  selecao,
}: Omit<TabelaDeRegistrosProps<L>, 'textoCarregando'>): ReactNode {
  const idTamanho = useId()
  const [principal, ...demais] = colunas
  const { pagina, porPagina, total } = paginacao
  const de = total === 0 ? 0 : (pagina - 1) * porPagina + 1
  const ate = Math.min(pagina * porPagina, total)
  const marcaveis = selecao ? linhas.filter((linha) => selecao.podeSelecionar?.(linha) ?? true) : []
  const paginaMarcada = marcaveis.length > 0 && marcaveis.every((linha) => selecao?.selecionadas.has(linha.id))

  const marcar = (ids: readonly string[], marcado: boolean): void => {
    if (!selecao) return
    const atuais = linhas.filter((linha) => selecao.selecionadas.has(linha.id)).map((linha) => linha.id)
    const naPagina = marcado ? [...new Set([...atuais, ...ids])] : atuais.filter((id) => !ids.includes(id))
    selecao.aoMudar(mesclarSelecao(selecao.selecionadas, linhas.map((linha) => linha.id), naPagina))
  }

  return (
    <Box role="region" aria-label={rotulo} sx={{ display: 'grid', gap: 1.5, minWidth: 0 }}>
      {paginacao.carregando && <LinearProgress aria-hidden />}
      {selecao && marcaveis.length > 0 && (
        <FormControlLabel
          control={<Checkbox checked={paginaMarcada} onChange={(_, marcado) => marcar(marcaveis.map((linha) => linha.id), marcado)} />}
          label={selecao.rotuloPagina}
        />
      )}
      {linhas.length === 0 ? (
        <Typography color="text.secondary" sx={{ py: 3, textAlign: 'center' }}>
          {textos.semLinhas}
        </Typography>
      ) : (
        <Box component="ul" sx={{ listStyle: 'none', m: 0, p: 0, display: 'grid', gap: 1.5 }}>
          {linhas.map((linha) => (
            <Box
              component="li"
              key={linha.id}
              sx={{
                display: 'grid',
                gap: 1.5,
                p: 2,
                borderRadius: 4,
                border: 1,
                borderColor: linha.falha ? 'error.main' : 'divider',
                bgcolor: linha.falha ? 'error.light' : 'background.paper',
              }}
            >
              <Box sx={{ display: 'flex', alignItems: 'flex-start', gap: 1, minWidth: 0 }}>
                {selecao && (
                  <Checkbox
                    sx={{ mt: -1, ml: -1 }}
                    checked={selecao.selecionadas.has(linha.id)}
                    disabled={!(selecao.podeSelecionar?.(linha) ?? true)}
                    onChange={(_, marcado) => marcar([linha.id], marcado)}
                    slotProps={{ input: { 'aria-label': selecao.rotuloLinha(linha) } }}
                  />
                )}
                <Box sx={{ minWidth: 0, fontWeight: 600 }}>
                  {linha.falha && (
                    <Box component="span" sx={{ color: 'error.main', mr: 1 }}>
                      {textoFalha}
                    </Box>
                  )}
                  {principal?.valor(linha)}
                </Box>
              </Box>
              {demais.length > 0 && (
                <Box
                  component="dl"
                  sx={{ m: 0, display: 'grid', gridTemplateColumns: 'minmax(0, max-content) minmax(0, 1fr)', columnGap: 2, rowGap: 0.75 }}
                >
                  {demais.map((coluna) => (
                    <Box key={coluna.chave} sx={{ display: 'contents' }}>
                      <Typography component="dt" variant="body2" color="text.secondary">
                        {coluna.rotulo}
                      </Typography>
                      <Typography component="dd" variant="body2" sx={{ m: 0, minWidth: 0 }}>
                        {coluna.valor(linha)}
                      </Typography>
                    </Box>
                  ))}
                </Box>
              )}
              <Button size="small" variant="outlined" onClick={() => aoAbrir(linha)} aria-label={textoAbrir ? rotuloAbrir(linha) : undefined} sx={{ justifySelf: 'start' }}>
                {textoAbrir ?? rotuloAbrir(linha)}
              </Button>
            </Box>
          ))}
        </Box>
      )}
      <Box sx={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 1.5, justifyContent: 'space-between' }}>
        <TextField
          id={idTamanho}
          select
          size="small"
          label={textos.linhasPorPagina}
          value={porPagina}
          onChange={(evento) => paginacao.aoMudar(1, Number(evento.target.value))}
          sx={{ minWidth: 140 }}
        >
          {TAMANHOS_DE_PAGINA.map((tamanho) => (
            <MenuItem key={tamanho} value={tamanho}>
              {tamanho}
            </MenuItem>
          ))}
        </TextField>
        <Typography variant="body2" aria-live="polite">
          {textos.exibindo(de, ate, total)}
        </Typography>
        <Box sx={{ display: 'flex', gap: 1 }}>
          <Button size="small" disabled={pagina <= 1} onClick={() => paginacao.aoMudar(pagina - 1, porPagina)}>
            {textos.paginaAnterior}
          </Button>
          <Button size="small" disabled={ate >= total} onClick={() => paginacao.aoMudar(pagina + 1, porPagina)}>
            {textos.proximaPagina}
          </Button>
        </Box>
      </Box>
    </Box>
  )
}
