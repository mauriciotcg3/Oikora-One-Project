import Box from '@mui/material/Box'
import Typography from '@mui/material/Typography'
import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'

/** Marcador da página inicial até as telas do painel existirem. */
export function InicioPainel(): ReactNode {
  const { t } = useTranslation('painel')
  return (
    <Box component="main" sx={{ minHeight: '100dvh', display: 'grid', placeContent: 'center', gap: 1, p: 3, textAlign: 'center' }}>
      <Typography variant="h1">{t('inicio.titulo')}</Typography>
      <Typography color="text.secondary">{t('inicio.texto')}</Typography>
    </Box>
  )
}
