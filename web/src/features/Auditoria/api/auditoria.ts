import { requisitar } from '../../../shared/http'

// GET /painel/auditoria (T-46): `de`, `ate` (datas no fuso da organização), `evento`, `pagina`,
// `por_pagina`. Cada linha traz só nomes de campo (`campos`), nunca valores (OIK-ADR-0005 §5).

export interface LinhaAuditoria {
  ocorrido_em: string
  evento: string
  ator: { acesso: string | null; plataforma: boolean }
  alvo: { tipo: string; id: string }
  igreja: { uuid: string; nome: string } | null
  campos: string[]
  canal: string | null
  request_id: string | null
}

export interface FiltroAuditoria {
  de?: string
  ate?: string
  evento?: string
  pagina?: number
}

export async function consultarAuditoria(filtro: FiltroAuditoria): Promise<{ itens: LinhaAuditoria[]; total: number }> {
  const parametros = new URLSearchParams()
  for (const [chave, valor] of Object.entries(filtro)) {
    if (valor !== undefined && valor !== '') parametros.set(chave, String(valor))
  }
  const consulta = parametros.toString()
  const resposta = await requisitar<{ data: LinhaAuditoria[]; meta: { total: number } }>(
    'GET',
    `/painel/auditoria${consulta ? `?${consulta}` : ''}`,
  )
  return { itens: resposta.data, total: resposta.meta.total }
}
