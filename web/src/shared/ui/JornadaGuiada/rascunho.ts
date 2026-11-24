import type { FieldValues } from 'react-hook-form'
import { ErroHttp, requisitar } from '../../http'

export interface RascunhoJornada<T extends FieldValues> {
  tipo: string
  etapa: string
  dados: T
  versao: number
  atualizado_em: string
  expira_em: string
}

export interface PersistenciaJornada<T extends FieldValues> {
  carregar: () => Promise<RascunhoJornada<T> | null>
  salvar: (etapa: string, dados: T) => Promise<RascunhoJornada<T>>
  descartar: () => Promise<void>
}

/** Persistência opt-in da jornada; conflito 409 recarrega o estado do servidor sem mesclar dados. */
export function criarPersistenciaJornada<T extends FieldValues>(tipo: string): PersistenciaJornada<T> {
  let versao = 0
  const caminho = `/painel/rascunhos/${encodeURIComponent(tipo)}`
  const carregar = async (): Promise<RascunhoJornada<T> | null> => {
    const resposta = await requisitar<{ data: RascunhoJornada<T> | null }>('GET', caminho)
    versao = resposta.data?.versao ?? 0
    return resposta.data
  }

  return {
    carregar,
    salvar: async (etapa, dados) => {
      try {
        const resposta = await requisitar<{ data: RascunhoJornada<T> }>('PUT', caminho, { etapa, dados, versao })
        versao = resposta.data.versao
        return resposta.data
      } catch (erro) {
        if (erro instanceof ErroHttp && erro.status === 409 && erro.codigo === 'rascunho_desatualizado') {
          const atual = await carregar()
          if (atual !== null) return atual
        }
        throw erro
      }
    },
    descartar: async () => {
      await requisitar('DELETE', caminho)
      versao = 0
    },
  }
}
