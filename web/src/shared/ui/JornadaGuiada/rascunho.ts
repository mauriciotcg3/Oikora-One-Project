import type { FieldValues } from 'react-hook-form'

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
