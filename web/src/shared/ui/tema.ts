import { createTheme } from '@mui/material/styles'
import { tokens } from './tokens'

// Tema Oikora do painel (OIK-ADR-0006 §6–7), a partir do protótipo aprovado em
// docs/prototipos/login-painel.html: café, mel, off-white e Poppins.

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

export const temaOikora = createTheme({
  cssVariables: { colorSchemeSelector: 'data-tema' },
  colorSchemes: {
    light: {
      palette: {
        primary: { main: '#5D4432', dark: '#4A3527', contrastText: '#FFFFFF' },
        secondary: { main: MEL, contrastText: '#24170D' },
        ...tokens.status.light,
        error: { main: '#B42318', light: '#FDECEA', contrastText: '#FFFFFF' },
        background: { default: '#F9F7F5', paper: '#FFFFFF' },
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
        primary: { main: MEL, dark: '#F2B85F', contrastText: '#24170D' },
        secondary: { main: MEL, contrastText: '#24170D' },
        ...tokens.status.dark,
        error: { main: '#FF9C90', light: '#3B1D1B', contrastText: '#3B1D1B' },
        background: { default: '#17110D', paper: '#211913' },
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
    button: { fontWeight: 600, textTransform: 'none', fontSize: '1rem' },
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
      }),
    },
    MuiButton: {
      defaultProps: { disableElevation: true },
      styleOverrides: { root: { borderRadius: 999, minHeight: 52 } },
    },
    MuiOutlinedInput: {
      styleOverrides: {
        root: ({ theme }) => ({
          minHeight: 52,
          backgroundColor: theme.vars.palette.background.paper,
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
      styleOverrides: { root: { fontWeight: 500, fontSize: '0.9375rem', textUnderlineOffset: 4 } },
    },
    MuiAlert: {
      styleOverrides: { root: { borderRadius: 10, fontSize: '0.9375rem' } },
    },
  },
})
