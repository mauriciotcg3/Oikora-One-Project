import { createTheme, darken, getContrastRatio, lighten, type Theme } from '@mui/material/styles'
import { tokens } from './tokens'

// Tema Oikora do painel (OIK-ADR-0006 §6–7), a partir do protótipo aprovado em
// docs/prototipos/login-painel.html: café, mel, off-white e Poppins. Claro e escuro seguem o
// sistema por padrão (`ProvedoresBase`); a cor primária da organização entra por `criarTemaOikora`.

declare module '@mui/material/styles' {
  interface Palette {
    campo: { main: string; contrastText: string; suave: string; folha: string }
    mel: string
    afundado: string
    bordaControle: string
  }
  interface PaletteOptions {
    campo?: { main: string; contrastText: string; suave: string; folha: string }
    mel?: string
    afundado?: string
    bordaControle?: string
  }
}

const MEL = '#E8A33D'
const FONTE = '"Poppins", "Segoe UI", system-ui, -apple-system, sans-serif'
const PAPEL = { light: '#FFFFFF', dark: '#211913' } as const
const FUNDO = { light: '#F9F7F5', dark: '#17110D' } as const
const TINTA_CLARA = '#FFFFFF'
const TINTA_ESCURA = '#24170D'
const TEXTO_AA = 4.5
const COR_HEX = /^#[0-9A-Fa-f]{6}$/

export type EsquemaDeCor = 'light' | 'dark'

/**
 * Ajusta a cor da organização ao esquema até ela servir de texto sobre o papel e o fundo (WCAG 1.4.3,
 * 4,5:1): no claro escurece, no escuro clareia. Cor inválida volta `null` e o tema usa o padrão Oikora.
 */
export function corLegivel(cor: string | null | undefined, esquema: EsquemaDeCor): string | null {
  if (!cor || !COR_HEX.test(cor)) return null
  const ajustar = esquema === 'light' ? darken : lighten
  const legivel = (candidata: string): boolean =>
    getContrastRatio(candidata, PAPEL[esquema]) >= TEXTO_AA && getContrastRatio(candidata, FUNDO[esquema]) >= TEXTO_AA
  let candidata = cor
  for (let passo = 1; passo <= 20 && !legivel(candidata); passo += 1) candidata = ajustar(cor, passo * 0.05)
  return legivel(candidata) ? candidata : null
}

/** Tinta do botão sobre a cor: branco ou café-escuro, o que tiver mais contraste. */
function tintaSobre(cor: string): string {
  return getContrastRatio(TINTA_CLARA, cor) >= getContrastRatio(TINTA_ESCURA, cor) ? TINTA_CLARA : TINTA_ESCURA
}

function primaria(cor: string | null, esquema: EsquemaDeCor): { main: string; dark: string; contrastText: string } {
  const propria = corLegivel(cor, esquema)
  if (propria) {
    return { main: propria, dark: esquema === 'light' ? darken(propria, 0.15) : lighten(propria, 0.15), contrastText: tintaSobre(propria) }
  }
  return esquema === 'light'
    ? { main: '#5D4432', dark: '#4A3527', contrastText: TINTA_CLARA }
    : { main: MEL, dark: '#F2B85F', contrastText: TINTA_ESCURA }
}

