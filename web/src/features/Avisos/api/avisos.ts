import { requisitar } from '../../../shared/http'

export interface Aviso { id: string; titulo: string; texto: string; acao: string; destino: string | null; lido_em: string | null; criado_em: string }
export interface Preferencia { tipo: string; categoria: string; obrigatorio: boolean; permite_escolher_canal: boolean; canais: Array<{ canal: string; ligado: boolean }> }

export const listarAvisos = (): Promise<{ data: Aviso[]; meta: { nao_lidos: number } }> => requisitar('GET', '/avisos/')
export const marcarAviso = (id: string): Promise<void> => requisitar('POST', `/avisos/${id}/lido`)
export const marcarTodos = (): Promise<{ marcados: number; nao_lidos: number }> => requisitar('POST', '/avisos/lidos')
export const listarPreferencias = (): Promise<{ data: Preferencia[] }> => requisitar('GET', '/conta/preferencias-aviso')
export const definirPreferencia = (tipo: string, canal: string, ligado: boolean): Promise<{ data: Preferencia[] }> => requisitar('PUT', '/conta/preferencias-aviso', { tipo, canal, ligado })
export const desligarWhatsapp = (): Promise<void> => requisitar('DELETE', '/painel/avisos/whatsapp')
