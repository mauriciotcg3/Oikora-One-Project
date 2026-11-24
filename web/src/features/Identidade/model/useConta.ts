import { useMutation, useQuery, useQueryClient, type UseMutationResult, type UseQueryResult } from '@tanstack/react-query'
import { escolherOrganizacao, lerConvite, lerMe, type DadosConvite, type Me } from '../api/conta'

// Estado de servidor da conta (FE-STATE-001): `/me` e o convite em cache curto; trocar de organização
// invalida tudo, porque cada dado do painel é da organização ativa.

export const chaveMe = ['identidade', 'me'] as const

export function useMe(): UseQueryResult<Me | null> {
  return useQuery({ queryKey: chaveMe, queryFn: lerMe, staleTime: 30_000 })
}

export function useConvite(token: string): UseQueryResult<DadosConvite | null> {
  return useQuery({ queryKey: ['identidade', 'convite', token], queryFn: () => lerConvite(token), staleTime: Infinity })
}

export function useEscolherOrganizacao(): UseMutationResult<void, Error, string> {
  const cliente = useQueryClient()
  return useMutation({
    mutationFn: escolherOrganizacao,
    onSuccess: async () => {
      await cliente.invalidateQueries()
    },
  })
}
