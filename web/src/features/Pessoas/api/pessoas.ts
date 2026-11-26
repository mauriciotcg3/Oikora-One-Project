import { requisitar } from '../../../shared/http'

export interface PessoaResumo {
  uuid: string
  nome: string
  igreja?: { uuid: string; nome: string } | null
  vinculo?: string
  situacao?: string
  nascimento?: string | null
  completude?: number | { percentual: number; obrigatorios?: string[]; faltando?: string[] } | null
  meta?: { acoes?: string[] }
}

export interface PaginaPessoas {
  data: PessoaResumo[]
  meta: { pagina: number; por_pagina: number; total: number; criterio?: string }
}

export interface FichaPessoa extends PessoaResumo {
  campos: Record<string, unknown>
  contatos?: Array<Record<string, unknown>>
  enderecos?: Array<Record<string, unknown>>
  foto_url?: string | null
}

export interface RespostaFicha { data: FichaPessoa; meta: { acoes: string[]; categorias: string[] } }
export interface RegistroGenerico { uuid: string; [chave: string]: unknown }
export interface Duplicidade { uuid: string; nome: string; igreja?: { nome: string }; vinculo?: string; na_lixeira?: boolean }

const consulta = (valores: Record<string, string | number | boolean | undefined>): string => {
  const parametros = new URLSearchParams()
  Object.entries(valores).forEach(([chave, valor]) => { if (valor !== undefined && valor !== '') parametros.set(chave, String(valor)) })
  const texto = parametros.toString()
  return texto === '' ? '' : `?${texto}`
}

export const listarPessoas = (filtros: Record<string, string | number | boolean | undefined>): Promise<PaginaPessoas> =>
  requisitar('GET', `/painel/pessoas${consulta(filtros)}`)

export const buscarPessoas = (q: string): Promise<{ data: PessoaResumo[] }> =>
  requisitar('GET', `/painel/busca${consulta({ q })}`)

export const verificarDuplicidade = (nome: string, nascimento: string): Promise<{ data: Duplicidade[] }> =>
  requisitar('GET', `/painel/pessoas/duplicidade${consulta({ nome, nascimento })}`)

export const cadastrarPessoa = (dados: Record<string, unknown>): Promise<{ data: FichaPessoa }> =>
  requisitar('POST', '/painel/pessoas', dados)

export const obterFicha = (uuid: string): Promise<RespostaFicha> => requisitar('GET', `/painel/pessoas/${uuid}`)
export const editarFicha = (uuid: string, dados: Record<string, unknown>): Promise<RespostaFicha> => requisitar('PATCH', `/painel/pessoas/${uuid}`, dados)

export const listarRecurso = (caminho: string): Promise<{ data: RegistroGenerico[]; meta?: Record<string, unknown> }> =>
  requisitar('GET', caminho)
export const enviarRecurso = <T>(metodo: 'POST' | 'PUT' | 'PATCH' | 'DELETE', caminho: string, dados?: unknown): Promise<T> =>
  requisitar(metodo, caminho, dados)

export const criarExportacao = (dados: Record<string, unknown>): Promise<{ data: RegistroGenerico }> =>
  requisitar('POST', '/painel/exportacoes', dados)

export const obterExportacao = (uuid: string): Promise<{ data: RegistroGenerico }> =>
  requisitar('GET', `/painel/exportacoes/${uuid}`)
