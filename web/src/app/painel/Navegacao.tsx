import List from '@mui/material/List'
import ListItemButton from '@mui/material/ListItemButton'
import ListItemText from '@mui/material/ListItemText'
import { Link } from '@tanstack/react-router'
import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'

/** Item de menu: aparece quando a pessoa tem a capacidade (sem `permissao`, aparece sempre). */
interface ItemNavegacao {
  chave: 'inicio' | 'auditoria'
  para: '/' | '/auditoria'
  permissao?: string
}

// Novas telas entram aqui junto com a rota que as atende; item sem rota quebraria a navegação.
const ITENS: readonly ItemNavegacao[] = [
  { chave: 'inicio', para: '/' },
  { chave: 'auditoria', para: '/auditoria', permissao: 'auditoria.ver' },
]

/** Itens visíveis para as capacidades da pessoa (OIK-ADR-0006 §9); o servidor continua decidindo cada acesso. */
export function itensVisiveis(tem: (permissao: string) => boolean): readonly ItemNavegacao[] {
  return ITENS.filter((item) => item.permissao === undefined || tem(item.permissao))
}

interface Props {
  tem: (permissao: string) => boolean
  aoNavegar?: () => void
}

export function Navegacao({ tem, aoNavegar }: Props): ReactNode {
  const { t } = useTranslation('painel')
  return (
    <nav aria-label={t('shell.navegacao')}>
      <List>
        {itensVisiveis(tem).map((item) => (
          <ListItemButton
            key={item.chave}
            component={Link}
            to={item.para}
            activeOptions={{ exact: true }}
            onClick={aoNavegar}
            sx={{ '&[aria-current="page"]': { bgcolor: 'action.selected', fontWeight: 600 } }}
          >
            <ListItemText primary={t(`shell.itens.${item.chave}`)} />
          </ListItemButton>
        ))}
      </List>
    </nav>
  )
}
