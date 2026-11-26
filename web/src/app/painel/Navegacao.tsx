import List from '@mui/material/List'
import ListItemButton from '@mui/material/ListItemButton'
import ListItemText from '@mui/material/ListItemText'
import { Link } from '@tanstack/react-router'
import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'

/** Item de menu: aparece quando a pessoa tem ao menos uma capacidade (sem `permissoes`, aparece sempre). */
interface ItemNavegacao {
  chave: 'inicio' | 'pessoas' | 'acompanhamentos' | 'operacoes' | 'avisos' | 'whatsapp' | 'configuracao' | 'auditoria' | 'solicitacoes'
  para: '/' | '/pessoas' | '/pessoas/acompanhamentos' | '/pessoas/operacoes' | '/avisos' | '/whatsapp' | '/configuracao' | '/auditoria' | '/solicitacoes'
  permissoes?: readonly string[]
}

// Novas telas entram aqui junto com a rota que as atende; item sem rota quebraria a navegação.
const ITENS: readonly ItemNavegacao[] = [
  { chave: 'inicio', para: '/' },
  { chave: 'pessoas', para: '/pessoas', permissoes: ['pessoas.ver'] },
  { chave: 'acompanhamentos', para: '/pessoas/acompanhamentos', permissoes: ['pessoas.sensiveis.fe.ver'] },
  {
    chave: 'operacoes',
    para: '/pessoas/operacoes',
    permissoes: ['pessoas.mesclar', 'transferencias.aprovar', 'transferencias.solicitar', 'pessoas.excluir'],
  },
  { chave: 'avisos', para: '/avisos' },
  { chave: 'whatsapp', para: '/whatsapp', permissoes: ['avisos.whatsapp.gerir'] },
  {
    chave: 'configuracao',
    para: '/configuracao',
    permissoes: ['organizacao.configurar', 'igrejas.gerir', 'pessoas.configurar'],
  },
  { chave: 'solicitacoes', para: '/solicitacoes' },
  { chave: 'auditoria', para: '/auditoria', permissoes: ['auditoria.ver'] },
]

/** Itens visíveis para as capacidades da pessoa (OIK-ADR-0006 §9); o servidor continua decidindo cada acesso. */
export function itensVisiveis(tem: (permissao: string) => boolean): readonly ItemNavegacao[] {
  return ITENS.filter((item) => item.permissoes === undefined || item.permissoes.some(tem))
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
