import { useTranslation } from 'react-i18next'
import { ErroHttp } from '../../../shared/http'

// Peças comuns às telas de Pessoas: mensagem de erro traduzida (nunca o código cru), valores da ficha
// legíveis (sim/não, datas) e a seção de cada campo, igual à `CampoFicha::secao()` da API.

export type Secao = 'pessoais' | 'documentos' | 'contatos' | 'endereco' | 'saude' | 'eclesiasticos' | 'adicionais'

const SECAO_DO_CAMPO: Readonly<Record<string, Secao>> = {
  cpf: 'documentos',
  rg: 'documentos',
  rg_pais: 'documentos',
  celular: 'contatos',
  e_whatsapp: 'contatos',
  telefone: 'contatos',
  email: 'contatos',
  cep: 'endereco',
  logradouro: 'endereco',
  numero: 'endereco',
  complemento: 'endereco',
  bairro: 'endereco',
  cidade: 'endereco',
  estado: 'endereco',
  pais: 'endereco',
  necessidades_especiais: 'saude',
  tipo_sanguineo: 'saude',
  aceitou_jesus: 'eclesiasticos',
  conversao_data: 'eclesiasticos',
  conversao_canal: 'eclesiasticos',
  batismo_data: 'eclesiasticos',
  batizado_por: 'eclesiasticos',
  igreja_origem: 'eclesiasticos',
  e_lider: 'eclesiasticos',
  e_pastor: 'eclesiasticos',
  novo_convertido: 'eclesiasticos',
  escolaridade: 'adicionais',
  profissao: 'adicionais',
  nacionalidade: 'adicionais',
  naturalidade: 'adicionais',
}

export const ORDEM_DAS_SECOES: readonly Secao[] = ['pessoais', 'documentos', 'contatos', 'endereco', 'saude', 'eclesiasticos', 'adicionais']

/** Campo sem ponto vai pela tabela da API; `secao.campo` (formato antigo) usa o prefixo. */
export function secaoDoCampo(chave: string): Secao {
  const [prefixo, nome] = chave.includes('.') ? (chave.split('.') as [string, string]) : [undefined, chave]
  if (prefixo && (ORDEM_DAS_SECOES as readonly string[]).includes(prefixo)) return prefixo as Secao
  return SECAO_DO_CAMPO[nome] ?? 'pessoais'
}

export const nomeDoCampo = (chave: string): string => chave.split('.').at(-1) ?? chave

const DATA_ISO = /^\d{4}-\d{2}-\d{2}$/
const DATA_HORA_ISO = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/

/** Formata data `AAAA-MM-DD` sem fuso (dia civil) e data-hora ISO no fuso de quem usa. */
export function formatarData(valor: string, idioma: string): string {
  if (DATA_ISO.test(valor)) {
    const [ano, mes, dia] = valor.split('-').map(Number) as [number, number, number]
    return new Intl.DateTimeFormat(idioma, { dateStyle: 'short', timeZone: 'UTC' }).format(new Date(Date.UTC(ano, mes - 1, dia)))
  }
  const data = new Date(valor)
  return Number.isNaN(data.getTime()) ? valor : new Intl.DateTimeFormat(idioma, { dateStyle: 'short' }).format(data)
}

/** Valores da ficha para leitura: sim/não, datas no idioma, listas e nada de "[object Object]". */
export function useFormatarValor(): (valor: unknown) => string {
  const { t, i18n } = useTranslation('pessoas')
  const formatar = (valor: unknown): string => {
    if (valor === null || valor === undefined || valor === '') return t('comum.naoInformado')
    if (typeof valor === 'boolean') return valor ? t('comum.sim') : t('comum.nao')
    if (typeof valor === 'number') return new Intl.NumberFormat(i18n.language).format(valor)
    if (typeof valor === 'string') return DATA_ISO.test(valor) || DATA_HORA_ISO.test(valor) ? formatarData(valor, i18n.language) : valor
    if (Array.isArray(valor)) return valor.length === 0 ? t('comum.naoInformado') : valor.map(formatar).join(', ')
    if (typeof valor === 'object') {
      const nome = (valor as { nome?: unknown }).nome
      return typeof nome === 'string' ? nome : t('comum.naoInformado')
    }
    return String(valor)
  }
  return formatar
}

/** Texto do erro para a pessoa: código conhecido traduzido; senão, pelo status HTTP. Nunca o código cru. */
export function useMensagemDeErro(): (erro: unknown) => string {
  const { t, i18n } = useTranslation('pessoas')
  return (erro: unknown): string => {
    if (!(erro instanceof ErroHttp)) return t('erros.generico')
    const codigo = erro.codigo ?? Object.values(erro.corpo.errors ?? {}).flat()[0]
    const chave = codigo ? `erros.codigos.${codigo.replaceAll('.', '_')}` : undefined
    if (chave && i18n.exists(chave, { ns: 'pessoas' })) return t(chave as never)
    if (erro.status === 403) return t('erros.semPermissao')
    if (erro.status === 404) return t('erros.naoEncontrado')
    if (erro.status === 409) return t('erros.conflito')
    if (erro.status === 422) return t('erros.validacao')
    return t('erros.generico')
  }
}
