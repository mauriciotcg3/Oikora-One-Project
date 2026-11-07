import { parsePhoneNumberFromString } from 'libphonenumber-js/min'

// E-mail ou celular num campo só (OIK-ADR-0003 §1): reconhece o tipo enquanto a pessoa digita,
// aplica a máscara brasileira e normaliza para a API (e-mail minúsculo, celular em E.164).

export type ChaveValidacaoIdentificador = 'identificadorVazio' | 'celularIncompleto' | 'emailIncompleto'

const somenteDigitos = (valor: string): string => valor.replace(/\D/g, '')

export function pareceCelular(valor: string): boolean {
  return /^[\s(+]*\d/.test(valor) && !valor.includes('@')
}

export function mascararCelular(valor: string): string {
  let digitos = somenteDigitos(valor)
  if (digitos.startsWith('55') && digitos.length > 11) digitos = digitos.slice(2)
  digitos = digitos.slice(0, 11)
  if (digitos.length <= 2) return digitos.length ? `(${digitos}` : ''
  if (digitos.length <= 6) return `(${digitos.slice(0, 2)}) ${digitos.slice(2)}`
  if (digitos.length <= 10) return `(${digitos.slice(0, 2)}) ${digitos.slice(2, 6)}-${digitos.slice(6)}`
  return `(${digitos.slice(0, 2)}) ${digitos.slice(2, 7)}-${digitos.slice(7)}`
}

/** Valor exibido no campo: celular mascarado; e-mail como digitado. */
export function formatarIdentificador(valor: string): string {
  return pareceCelular(valor) ? mascararCelular(valor) : valor
}

function celularE164(valor: string): string | undefined {
  const numero = parsePhoneNumberFromString(somenteDigitos(valor), 'BR')
  return numero?.isValid() ? numero.number : undefined
}

export function validarIdentificador(valor: string): ChaveValidacaoIdentificador | undefined {
  const texto = valor.trim()
  if (!texto) return 'identificadorVazio'
  if (pareceCelular(texto)) return celularE164(texto) ? undefined : 'celularIncompleto'
  return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(texto) ? undefined : 'emailIncompleto'
}

/** Formato enviado à API; chamar só depois de `validarIdentificador`. */
export function normalizarIdentificador(valor: string): string {
  const texto = valor.trim()
  return pareceCelular(texto) ? (celularE164(texto) ?? texto) : texto.toLowerCase()
}
