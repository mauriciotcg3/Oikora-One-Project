import Box from '@mui/material/Box'
import Link from '@mui/material/Link'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import { Link as LinkRota, Navigate } from '@tanstack/react-router'
import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { SeletorOrganizacao, useMe } from '../../features/Identidade'

/**
 * Página inicial do painel até o shell do UI Standard (T-51): seletor de organização, atalhos das telas
 * de gestão e o desvio para o cadastro do 2FA quando o papel exige e a conta ainda não tem (RN-16).
 */
export function InicioPainel(): ReactNode {
  const { t } = useTranslation('painel')
  const me = useMe()

  if (me.data?.dois_fatores.obrigatorio && me.data.dois_fatores.canal === null) {
    return <Navigate to="/dois-fatores" />
  }

  return (
    <Box component="main" sx={{ minHeight: '100dvh', display: 'grid', placeContent: 'center', gap: 2, p: 3, textAlign: 'center' }}>
      <Typography variant="h1">{t('inicio.titulo')}</Typography>
      <Typography color="text.secondary">{t('inicio.texto')}</Typography>
      <Box><SeletorOrganizacao /></Box>
      <Stack component="nav" direction="row" spacing={2} justifyContent="center" aria-label={t('inicio.atalhos')}>
        <Link component={LinkRota} to="/acessos">{t('inicio.acessos')}</Link>
        <Link component={LinkRota} to="/papeis">{t('inicio.papeis')}</Link>
        <Link component={LinkRota} to="/auditoria">{t('inicio.auditoria')}</Link>
      </Stack>
    </Box>
  )
}
