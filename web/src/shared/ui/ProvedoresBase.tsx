import CssBaseline from '@mui/material/CssBaseline'
import { ThemeProvider } from '@mui/material/styles'
import { QueryClientProvider, type QueryClient } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { I18nextProvider } from 'react-i18next'
import type { InstanciaI18n } from '../i18n'
import { temaOikora } from './tema'

interface Props {
  i18n: InstanciaI18n
  clienteConsulta: QueryClient
  children: ReactNode
}

/** Tema, idioma e estado de servidor; usado pelas entradas em `app/` e pelos testes. */
export function ProvedoresBase({ i18n, clienteConsulta, children }: Props): ReactNode {
  return (
    <ThemeProvider theme={temaOikora} defaultMode="system">
      <CssBaseline enableColorScheme />
      <I18nextProvider i18n={i18n}>
        <QueryClientProvider client={clienteConsulta}>{children}</QueryClientProvider>
      </I18nextProvider>
    </ThemeProvider>
  )
}
