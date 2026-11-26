import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import SvgIcon from '@mui/material/SvgIcon'
import { DataGrid, type GridColDef } from '@mui/x-data-grid'
import { useMemo, type ReactNode } from 'react'
import { TAMANHOS_DE_PAGINA } from '../FiltrosNaUrl'
import type { TabelaDeRegistrosProps } from './TabelaDeRegistros'

// Único arquivo que importa o DataGrid (MUI X, MIT): `TabelaDeRegistros` o carrega por `lazy`,
// então ele vira um chunk à parte e fica fora da entrada `painel-*.js` (OIK-ADR-0006 §6).

function IconeFalha(): ReactNode {
  return (
    <SvgIcon fontSize="small" color="error" aria-hidden="true">
      <path d="M12 2L1 21h22L12 2zm1 15h-2v-2h2v2zm0-4h-2V9h2v4z" />
    </SvgIcon>
  )
}

export default function GradeDeRegistros<L extends { id: string; falha?: boolean }>({
  rotulo,
  colunas,
  linhas,
  textoFalha,
  rotuloAbrir,
  aoAbrir,
  paginacao,
  textos,
}: Omit<TabelaDeRegistrosProps<L>, 'textoCarregando'>): ReactNode {
  const definicao = useMemo<GridColDef<L>[]>(
    () => [
      ...colunas.map((coluna, posicao): GridColDef<L> => ({
        field: coluna.chave,
        headerName: coluna.rotulo,
        flex: 1,
        minWidth: 160,
        sortable: false,
        renderCell: ({ row }) => (
          <>
            {posicao === 0 && row.falha && (
              <Box component="span" sx={{ display: 'inline-flex', alignItems: 'center', gap: 0.5, mr: 1, fontWeight: 600, color: 'error.main' }}>
                <IconeFalha />
                {textoFalha}
              </Box>
            )}
            {coluna.valor(row)}
          </>
        ),
      })),
      {
        field: '__abrir',
        headerName: '',
        sortable: false,
        disableColumnMenu: true,
        width: 240,
        align: 'right',
        renderCell: ({ row }) => (
          <Button size="small" variant="outlined" onClick={() => aoAbrir(row)}>
            {rotuloAbrir(row)}
          </Button>
        ),
      },
    ],
    [colunas, textoFalha, rotuloAbrir, aoAbrir],
  )

  return (
    <DataGrid
      aria-label={rotulo}
      rows={linhas as L[]}
      columns={definicao}
      autoHeight
      // Páginas de até 100 linhas e a página inteira no DOM: leitores de tela leem tudo.
      disableVirtualization
      // Paginação no servidor: o DataGrid nunca recebe nem fatia a lista completa.
      paginationMode="server"
      rowCount={paginacao.total}
      loading={paginacao.carregando}
      pageSizeOptions={[...TAMANHOS_DE_PAGINA]}
      paginationModel={{ page: paginacao.pagina - 1, pageSize: paginacao.porPagina }}
      onPaginationModelChange={({ page, pageSize }) => paginacao.aoMudar(page + 1, pageSize)}
      localeText={{
        noRowsLabel: textos.semLinhas,
        paginationRowsPerPage: textos.linhasPorPagina,
        paginationDisplayedRows: ({ from, to, count }) => textos.exibindo(from, to, count ?? 0),
        paginationItemAriaLabel: (tipo) => (tipo === 'previous' ? textos.paginaAnterior : tipo === 'next' ? textos.proximaPagina : tipo),
      }}
      disableRowSelectionOnClick
      disableColumnMenu
      getRowClassName={({ row }) => (row.falha ? 'linha-falha' : '')}
      sx={{ '& .linha-falha': { bgcolor: 'error.light' } }}
    />
  )
}
