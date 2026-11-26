import SvgIcon from '@mui/material/SvgIcon'
import type { ReactNode } from 'react'

// Ícones de traço do shell, no mesmo desenho dos ícones do login (sem pacote de ícones).

function Traco({ children }: { children: ReactNode }): ReactNode {
  return (
    <SvgIcon
      fontSize="small"
      aria-hidden
      sx={{ fill: 'none', stroke: 'currentColor', strokeWidth: 1.7, strokeLinecap: 'round', strokeLinejoin: 'round' }}
    >
      {children}
    </SvgIcon>
  )
}

export const IconeMenu = (): ReactNode => (
  <Traco>
    <path d="M4 7h16M4 12h16M4 17h16" />
  </Traco>
)

export const IconeSol = (): ReactNode => (
  <Traco>
    <circle cx="12" cy="12" r="4" />
    <path d="M12 2.5v2M12 19.5v2M4.6 4.6l1.4 1.4M18 18l1.4 1.4M2.5 12h2M19.5 12h2M4.6 19.4 6 18M18 6l1.4-1.4" />
  </Traco>
)

export const IconeLua = (): ReactNode => (
  <Traco>
    <path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5Z" />
  </Traco>
)

export const IconeSistema = (): ReactNode => (
  <Traco>
    <rect x="3" y="4.5" width="18" height="12" rx="2" />
    <path d="M8.5 20h7M12 16.5V20" />
  </Traco>
)

export const IconeInicio = (): ReactNode => (
  <Traco>
    <path d="M4 11 12 4l8 7v8.5a1 1 0 0 1-1 1h-4.5v-6h-5v6H5a1 1 0 0 1-1-1Z" />
  </Traco>
)

export const IconePessoas = (): ReactNode => (
  <Traco>
    <circle cx="9" cy="8" r="3.5" />
    <path d="M2.5 20c.6-3.6 3.2-5.5 6.5-5.5s5.9 1.9 6.5 5.5" />
    <path d="M16 4.8a3.3 3.3 0 0 1 0 6.4M18 14.8c2 .7 3.2 2.4 3.5 5.2" />
  </Traco>
)

export const IconeCalendario = (): ReactNode => (
  <Traco>
    <rect x="3.5" y="5" width="17" height="15.5" rx="2.5" />
    <path d="M3.5 10h17M8 3v4M16 3v4" />
  </Traco>
)

export const IconeOperacoes = (): ReactNode => (
  <Traco>
    <path d="M4 7h12M13 4l3 3-3 3M20 17H8M11 14l-3 3 3 3" />
  </Traco>
)

export const IconeSino = (): ReactNode => (
  <Traco>
    <path d="M6 16.5V11a6 6 0 0 1 12 0v5.5l1.5 2h-15Z" />
    <path d="M10 20.5a2 2 0 0 0 4 0" />
  </Traco>
)

export const IconeMensagem = (): ReactNode => (
  <Traco>
    <path d="M4 18.5 5.3 15A7.5 7.5 0 1 1 8.8 18.4Z" />
  </Traco>
)

export const IconeAjustes = (): ReactNode => (
  <Traco>
    <path d="M4 7h9M17 7h3M4 17h3M11 17h9" />
    <circle cx="15" cy="7" r="2" />
    <circle cx="9" cy="17" r="2" />
  </Traco>
)

export const IconeEscudo = (): ReactNode => (
  <Traco>
    <path d="M12 3 19.5 6v5.5c0 4.5-3.2 8-7.5 9.5-4.3-1.5-7.5-5-7.5-9.5V6Z" />
    <path d="m9 12 2.2 2.2L15.5 10" />
  </Traco>
)

export const IconeRelogio = (): ReactNode => (
  <Traco>
    <circle cx="12" cy="12" r="8.5" />
    <path d="M12 7.5V12l3 2" />
  </Traco>
)
