import Box from '@mui/material/Box'
import Paper from '@mui/material/Paper'
import Typography from '@mui/material/Typography'
import { Navigate } from '@tanstack/react-router'
import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { SeletorOrganizacao, useMe } from '../../features/Identidade'
import { LinkTexto, PaginaDoPainel } from '../../shared/ui'
import { useCapacidades } from './capacidades'
import { itensVisiveis } from './Navegacao'

/**
 * Página inicial do painel: seletor de organização, atalhos para as áreas que a pessoa pode usar e o
 * desvio para o cadastro do 2FA quando o papel exige e a conta ainda não tem (RN-16).
 */
export function InicioPainel(): ReactNode {
  const { t } = useTranslation('painel')
  const me = useMe()
  const { tem } = useCapacidades()

  if (me.data?.dois_fatores.obrigatorio && me.data.dois_fatores.canal === null) {
    return <Navigate to="/dois-fatores" />
  }

  const areas = itensVisiveis(tem).filter((item) => item.chave !== 'inicio')

  return (
    <PaginaDoPainel titulo={t('inicio.titulo')} descricao={t('inicio.texto')} acoes={<SeletorOrganizacao />}>
      <Box component="nav" aria-label={t('inicio.atalhos')} sx={{ display: 'grid', gap: 1.5, gridTemplateColumns: 'repeat(auto-fill, minmax(min(100%, 15rem), 1fr))' }}>
        {areas.map((item) => (
          <Paper key={item.chave} variant="outlined" sx={{ p: 2.5, borderRadius: 4, display: 'grid', gap: 0.5 }}>
            <LinkTexto to={item.para} sx={{ fontWeight: 600, fontSize: '1.0625rem' }}>
              {t(`shell.itens.${item.chave}`)}
            </LinkTexto>
            <Typography variant="body2" color="text.secondary">
              {t(`inicio.descricoes.${item.chave}`)}
            </Typography>
          </Paper>
        ))}
        <Paper variant="outlined" sx={{ p: 2.5, borderRadius: 4, display: 'grid', gap: 0.5 }}>
          <LinkTexto to="/acessos" sx={{ fontWeight: 600, fontSize: '1.0625rem' }}>
            {t('inicio.acessos')}
          </LinkTexto>
          <Typography variant="body2" color="text.secondary">
            {t('inicio.descricoes.acessos')}
          </Typography>
        </Paper>
        <Paper variant="outlined" sx={{ p: 2.5, borderRadius: 4, display: 'grid', gap: 0.5 }}>
          <LinkTexto to="/papeis" sx={{ fontWeight: 600, fontSize: '1.0625rem' }}>
            {t('inicio.papeis')}
          </LinkTexto>
          <Typography variant="body2" color="text.secondary">
            {t('inicio.descricoes.papeis')}
          </Typography>
        </Paper>
      </Box>
    </PaginaDoPainel>
  )
}
