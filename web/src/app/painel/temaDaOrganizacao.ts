import { useQuery } from '@tanstack/react-query'
import { useEffect } from 'react'
import { requisitar } from '../../shared/http'
import { useDefinirCorPrimaria } from '../../shared/ui'

/** Parte de `GET /painel/organizacao` que o tema usa; a leitura serve a todo o painel (nome, cor e logo). */
interface RespostaOrganizacao {
  data: { cor_primaria: string | null }
}

const CINCO_MINUTOS = 5 * 60_000

/**
 * Aplica a cor primária da organização ativa ao tema do painel. Sem cor, ou se a leitura falhar, fica
 * a cor Oikora; ao sair do shell (login, troca de conta) a cor volta ao padrão.
 */
export function useTemaDaOrganizacao(organizacaoId: string | null): void {
  const definirCor = useDefinirCorPrimaria()
  const consulta = useQuery({
    queryKey: ['organizacao', organizacaoId, 'tema'],
    queryFn: () => requisitar<RespostaOrganizacao>('GET', '/painel/organizacao'),
    enabled: organizacaoId !== null,
    staleTime: CINCO_MINUTOS,
    retry: false,
  })
  const cor = consulta.data?.data.cor_primaria ?? null

  useEffect(() => {
    definirCor(cor)
  }, [cor, definirCor])
  useEffect(() => () => definirCor(null), [definirCor])
}
