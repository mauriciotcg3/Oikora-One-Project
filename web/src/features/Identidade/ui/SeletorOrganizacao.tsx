import FormControl from '@mui/material/FormControl'
import InputLabel from '@mui/material/InputLabel'
import MenuItem from '@mui/material/MenuItem'
import Select from '@mui/material/Select'
import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { useEscolherOrganizacao, useMe } from '../model/useConta'

/**
 * Seletor da organização ativa (OIK-ADR-0002 §8; T-16): aparece só para quem tem acesso a mais de uma.
 * Trocar invalida o cache do painel inteiro: nada da organização anterior continua na tela.
 */
export function SeletorOrganizacao(): ReactNode {
  const { t } = useTranslation('identidade')
  const me = useMe()
  const escolher = useEscolherOrganizacao()

  const organizacoes = me.data?.organizacoes ?? []
  if (organizacoes.length < 2) return null

  return (
    <FormControl size="small" sx={{ minWidth: 220 }}>
      <InputLabel id="seletor-organizacao">{t('organizacao.rotulo')}</InputLabel>
      <Select
        labelId="seletor-organizacao"
        label={t('organizacao.rotulo')}
        value={me.data?.organizacao_ativa?.id ?? ''}
        disabled={escolher.isPending}
        onChange={(evento) => escolher.mutate(String(evento.target.value))}
      >
        {organizacoes.map((organizacao) => (
          <MenuItem key={organizacao.id} value={organizacao.id}>{organizacao.nome}</MenuItem>
        ))}
      </Select>
    </FormControl>
  )
}
