import { useQuery } from '@tanstack/react-query'
import { requisitar } from '../../shared/http'

/** Parte do `GET /me` usada pelo shell (`contrato-permissoes.md`). A chave `['me']` é a que a sessão lê. */
export interface RespostaMe {
  data: {
    organizacao_ativa: { id: string; nome: string } | null
    autorizacao: { capacidades: readonly { permissao: string }[] }
  }
}

export const CHAVE_ME = ['me'] as const

/** Carrega o `/me` (compartilhado por chave) e responde se a pessoa tem uma capacidade. Só orienta a tela: o servidor decide. */
export function useCapacidades() {
  const consulta = useQuery({ queryKey: CHAVE_ME, queryFn: () => requisitar<RespostaMe>('GET', '/me') })
  const permissoes = new Set(consulta.data?.data.autorizacao.capacidades.map((capacidade) => capacidade.permissao))
  return {
    consulta,
    organizacao: consulta.data?.data.organizacao_ativa ?? null,
    tem: (permissao: string): boolean => permissoes.has(permissao),
  }
}
