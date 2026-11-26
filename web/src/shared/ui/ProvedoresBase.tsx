import CssBaseline from '@mui/material/CssBaseline'
import { ThemeProvider } from '@mui/material/styles'
import { QueryClientProvider, type QueryClient } from '@tanstack/react-query'
import { createContext, useContext, useMemo, useState, type ReactNode } from 'react'
import { I18nextProvider } from 'react-i18next'
import type { InstanciaI18n } from '../i18n'
import { criarTemaOikora } from './tema'

/** Chave do `localStorage` onde a MUI guarda a escolha claro/escuro/sistema (só preferência de quem usa). */
export const CHAVE_MODO_DE_COR = 'oikora-tema'

const DefinirCorPrimaria = createContext<(cor: string | null) => void>(() => undefined)

/**
 * Aplica a cor primária da organização ao tema (já ajustada para contraste AA). O shell chama com a cor
 * de `GET /painel/organizacao`; `null` volta à cor Oikora (login, troca de organização).
 */
export function useDefinirCorPrimaria(): (cor: string | null) => void {
  return useContext(DefinirCorPrimaria)
}

interface Props {
  i18n: InstanciaI18n
  clienteConsulta: QueryClient
  children: ReactNode
}

/** Tema (claro, escuro ou do sistema), idioma e estado de servidor; usado pelas entradas em `app/` e pelos testes. */
export function ProvedoresBase({ i18n, clienteConsulta, children }: Props): ReactNode {
  const [corPrimaria, setCorPrimaria] = useState<string | null>(null)
  const tema = useMemo(() => criarTemaOikora(corPrimaria), [corPrimaria])
  return (
    <ThemeProvider theme={tema} defaultMode="system" modeStorageKey={CHAVE_MODO_DE_COR}>
      <CssBaseline enableColorScheme />
      <DefinirCorPrimaria.Provider value={setCorPrimaria}>
        <I18nextProvider i18n={i18n}>
          <QueryClientProvider client={clienteConsulta}>{children}</QueryClientProvider>
        </I18nextProvider>
      </DefinirCorPrimaria.Provider>
    </ThemeProvider>
  )
}
