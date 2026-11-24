import { useNavigate, useSearch } from '@tanstack/react-router'
import { z } from 'zod'
import { registrarEventoUi } from '../../observabilidade'

/** Tamanhos de página aceitos pela API (OIK-ADR-0006 §6; limite do DataGrid MIT). */
export const TAMANHOS_DE_PAGINA = [25, 50, 100] as const
const TAMANHO_PADRAO = 25

export interface PaginacaoDaUrl {
  /** Página atual, a partir de 1. */
  pagina: number
  porPagina: number
}

const esquema = z.object({
  pagina: z.coerce.number().int().min(1).max(100_000).catch(1),
  por_pagina: z.coerce
    .number()
    .pipe(z.union([z.literal(25), z.literal(50), z.literal(100)]))
    .catch(TAMANHO_PADRAO),
})

/** Lê `pagina` e `por_pagina` da URL; valor ausente ou inválido volta ao padrão (página 1, 25 linhas). */
export function lerPaginacao(busca: unknown): PaginacaoDaUrl {
  const { pagina, por_pagina } = esquema.parse(typeof busca === 'object' && busca !== null ? busca : {})
  return { pagina, porPagina: por_pagina }
}

/**
 * Página e tamanho que vivem na URL, para a rota pedir à API só aquela página. Os valores padrão
 * saem da URL, então o link da primeira página fica limpo.
 */
export function usePaginacaoDaUrl(): PaginacaoDaUrl & { mudar: (pagina: number, porPagina: number) => void } {
  const busca = useSearch({ strict: false })
  const navegar = useNavigate()
  const atual = lerPaginacao(busca)

  const mudar = (pagina: number, porPagina: number): void => {
    // Trocar o tamanho muda onde cada linha cai: volta à primeira página.
    const paginaFinal = porPagina === atual.porPagina ? pagina : 1
    registrarEventoUi('ui.pagina_alterada', { pagina: paginaFinal, por_pagina: porPagina })
    void navegar({
      to: '.',
      search: ((anterior: Record<string, unknown>) => ({
        ...anterior,
        pagina: paginaFinal === 1 ? undefined : paginaFinal,
        por_pagina: porPagina === TAMANHO_PADRAO ? undefined : porPagina,
      })) as never,
    })
  }

  return { ...atual, mudar }
}
