import SvgIcon from '@mui/material/SvgIcon'
import type { ReactNode } from 'react'

// Ícones de traço do protótipo aprovado, sem dependência de pacote de ícones.

interface PropsTraco {
  children: ReactNode
  espessura?: number
}

function IconeTraco({ children, espessura = 1.6 }: PropsTraco): ReactNode {
  return (
    <SvgIcon
      fontSize="small"
      aria-hidden
      sx={{ fill: 'none', stroke: 'currentColor', strokeWidth: espessura, strokeLinecap: 'round', strokeLinejoin: 'round' }}
    >
      {children}
    </SvgIcon>
  )
}

export const IconeEmail = (): ReactNode => (
  <IconeTraco>
    <rect x="3" y="5" width="18" height="14" rx="3" />
    <path d="m4 7 8 6 8-6" />
  </IconeTraco>
)

export const IconeCelular = (): ReactNode => (
  <IconeTraco>
    <rect x="6.5" y="2.5" width="11" height="19" rx="2.5" />
    <path d="M11 18.5h2" />
  </IconeTraco>
)

export const IconeCadeado = (): ReactNode => (
  <IconeTraco>
    <rect x="4.5" y="10.5" width="15" height="10" rx="2.5" />
    <path d="M8 10.5V8a4 4 0 0 1 8 0v2.5" />
  </IconeTraco>
)

export const IconeOlho = (): ReactNode => (
  <IconeTraco>
    <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" />
    <circle cx="12" cy="12" r="3" />
  </IconeTraco>
)

export const IconeOlhoFechado = (): ReactNode => (
  <IconeTraco>
    <path d="M3 3l18 18M10.6 6.1A9.6 9.6 0 0 1 12 6c6 0 9.5 6 9.5 6a17 17 0 0 1-3 3.6M6.5 7.6C4 9.3 2.5 12 2.5 12S6 18 12 18a9 9 0 0 0 4-.9" />
    <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
  </IconeTraco>
)

export const IconeChave = (): ReactNode => (
  <IconeTraco>
    <circle cx="8" cy="12" r="4" />
    <path d="M12 12h9M18 12v3M21 12v2" />
  </IconeTraco>
)

export const IconeAlerta = (): ReactNode => (
  <IconeTraco espessura={2}>
    <circle cx="12" cy="12" r="9" />
    <path d="M12 7.5v5.5M12 16.5v.01" />
  </IconeTraco>
)

export const IconeVoltar = (): ReactNode => (
  <IconeTraco espessura={2}>
    <path d="M15 6l-6 6 6 6" />
  </IconeTraco>
)

export const IconeConfirmado = (): ReactNode => (
  <IconeTraco espessura={2.2}>
    <path d="m5 12.5 4.5 4.5L19 7.5" />
  </IconeTraco>
)
