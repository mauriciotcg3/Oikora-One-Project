import Button from '@mui/material/Button'
import ListItemIcon from '@mui/material/ListItemIcon'
import ListItemText from '@mui/material/ListItemText'
import Menu from '@mui/material/Menu'
import MenuItem from '@mui/material/MenuItem'
import { useColorScheme } from '@mui/material/styles'
import { useId, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { registrarEventoUi } from '../../shared/observabilidade'
import { IconeLua, IconeSistema, IconeSol } from './icones'

type Modo = 'system' | 'light' | 'dark'

const OPCOES: readonly { modo: Modo; icone: () => ReactNode }[] = [
  { modo: 'system', icone: IconeSistema },
  { modo: 'light', icone: IconeSol },
  { modo: 'dark', icone: IconeLua },
]

/**
 * Escolha claro, escuro ou "igual ao sistema" (padrão). A MUI guarda a escolha no navegador
 * (`CHAVE_MODO_DE_COR`), é preferência de quem usa e não vai para o servidor.
 */
export function SeletorTema(): ReactNode {
  const { t } = useTranslation('painel')
  const { mode, setMode } = useColorScheme()
  const [ancora, setAncora] = useState<HTMLElement | null>(null)
  const idMenu = useId()
  const atual: Modo = mode ?? 'system'
  const Icone = OPCOES.find((opcao) => opcao.modo === atual)?.icone ?? IconeSistema

  const escolher = (modo: Modo): void => {
    setMode(modo)
    registrarEventoUi('ui.tema_alterado', { modo })
    setAncora(null)
  }

  return (
    <>
      <Button
        color="inherit"
        size="small"
        startIcon={<Icone />}
        aria-haspopup="menu"
        aria-expanded={ancora !== null}
        aria-controls={ancora ? idMenu : undefined}
        aria-label={t('tema.rotulo', { atual: t(`tema.${atual}`) })}
        onClick={(evento) => setAncora(evento.currentTarget)}
        sx={{ minWidth: 0 }}
      >
        <span className="rotulo-tema">{t(`tema.${atual}`)}</span>
      </Button>
      <Menu id={idMenu} anchorEl={ancora} open={ancora !== null} onClose={() => setAncora(null)}>
        {OPCOES.map(({ modo, icone: IconeOpcao }) => (
          <MenuItem key={modo} role="menuitemradio" aria-checked={atual === modo} selected={atual === modo} onClick={() => escolher(modo)}>
            <ListItemIcon>
              <IconeOpcao />
            </ListItemIcon>
            <ListItemText>{t(`tema.${modo}`)}</ListItemText>
          </MenuItem>
        ))}
      </Menu>
    </>
  )
}
