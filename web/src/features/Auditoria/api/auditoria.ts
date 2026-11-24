import { requisitar } from '../../../shared/http'

export interface LinhaAuditoria {
  id: string
  ocorrido_em: string
  evento: string
  ator: { acesso: string | null; nome: string | null; plataforma: boolean }
  alvo: { tipo: string; id: string }
  igreja: { uuid: string; nome: string } | null
  campos: string[]
  metadados: Record<string, unknown>
  canal: string | null
  request_id: string | null
  resultado: 'sucesso' | 'falha'
  codigo_falha: string | null
  trace_id: string | null
  falha: boolean
}

type LinhaDaApi = Omit<LinhaAuditoria, 'id' | 'falha'>

export interface FiltroAuditoria {
  de?: string
  ate?: string
  evento?: string
  ator?: string
  alvo_tipo?: string
  alvo?: string
  igreja?: string
  busca?: string
  resultado?: string
  pagina: number
  por_pagina: number
}

export async function consultarAuditoria(filtro: FiltroAuditoria): Promise<{ itens: LinhaAuditoria[]; total: number }> {
  const parametros = new URLSearchParams()
  for (const [chave, valor] of Object.entries(filtro)) {
    if (valor !== undefined && valor !== '') parametros.set(chave, String(valor))
  }
  const resposta = await requisitar<{ data: LinhaDaApi[]; meta: { total: number } }>('GET', `/painel/auditoria?${parametros.toString()}`)
  return {
    itens: resposta.data.map((linha, indice) => ({
      ...linha,
      id: [linha.ocorrido_em, linha.evento, linha.alvo.tipo, linha.alvo.id, linha.request_id ?? indice].join(':'),
      falha: linha.resultado === 'falha',
    })),
    total: resposta.meta.total,
  }
}
