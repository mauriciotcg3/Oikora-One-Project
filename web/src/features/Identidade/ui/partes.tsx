import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Link from '@mui/material/Link'
import Typography from '@mui/material/Typography'
import { keyframes } from '@mui/material/styles'
import { useEffect, useRef, type ReactNode } from 'react'
import { IconeAlerta, IconeVoltar } from './icones'

// Peças comuns às telas do login: cabeçalho, botão voltar, aviso de erro e o bloco da tela.

const subir = keyframes`
  from { opacity: .4; transform: translateY(8px); }
  to { opacity: 1; transform: none; }
`

interface PropsTela {
  rotuloId: string
  children: ReactNode
}

export function Tela({ rotuloId, children }: PropsTela): ReactNode {
  return (
    <Box
      component="section"
      aria-labelledby={rotuloId}
      sx={{
        display: 'grid',
        gap: 3,
        animation: `${subir} .3s ease-out`,
        '@media (prefers-reduced-motion: reduce)': { animation: 'none' },
      }}
    >
      {children}
    </Box>
  )
}

interface PropsCabecalho {
  id: string
  titulo: string
  children?: ReactNode
  /** Leva o foco ao título (telas sem campo para focar). */
  focar?: boolean
}

export function Cabecalho({ id, titulo, children, focar = false }: PropsCabecalho): ReactNode {
  const ref = useRef<HTMLHeadingElement>(null)
  useEffect(() => {
    if (focar) ref.current?.focus()
  }, [focar])
  return (
    <Box component="header" sx={{ display: 'grid', gap: 1 }}>
      <Typography
        ref={ref}
        id={id}
        variant="h1"
        tabIndex={focar ? -1 : undefined}
        sx={{ fontSize: { xs: '1.625rem', md: '2rem' }, textWrap: 'balance', outline: 'none' }}
      >
        {titulo}
      </Typography>
      {children ? (
        <Typography color="text.secondary" sx={{ '& strong': { color: 'text.primary', fontWeight: 600 } }}>
          {children}
        </Typography>
      ) : null}
    </Box>
  )
}

export function Voltar({ rotulo, aoClicar }: { rotulo: string; aoClicar: () => void }): ReactNode {
  return (
    <Link
      component="button"
      type="button"
      onClick={aoClicar}
      sx={{ justifySelf: 'start', display: 'inline-flex', alignItems: 'center', gap: 0.5, ml: -0.5, py: 0.5 }}
    >
      <IconeVoltar />
      {rotulo}
    </Link>
  )
}

export function AvisoErro({ children }: { children: ReactNode }): ReactNode {
  return (
    <Alert
      severity="error"
      icon={<IconeAlerta />}
      sx={{ bgcolor: 'error.light', color: 'text.primary', '& .MuiAlert-icon': { color: 'error.main' } }}
    >
      {children}
    </Alert>
  )
}

export function Dica({ id, erro, children }: { id: string; erro: boolean; children: ReactNode }): ReactNode {
  return (
    <Typography
      id={id}
      aria-live="polite"
      sx={{ fontSize: '0.875rem', minHeight: '1.4em', color: erro ? 'error.main' : 'text.secondary', fontWeight: erro ? 500 : 400 }}
    >
      {children}
    </Typography>
  )
}
