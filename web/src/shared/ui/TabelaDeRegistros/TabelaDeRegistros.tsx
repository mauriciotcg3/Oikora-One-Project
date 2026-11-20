import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Paper from '@mui/material/Paper'
import SvgIcon from '@mui/material/SvgIcon'
import Table from '@mui/material/Table'
import TableBody from '@mui/material/TableBody'
import TableCell from '@mui/material/TableCell'
import TableContainer from '@mui/material/TableContainer'
import TableHead from '@mui/material/TableHead'
import TableRow from '@mui/material/TableRow'
import type { ReactNode } from 'react'

export interface ColunaDaTabela<L> {
  chave: string
  rotulo: string
  valor: (linha: L) => ReactNode
}

export interface TabelaDeRegistrosProps<L extends { id: string; falha?: boolean }> {
  /** Nome acessível da tabela, já traduzido. */
  rotulo: string
  colunas: readonly ColunaDaTabela<L>[]
  linhas: readonly L[]
  /** Texto que marca a linha de falha, ex.: "Falha" (a cor e o ícone não bastam, WCAG 1.4.1). */
  textoFalha: string
  /** Rótulo do botão que abre o detalhe da linha, ex.: "Ver detalhes de …". */
  rotuloAbrir: (linha: L) => string
  aoAbrir: (linha: L) => void
}

function IconeFalha(): ReactNode {
  return (
    <SvgIcon fontSize="small" color="error" aria-hidden="true">
      <path d="M12 2L1 21h22L12 2zm1 15h-2v-2h2v2zm0-4h-2V9h2v4z" />
    </SvgIcon>
  )
}

/**
 * Tabela de registros (OIK-ADR-0014): a linha de falha traz texto e ícone além da cor, e cada linha
 * abre o detalhe por um botão alcançável por teclado. Não busca dados (FE-FLOW-001).
 */
export function TabelaDeRegistros<L extends { id: string; falha?: boolean }>({
  rotulo,
  colunas,
  linhas,
  textoFalha,
  rotuloAbrir,
  aoAbrir,
}: TabelaDeRegistrosProps<L>): ReactNode {
  return (
    <TableContainer component={Paper} variant="outlined">
      <Table aria-label={rotulo}>
        <TableHead>
          <TableRow>
            {colunas.map((coluna) => (
              <TableCell key={coluna.chave} scope="col" sx={{ fontWeight: 600 }}>
                {coluna.rotulo}
              </TableCell>
            ))}
            <TableCell />
          </TableRow>
        </TableHead>
        <TableBody>
          {linhas.map((linha) => (
            <TableRow key={linha.id} sx={linha.falha ? { bgcolor: 'error.light' } : undefined}>
              {colunas.map((coluna, posicao) => (
                <TableCell key={coluna.chave}>
                  {posicao === 0 && linha.falha && (
                    <Box component="span" sx={{ display: 'inline-flex', alignItems: 'center', gap: 0.5, mr: 1, fontWeight: 600, color: 'error.main' }}>
                      <IconeFalha />
                      {textoFalha}
                    </Box>
                  )}
                  {coluna.valor(linha)}
                </TableCell>
              ))}
              <TableCell align="right">
                <Button size="small" variant="outlined" onClick={() => aoAbrir(linha)}>
                  {rotuloAbrir(linha)}
                </Button>
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </TableContainer>
  )
}
