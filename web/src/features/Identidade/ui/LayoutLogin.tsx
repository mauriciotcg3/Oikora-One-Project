import Box from '@mui/material/Box'
import Typography from '@mui/material/Typography'
import { keyframes } from '@mui/material/styles'
import type { ReactNode } from 'react'
import { Trans, useTranslation } from 'react-i18next'

// À esquerda, o campo café com a porta acesa (oikos = casa e família); à direita, o formulário.
// Abaixo de 900px o campo vira uma faixa curta acima do formulário (protótipo aprovado).

const MEL = '#E8A33D'

const respirar = keyframes`
  0%, 100% { opacity: .55; transform: scale(1); }
  50% { opacity: .85; transform: scale(1.05); }
`

function Marca(): ReactNode {
  const { t } = useTranslation('identidade')
  return (
    <Box translate="no" sx={{ display: 'flex', alignItems: 'center', gap: 1.5, fontWeight: 600, fontSize: '1.125rem' }}>
      <svg viewBox="0 0 36 36" width="36" height="36" aria-hidden="true">
        <circle cx="18" cy="18" r="18" fill="#F8EFE6" opacity=".12" />
        <path d="M10 29V17a8 8 0 0 1 16 0v12Z" fill={MEL} />
        <path d="M10 29V16.5a8 8 0 0 1 3.4-6.5V27Z" fill="#6E5038" />
      </svg>
      {t('marca')}
    </Box>
  )
}

function Porta(): ReactNode {
  const { t } = useTranslation('identidade')
  return (
    <Box
      component="svg"
      viewBox="0 0 400 360"
      role="img"
      aria-label={t('boasVindas.ilustracao')}
      sx={{ width: { xs: 84, md: 'min(100%, 420px)' }, height: 'auto', gridRow: { xs: 'span 2', md: 'auto' } }}
    >
      <Box
        component="circle"
        cx="200"
        cy="190"
        r="160"
        fill={MEL}
        opacity=".14"
        sx={{
          transformOrigin: '200px 300px',
          animation: `${respirar} 6s ease-in-out infinite`,
          '@media (prefers-reduced-motion: reduce)': { animation: 'none' },
        }}
      />
      <circle cx="200" cy="190" r="112" fill={MEL} opacity=".16" />
      <path d="M130 352 270 352 340 300 60 300Z" transform="matrix(1 0 0 -1 0 652)" fill={MEL} opacity=".28" />
      <path d="M40 300H360" stroke="var(--mui-palette-campo-suave)" strokeWidth="3" strokeLinecap="round" opacity=".5" />
      <path d="M130 300V160a70 70 0 0 1 140 0v140Z" fill={MEL} />
      <path d="M130 300V158c0-30 15-52 40-62v190Z" fill="var(--mui-palette-campo-folha)" />
      <circle cx="160" cy="214" r="4.5" fill={MEL} />
    </Box>
  )
}

function BoasVindas(): ReactNode {
  const { t } = useTranslation('identidade')
  return (
    <Box component="aside" aria-label={t('boasVindas.rotulo')} sx={{ bgcolor: 'campo.main', color: 'campo.contrastText', display: 'flex' }}>
      <Box
        sx={{
          width: '100%',
          display: 'flex',
          flexDirection: 'column',
          justifyContent: 'space-between',
          gap: { xs: 2, md: 5 },
          pt: { xs: 2.5, md: 5 },
          pb: { xs: 3, md: 5 },
          px: { xs: 2, md: 6 },
        }}
      >
        <Marca />
        <Box
          sx={{
            display: 'grid',
            gap: { xs: 0.5, md: 3 },
            columnGap: { xs: 2 },
            gridTemplateColumns: { xs: '84px minmax(0, 1fr)', md: 'minmax(0, 1fr)' },
            alignItems: { xs: 'center', md: 'start' },
            justifyItems: 'start',
          }}
        >
          <Porta />
          <Typography
            component="p"
            sx={{
              m: 0,
              fontWeight: 600,
              fontSize: { xs: '1.375rem', md: '2.25rem' },
              lineHeight: 1.12,
              letterSpacing: '-0.02em',
              textWrap: 'balance',
              maxWidth: { md: '15ch' },
            }}
          >
            {t('boasVindas.frase')}
          </Typography>
          <Typography sx={{ m: 0, color: 'campo.suave', fontSize: { xs: '0.9375rem', md: '1.0625rem' }, maxWidth: '36ch' }}>
            {t('boasVindas.texto')}
          </Typography>
        </Box>
        <Typography sx={{ display: { xs: 'none', md: 'block' }, m: 0, color: 'campo.suave', fontSize: '0.875rem' }}>
          <Trans t={t} i18nKey="boasVindas.rodape" components={[<span key="oikos" translate="no" />]} />
        </Typography>
      </Box>
    </Box>
  )
}

export function LayoutLogin({ children }: { children: ReactNode }): ReactNode {
  return (
    <Box
      sx={{
        minHeight: '100dvh',
        display: 'grid',
        gridTemplateColumns: { xs: 'minmax(0, 1fr)', md: 'repeat(2, minmax(0, 1fr))' },
        bgcolor: 'background.default',
      }}
    >
      <BoasVindas />
      <Box component="main" sx={{ display: 'flex', alignItems: { xs: 'flex-start', md: 'center' }, justifyContent: 'center' }}>
        <Box sx={{ width: '100%', maxWidth: 456, display: 'grid', gap: 3, pt: { xs: 4, md: 6 }, pb: { xs: 5, md: 6 }, px: { xs: 2, md: 3 } }}>
          {children}
        </Box>
      </Box>
    </Box>
  )
}
