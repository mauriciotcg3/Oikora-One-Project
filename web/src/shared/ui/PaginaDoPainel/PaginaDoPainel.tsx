import Box from '@mui/material/Box'
import Typography from '@mui/material/Typography'
import { useId, type ReactNode } from 'react'

/** Larguras máximas do conteúdo: formulário curto ou tela de consulta. Em monitor largo o texto não se estica. */
const LARGURAS = { estreita: 760, media: 1040, larga: 1360 } as const

export interface PaginaDoPainelProps {
  /** Título da tela (h1), já traduzido. */
  titulo: string
  /** Uma frase sobre o que a tela faz, já traduzida. */
  descricao?: ReactNode
  /** Ações principais da tela (ex.: "Cadastrar pessoa"); quebram para baixo do título em tela estreita. */
  acoes?: ReactNode
  /** Link de volta, acima do título (ex.: "Voltar para Pessoas"). */
  voltar?: ReactNode
  largura?: keyof typeof LARGURAS
  children: ReactNode
}

/**
 * Moldura das telas do painel (OIK-ADR-0014): cabeçalho no padrão do login (título forte, apoio em
 * texto secundário), ações que quebram linha e largura máxima centrada, sem rolagem horizontal de 320 px
 * a monitores ultrawide (WCAG 1.4.10).
 */
export function PaginaDoPainel({ titulo, descricao, acoes, voltar, largura = 'larga', children }: PaginaDoPainelProps): ReactNode {
  const idTitulo = useId()
  return (
    <Box
      component="section"
      aria-labelledby={idTitulo}
      sx={{ width: '100%', maxWidth: LARGURAS[largura], mx: 'auto', display: 'grid', gap: { xs: 2.5, md: 3 }, minWidth: 0 }}
    >
      <Box component="header" sx={{ display: 'flex', flexWrap: 'wrap', alignItems: 'flex-end', justifyContent: 'space-between', gap: 2 }}>
        <Box sx={{ display: 'grid', gap: 1, flex: '1 1 18rem', minWidth: 0 }}>
          {voltar && <Box sx={{ justifySelf: 'start' }}>{voltar}</Box>}
          <Typography id={idTitulo} variant="h1" sx={{ fontSize: { xs: '1.625rem', md: '2rem' }, textWrap: 'balance' }}>
            {titulo}
          </Typography>
          {descricao && (
            <Typography color="text.secondary" sx={{ maxWidth: '65ch' }}>
              {descricao}
            </Typography>
          )}
        </Box>
        {acoes && <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1 }}>{acoes}</Box>}
      </Box>
      {children}
    </Box>
  )
}
