import AppBar from '@mui/material/AppBar'
import Box from '@mui/material/Box'
import Drawer from '@mui/material/Drawer'
import IconButton from '@mui/material/IconButton'
import Toolbar from '@mui/material/Toolbar'
import Typography from '@mui/material/Typography'
import useMediaQuery from '@mui/material/useMediaQuery'
import { Outlet } from '@tanstack/react-router'
import { useRef, useState, type MouseEvent, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { AjudaContextual, EstadoCarregando, EstadoErro } from '../../shared/ui'
import { useCapacidades } from './capacidades'
import { IconeMenu } from './icones'
import { Navegacao } from './Navegacao'
import { SeletorTema } from './SeletorTema'
import { useTemaDaOrganizacao } from './temaDaOrganizacao'

const LARGURA_MENU = 264
// Menu fixo só a partir de 900 px CSS: com zoom de 200% ou mais ele vira gaveta e o conteúdo ganha a largura.
const TELA_LARGA = '(min-width:900px)'
const ID_CONTEUDO = 'conteudo'
const MEL = '#E8A33D'

/** A porta acesa do login, em miniatura: marca do Oikora no cabeçalho. */
function Marca(): ReactNode {
  return (
    <Box component="svg" viewBox="0 0 36 36" aria-hidden="true" sx={{ width: 32, height: 32, flexShrink: 0 }}>
      <circle cx="18" cy="18" r="18" fill="var(--mui-palette-campo-main)" />
      <path d="M10 29V17a8 8 0 0 1 16 0v12Z" fill={MEL} />
      <path d="M10 29V16.5a8 8 0 0 1 3.4-6.5V27Z" fill="var(--mui-palette-campo-folha)" />
    </Box>
  )
}

/** Shell do painel: cabeçalho, navegação lateral responsiva, tema, pular ao conteúdo e ajuda (OIK-ADR-0014). */
export function Shell(): ReactNode {
  const { t } = useTranslation('painel')
  const { consulta, organizacao, tem } = useCapacidades()
  const larga = useMediaQuery(TELA_LARGA, { noSsr: true })
  const [menuAberto, setMenuAberto] = useState(false)
  const conteudo = useRef<HTMLElement>(null)
  useTemaDaOrganizacao(organizacao?.id ?? null)

  const irAoConteudo = (evento: MouseEvent): void => {
    evento.preventDefault()
    conteudo.current?.focus()
  }

  const lateral = (
    <Box sx={{ display: 'flex', flexDirection: 'column', minHeight: '100%' }}>
      <Toolbar />
      <Box sx={{ flexGrow: 1, py: 1 }}>
        <Navegacao tem={tem} aoNavegar={() => setMenuAberto(false)} />
      </Box>
      <Box sx={{ p: 2, borderTop: 1, borderColor: 'divider' }}>
        <AjudaContextual rotulo={t('shell.ajuda')}>{t('shell.ajudaTexto')}</AjudaContextual>
      </Box>
    </Box>
  )
  const papelLateral = {
    width: LARGURA_MENU,
    maxWidth: '85vw',
    bgcolor: 'background.paper',
    borderRight: 1,
    borderColor: 'divider',
  }

  return (
    <Box sx={{ display: 'flex', minHeight: '100dvh', bgcolor: 'background.default' }}>
      <Box
        component="a"
        href={`#${ID_CONTEUDO}`}
        onClick={irAoConteudo}
        sx={{
          position: 'absolute',
          left: 8,
          top: -64,
          zIndex: 'tooltip',
          p: 1.5,
          borderRadius: 1,
          bgcolor: 'background.paper',
          color: 'text.primary',
          '&:focus': { top: 8 },
        }}
      >
        {t('shell.pularParaConteudo')}
      </Box>

      <AppBar
        position="fixed"
        color="inherit"
        elevation={0}
        sx={{ zIndex: (tema) => tema.zIndex.drawer + 1, bgcolor: 'background.paper', borderBottom: 1, borderColor: 'divider' }}
      >
        <Toolbar sx={{ gap: 1, px: { xs: 1, sm: 2 } }}>
          {!larga && (
            <IconButton color="inherit" edge="start" aria-label={t('shell.abrirMenu')} aria-expanded={menuAberto} onClick={() => setMenuAberto(true)}>
              <IconeMenu />
            </IconButton>
          )}
          <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.25, flexGrow: 1, minWidth: 0 }}>
            <Marca />
            <Box sx={{ minWidth: 0 }}>
              <Typography component="p" sx={{ fontWeight: 600, lineHeight: 1.2 }} noWrap>
                {organizacao?.nome ?? t('shell.titulo')}
              </Typography>
              {organizacao && (
                <Typography component="p" variant="caption" color="text.secondary" translate="no" noWrap sx={{ display: { xs: 'none', sm: 'block' } }}>
                  {t('shell.titulo')}
                </Typography>
              )}
            </Box>
          </Box>
          <Box sx={{ '& .rotulo-tema': { display: { xs: 'none', sm: 'inline' } } }}>
            <SeletorTema />
          </Box>
        </Toolbar>
      </AppBar>

      {larga ? (
        <Drawer variant="permanent" sx={{ width: LARGURA_MENU, flexShrink: 0, '& .MuiDrawer-paper': papelLateral }}>
          {lateral}
        </Drawer>
      ) : (
        <Drawer open={menuAberto} onClose={() => setMenuAberto(false)} sx={{ '& .MuiDrawer-paper': papelLateral }}>
          {lateral}
        </Drawer>
      )}

      <Box
        component="main"
        id={ID_CONTEUDO}
        ref={conteudo}
        tabIndex={-1}
        sx={{ flexGrow: 1, minWidth: 0, px: { xs: 2, sm: 3, md: 4, xl: 6 }, pb: { xs: 4, md: 6 }, outline: 'none' }}
      >
        <Toolbar />
        <Box sx={{ pt: { xs: 3, md: 4 } }}>
          {consulta.isPending && <EstadoCarregando texto={t('shell.carregando')} />}
          {consulta.isError && (
            <EstadoErro
              titulo={t('shell.erroTitulo')}
              texto={t('shell.erroTexto')}
              rotuloTentar={t('shell.tentarDeNovo')}
              aoTentarDeNovo={() => void consulta.refetch()}
            />
          )}
          {consulta.isSuccess && <Outlet />}
        </Box>
      </Box>
    </Box>
  )
}
