// OIK-SPEC-0001 T-49 (CA-40): fundação visual do Oikora UI Standard — contraste WCAG 2.2 AA
// dos tokens, paleta semântica nos dois esquemas e comportamento visível no tema.
import Alert from '@mui/material/Alert'
import TextField from '@mui/material/TextField'
import { getContrastRatio, type CssVarsTheme, type Palette } from '@mui/material/styles'
import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import { renderizarComProvedores } from '../../../test/renderizar'
import { corLegivel, criarTemaOikora, temaOikora } from '../tema'

const ESQUEMAS = ['light', 'dark'] as const
const SEMANTICAS = ['success', 'warning', 'info', 'error'] as const
/** `cssVariables` + `colorSchemes` expõe as paletas de cada esquema (tipo do createTheme não as declara). */
function paleta(esquema: (typeof ESQUEMAS)[number]): Palette {
  const sistema = (temaOikora as unknown as CssVarsTheme).colorSchemes[esquema]
  if (!sistema) throw new Error(`esquema ${esquema} ausente no tema`)
  return sistema.palette as Palette
}

const TEXTO_AA = 4.5
const COMPONENTE_AA = 3

describe('Tema Oikora — contraste (WCAG 2.2 AA)', () => {
  it.each(ESQUEMAS)('texto primário e secundário legíveis sobre fundo e papel (%s)', (esquema) => {
    const { text, background } = paleta(esquema)
    for (const fundo of [background.default, background.paper]) {
      expect(getContrastRatio(text.primary, fundo)).toBeGreaterThanOrEqual(TEXTO_AA)
      expect(getContrastRatio(text.secondary, fundo)).toBeGreaterThanOrEqual(TEXTO_AA)
    }
  })

  it.each(ESQUEMAS)('a borda do campo tem ao menos 3:1 sobre o papel (WCAG 1.4.11) (%s)', (esquema) => {
    const { bordaControle, background } = paleta(esquema)
    expect(getContrastRatio(bordaControle, background.paper)).toBeGreaterThanOrEqual(COMPONENTE_AA)
  })

  it.each(ESQUEMAS)('success, warning, info e error: texto sobre o fundo da cor e a cor como texto sobre o papel (%s)', (esquema) => {
    const palette = paleta(esquema)
    for (const semantica of SEMANTICAS) {
      const cor = palette[semantica]
      expect(getContrastRatio(cor.contrastText, cor.main), `${semantica} contrastText`).toBeGreaterThanOrEqual(TEXTO_AA)
      expect(getContrastRatio(cor.main, palette.background.paper), `${semantica} como texto`).toBeGreaterThanOrEqual(TEXTO_AA)
    }
  })
})

describe('Tema Oikora — cor da organização', () => {
  const paletaDe = (cor: string | null, esquema: (typeof ESQUEMAS)[number]): Palette => {
    const sistema = (criarTemaOikora(cor) as unknown as CssVarsTheme).colorSchemes[esquema]
    if (!sistema) throw new Error(`esquema ${esquema} ausente no tema`)
    return sistema.palette as Palette
  }

  it.each(['#F5D000', '#1565C0', '#0A0A0A', '#FFFFFF'])('a cor %s vira primária legível como texto e com tinta AA nos dois esquemas', (cor) => {
    for (const esquema of ESQUEMAS) {
      const { primary, background } = paletaDe(cor, esquema)
      expect(getContrastRatio(primary.main, background.paper), `${cor} ${esquema} sobre o papel`).toBeGreaterThanOrEqual(TEXTO_AA)
      expect(getContrastRatio(primary.main, background.default), `${cor} ${esquema} sobre o fundo`).toBeGreaterThanOrEqual(TEXTO_AA)
      expect(getContrastRatio(primary.contrastText, primary.main), `${cor} ${esquema} tinta do botão`).toBeGreaterThanOrEqual(TEXTO_AA)
    }
  })

  it('mantém a cor que já é legível e ignora valor fora do formato #RRGGBB', () => {
    expect(corLegivel('#1565C0', 'light')).toBe('#1565C0')
    expect(corLegivel('azul', 'light')).toBeNull()
    expect(paletaDe('azul', 'light').primary.main).toBe(paleta('light').primary.main)
  })
})

describe('Tema Oikora — comportamento visível', () => {
  it('cada severidade de Alert é anunciada ao leitor de tela com seu texto', () => {
    renderizarComProvedores(
      <>
        {SEMANTICAS.map((severidade) => (
          <Alert key={severidade} severity={severidade}>{`mensagem ${severidade}`}</Alert>
        ))}
      </>,
      {},
    )

    const alertas = screen.getAllByRole('alert')
    expect(alertas).toHaveLength(SEMANTICAS.length)
    for (const severidade of SEMANTICAS) expect(screen.getByText(`mensagem ${severidade}`)).toBeVisible()
  })

  it('o campo recebe foco por teclado e mantém o rótulo associado', async () => {
    const usuario = userEvent.setup()
    renderizarComProvedores(<TextField label="Nome da igreja" />, {})

    await usuario.tab()

    const campo = screen.getByRole('textbox', { name: 'Nome da igreja' })
    expect(campo).toHaveFocus()
  })
})