/** Tema do painel; `corPrimaria` é a cor da organização (`GET /painel/organizacao`), ajustada para contraste AA. */
export function criarTemaOikora(corPrimaria: string | null = null): Theme {
  return createTheme({
    cssVariables: { colorSchemeSelector: 'data-tema' },
    colorSchemes: {
      light: {
        palette: {
          primary: primaria(corPrimaria, 'light'),
          secondary: { main: MEL, contrastText: TINTA_ESCURA },
          ...tokens.status.light,
          error: { main: '#B42318', light: '#FDECEA', contrastText: TINTA_CLARA },
          background: { default: FUNDO.light, paper: PAPEL.light },
          text: { primary: '#3E2B1E', secondary: '#6B5646' },
          divider: '#E3DAD2',
          campo: { main: '#3E2B1E', contrastText: '#F8EFE6', suave: '#D8C5B3', folha: '#6E5038' },
          mel: MEL,
          afundado: '#F1ECE7',
          bordaControle: tokens.bordaControle.light,
        },
      },
      dark: {
        palette: {
          primary: primaria(corPrimaria, 'dark'),
          secondary: { main: MEL, contrastText: TINTA_ESCURA },
          ...tokens.status.dark,
          error: { main: '#FF9C90', light: '#3B1D1B', contrastText: '#3B1D1B' },
          background: { default: FUNDO.dark, paper: PAPEL.dark },
          text: { primary: '#F5ECE4', secondary: '#C2B1A3' },
          divider: '#3A2D24',
          campo: { main: '#271B13', contrastText: '#F5ECE4', suave: '#CDB8A5', folha: '#5D4432' },
          mel: MEL,
          afundado: '#2A2019',
          bordaControle: tokens.bordaControle.dark,
        },
      },
    },
    spacing: tokens.espacamentoBase,
    shape: { borderRadius: tokens.raio },
    typography: {
      fontFamily: FONTE,
      h1: { fontWeight: 600, fontSize: '2rem', lineHeight: 1.15, letterSpacing: '-0.02em' },
      h2: { fontWeight: 600, fontSize: '1.25rem', lineHeight: 1.3, letterSpacing: '-0.01em' },
      h3: { fontWeight: 600, fontSize: '1.0625rem', lineHeight: 1.35 },
      button: { fontWeight: 600, textTransform: 'none', fontSize: '1rem' },
      overline: { fontWeight: 600, letterSpacing: '0.06em', lineHeight: 1.6 },
    },
    components: {
      MuiCssBaseline: {
        styleOverrides: (theme) => ({
          ':focus-visible': {
            outline: `${tokens.foco.largura}px solid ${theme.vars.palette.primary.main}`,
            outlineOffset: tokens.foco.afastamento,
          },
          // O campo mostra o próprio anel de foco (OutlinedInput); evita o anel duplo.
          'input:focus-visible, textarea:focus-visible': { outline: 'none' },
          // Palavras longas (e-mail, nome de lista) quebram em vez de empurrar a página (WCAG 1.4.10).
          body: { overflowWrap: 'anywhere' },
        }),
      },
      MuiButton: {
        defaultProps: { disableElevation: true },
        // Botão arredondado do login (protótipo aprovado) em todo o painel; `size="large"` mantém a altura do login.
        styleOverrides: {
          root: { borderRadius: 999, paddingInline: 18, minHeight: 40 },
          sizeSmall: { minHeight: 32, paddingInline: 12, fontSize: '0.875rem' },
          sizeLarge: { minHeight: 52 },
        },
      },
      MuiOutlinedInput: {
        styleOverrides: {
          root: ({ theme }) => ({
            minHeight: 52,
            backgroundColor: theme.vars.palette.background.paper,
            '&.MuiInputBase-sizeSmall': { minHeight: 40 },
            '&.Mui-focused': { boxShadow: `0 0 0 3px rgba(${theme.vars.palette.primary.mainChannel} / 0.22)` },
            '&.Mui-error.Mui-focused': { boxShadow: `0 0 0 3px rgba(${theme.vars.palette.error.mainChannel} / 0.22)` },
          }),
          notchedOutline: ({ theme }) => ({ borderColor: theme.vars.palette.bordaControle }),
        },
      },
      MuiFormLabel: {
        styleOverrides: { root: ({ theme }) => ({ fontWeight: 500, fontSize: '0.9375rem', color: theme.vars.palette.text.primary }) },
      },
      MuiFormHelperText: {
        styleOverrides: { root: { marginLeft: 0, fontSize: '0.875rem' } },
      },
      MuiLink: {
        defaultProps: { underline: 'hover' },
        styleOverrides: { root: { fontWeight: 500, textUnderlineOffset: 4 } },
      },
      MuiAlert: {
        styleOverrides: { root: { borderRadius: 10, fontSize: '0.9375rem' } },
      },
      // Superfícies: papel com borda suave, sem sombra nem o "clareamento" do Paper no escuro.
      MuiPaper: { styleOverrides: { root: { backgroundImage: 'none' } } },
      MuiCard: {
        defaultProps: { variant: 'outlined' },
        styleOverrides: { root: { borderRadius: 16 } },
      },
      MuiCardContent: {
        styleOverrides: { root: ({ theme }) => ({ padding: theme.spacing(2.5), '&:last-child': { paddingBottom: theme.spacing(2.5) } }) },
      },
      MuiTabs: {
        styleOverrides: {
          root: ({ theme }) => ({ minHeight: 44, borderBottom: `1px solid ${theme.vars.palette.divider}` }),
          indicator: { height: 3, borderRadius: 3 },
        },
      },
      MuiTab: {
        styleOverrides: { root: { textTransform: 'none', fontWeight: 600, fontSize: '0.9375rem', minHeight: 44, paddingInline: 12 } },
      },
      MuiDialog: {
        styleOverrides: { paper: { borderRadius: 16 } },
      },
      MuiDialogTitle: {
        styleOverrides: { root: { fontWeight: 600, fontSize: '1.25rem' } },
      },
      MuiChip: {
        styleOverrides: { root: { fontWeight: 500 } },
      },
      MuiLinearProgress: {
        styleOverrides: { root: { height: 8, borderRadius: 8 }, bar: { borderRadius: 8 } },
      },
    },
  })
}

export const temaOikora = criarTemaOikora()
