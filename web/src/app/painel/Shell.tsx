import AppBar from '@mui/material/AppBar'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Drawer from '@mui/material/Drawer'
import Toolbar from '@mui/material/Toolbar'
import Typography from '@mui/material/Typography'
import useMediaQuery from '@mui/material/useMediaQuery'
import { Outlet } from '@tanstack/react-router'
import { useRef, useState, type MouseEvent, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { AjudaContextual, EstadoCarregando, EstadoErro } from '../../shared/ui'
import { useCapacidades } from './capacidades'
import { Navegacao } from './Navegacao'

const LARGURA_MENU = 240
const TELA_LARGA = '(min-width:900px)'
const ID_CONTEUDO = 'conteudo'

/** Shell do painel: cabeçalho, navegação lateral responsiva, pular ao conteúdo e ajuda fixa (OIK-ADR-0014). */
export function Shell(): ReactNode {
  const { t } = useTranslation('painel')
  const { consulta, organizacao, tem } = useCapacidades()
  const larga = useMediaQuery(TELA_LARGA)
  const [menuAberto, setMenuAberto] = useState(false)
  const conteudo = useRef<HTMLElement>(null)

  const irAoConteudo = (evento: MouseEvent): void => {
    evento.preventDefault()
    conteudo.current?.focus()
  }

  const navegacao = <Navegacao tem={tem} aoNavegar={() => setMenuAberto(false)} />

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

      <AppBar position="fixed" color="primary" sx={{ zIndex: (tema) => tema.zIndex.drawer + 1 }}>
        <Toolbar>
          {!larga && (
            <Button color="inherit" aria-expanded={menuAberto} onClick={() => setMenuAberto(true)} sx={{ mr: 1 }}>
              {t('shell.abrirMenu')}
            </Button>
          )}
          <Typography component="p" variant="h6" sx={{ flexGrow: 1 }}>
            {organizacao?.nome ?? t('shell.titulo')}
          </Typography>
        </Toolbar>
      </AppBar>

      {larga ? (
        <Drawer variant="permanent" sx={{ width: LARGURA_MENU, '& .MuiDrawer-paper': { width: LARGURA_MENU } }}>
          <Toolbar />
          {navegacao}
        </Drawer>
      ) : (
        <Drawer open={menuAberto} onClose={() => setMenuAberto(false)} sx={{ '& .MuiDrawer-paper': { width: LARGURA_MENU } }}>
          <Toolbar />
          {navegacao}
        </Drawer>
      )}

      <Box component="main" id={ID_CONTEUDO} ref={conteudo} tabIndex={-1} sx={{ flexGrow: 1, minWidth: 0, p: 3, outline: 'none' }}>
        <Toolbar />
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

      <Box
        sx={{
          position: 'fixed',
          right: 16,
          bottom: 16,
          zIndex: 'speedDial',
          maxWidth: 320,
          p: 1,
          borderRadius: 2,
          border: 1,
          borderColor: 'divider',
          bgcolor: 'background.paper',
        }}
      >
        <AjudaContextual rotulo={t('shell.ajuda')}>{t('shell.ajudaTexto')}</AjudaContextual>
      </Box>
    </Box>
  )
}
