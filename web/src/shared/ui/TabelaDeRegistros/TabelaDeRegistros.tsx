import { useTheme } from '@mui/material/styles'
import useMediaQuery from '@mui/material/useMediaQuery'
import { lazy, Suspense, type ReactNode } from 'react'
import { EstadoCarregando } from '../EstadoCarregando'
import { ListaDeRegistros } from './ListaDeRegistros'

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

/**
 * Seleção opcional de linhas (ex.: exportar as escolhidas). Guarda ids de qualquer página: trocar de
 * página não perde o que já foi marcado. Estado efêmero da tela, mantido por quem usa a tabela.
 */
export interface SelecaoDaTabela<L> {
  selecionadas: ReadonlySet<string>
  aoMudar: (selecionadas: ReadonlySet<string>) => void
  /** Linha que a pessoa não pode marcar (ex.: sem a ação na API) aparece desabilitada. */
  podeSelecionar?: (linha: L) => boolean
  /** Nome acessível da caixa da linha, ex.: "Selecionar Ana Lima". */
  rotuloLinha: (linha: L) => string
  /** Nome acessível da caixa que marca a página inteira. */
  rotuloPagina: string
  /** Título curto da coluna de caixas (tabela larga), ex.: "Selecionar". */
  rotuloColuna: string
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
  /**
   * Texto visível e curto do botão (ex.: "Abrir ficha"); `rotuloAbrir` vira o nome acessível, que o contém
   * (WCAG 2.5.3). Sem ele, o botão mostra `rotuloAbrir`.
   */
  textoAbrir?: string
  aoAbrir: (linha: L) => void
  paginacao: PaginacaoDaTabela
  textos: TextosDaTabela
  selecao?: SelecaoDaTabela<L>
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
 * da cor, e cada linha abre o detalhe por um botão alcançável por teclado. Abaixo de 900 px CSS (celular
 * ou zoom de 200% a 400%) vira uma lista de cartões, sem rolagem horizontal (WCAG 1.4.10). Não busca
 * dados (FE-FLOW-001) e nunca pagina no navegador: recebe só a página atual e o total (OIK-ADR-0006 §6).
 */
export function TabelaDeRegistros<L extends { id: string; falha?: boolean }>({ textoCarregando, ...props }: TabelaDeRegistrosProps<L>): ReactNode {
  const tema = useTheme()
  // `noSsr`: lê a largura já na primeira renderização, sem baixar o DataGrid à toa no celular.
  const estreita = useMediaQuery(tema.breakpoints.down('md'), { noSsr: true })
  if (estreita) return <ListaDeRegistros {...props} />
  return (
    <Suspense fallback={<EstadoCarregando texto={textoCarregando} />}>
      <GradeDeRegistros {...props} />
    </Suspense>
  )
}
