// Tokens do Oikora UI Standard (OIK-ADR-0014, OIK-SPEC-0001 T-49). Valores só aqui: o tema e os
// componentes os consomem; nenhum componente de feature repete cor ou medida.
// Contraste conferido por `__tests__/tema.test.tsx` (WCAG 2.2 AA: texto 4,5:1; componente 3:1).

export const tokens = {
  /** Base da escala de espaçamento: `theme.spacing(n)` = n × 8 px. */
  espacamentoBase: 8,
  raio: 10,
  /** Anel de foco visível (WCAG 2.4.7). */
  foco: { largura: 3, afastamento: 2 },
  /** Borda de controle de formulário: ≥ 3:1 sobre o papel (WCAG 1.4.11). */
  bordaControle: { light: '#8A7869', dark: '#9C8A7B' },
  /** Cores de status; `main` serve de texto sobre o papel e de fundo para `contrastText`. */
  status: {
    light: {
      success: { main: '#1B6E3A', contrastText: '#FFFFFF' },
      warning: { main: '#8A5300', contrastText: '#FFFFFF' },
      info: { main: '#0B5FA5', contrastText: '#FFFFFF' },
    },
    dark: {
      success: { main: '#7FD99B', contrastText: '#10210F' },
      warning: { main: '#F5C15F', contrastText: '#24170D' },
      info: { main: '#8CC4FF', contrastText: '#0B1F33' },
    },
  },
} as const
