import { requisitar } from '../../../shared/http'

export interface Organizacao {
  uuid: string
  nome: string
  fuso: string
  cor_primaria: string | null
  modo_matricula: string
  limite_whatsapp_mensal: number | null
  email_contato: string | null
  logo_url: string | null
}

export interface Igreja { uuid: string; nome: string; regional: { uuid: string; nome: string } | null }
export interface ItemLista { uuid: string; nome: string; ordem: number; ativo: boolean }

export const obterOrganizacao = (): Promise<{ data: Organizacao }> => requisitar('GET', '/painel/organizacao')
export const salvarOrganizacao = (dados: Partial<Organizacao>): Promise<{ data: Organizacao }> => requisitar('PUT', '/painel/organizacao', dados)
export const listarIgrejas = (): Promise<{ data: Igreja[] }> => requisitar('GET', '/painel/igrejas')
export const criarIgreja = (nome: string): Promise<{ data: Igreja }> => requisitar('POST', '/painel/igrejas', { nome })
export const editarIgreja = (uuid: string, dados: { nome?: string; regional?: string | null }): Promise<{ data: Igreja }> => requisitar('PUT', `/painel/igrejas/${uuid}`, dados)
export const listarValores = (lista: string): Promise<{ data: ItemLista[] }> => requisitar('GET', `/painel/listas/${lista}`)
export const criarValor = (lista: string, nome: string): Promise<{ data: ItemLista }> => requisitar('POST', `/painel/listas/${lista}`, { nome })
export const editarValor = (lista: string, uuid: string, dados: Partial<Pick<ItemLista, 'nome' | 'ordem' | 'ativo'>>): Promise<{ data: ItemLista }> => requisitar('PUT', `/painel/listas/${lista}/${uuid}`, dados)
export const obterObrigatorios = (): Promise<{ data: Record<string, string[]> }> => requisitar('GET', '/painel/configuracao/obrigatorios')
export const salvarObrigatorios = (vinculo: string, campos: string[]): Promise<{ data: Record<string, string[]> }> => requisitar('PUT', '/painel/configuracao/obrigatorios', { vinculo, campos })
