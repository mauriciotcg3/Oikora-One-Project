import { lazy, Suspense, type ReactNode } from 'react'
import { EstadoCarregando } from '../EstadoCarregando'

export interface ColunaDaTabela<L> {
  chave: string
  rotulo: string
  valor: (linha: L) => ReactNode
}

/** Paginação no servidor: a tabela só mostra a página recebida e avisa quando a pessoa troca. */
export interface PaginacaoDaTabela {
  /** Página atual, a partir de 1. */
  pagina: number
  porPagina: number
  /** Total de registros na API (`meta.total`), para calcular as páginas sem baixá-los. */
  total: number
  aoMudar: (pagina: number, porPagina: number) => void
  /** Há um pedido da página em andamento. */
  carregando?: boolean
}

/** Textos do rodapé e do estado vazio, já traduzidos (o DataGrid traz inglês por padrão). */
export interface TextosDaTabela {
  linhasPorPagina: string
  exibindo: (de: number, ate: number, total: number) => string
  paginaAnterior: string
  proximaPagina: string
  semLinhas: string
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
  paginacao: PaginacaoDaTabela
  textos: TextosDaTabela
  /** Texto mostrado enquanto o DataGrid carrega. */
  textoCarregando: string
}

// O DataGrid pesa; sai da entrada do painel e só baixa quando uma tela com tabela abre.
// `lazy` apaga o genérico da linha; o cast o devolve (o módulo exporta exatamente esse tipo).
const GradeDeRegistros = lazy(() => import('./GradeDeRegistros')) as unknown as <L extends { id: string; falha?: boolean }>(
  props: Omit<TabelaDeRegistrosProps<L>, 'textoCarregando'>,
) => ReactNode

/**
 * Tabela de registros (OIK-ADR-0014) sobre o DataGrid MIT: a linha de falha traz texto e ícone além
 * da cor, e cada linha abre o detalhe por um botão alcançável por teclado. Não busca dados (FE-FLOW-001) e
 * nunca pagina no navegador: recebe só a página atual e o total (OIK-ADR-0006 §6).
 */
export function TabelaDeRegistros<L extends { id: string; falha?: boolean }>({ textoCarregando, ...props }: TabelaDeRegistrosProps<L>): ReactNode {
  return (
    <Suspense fallback={<EstadoCarregando texto={textoCarregando} />}>
      <GradeDeRegistros {...props} />
    </Suspense>
  )
}
