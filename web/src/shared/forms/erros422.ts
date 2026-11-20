// 422 do Laravel → erro no campo (OIK-ADR-0006 §5). `errors` vem por caminho (`contatos.0.valor`),
// o mesmo formato de nome do React Hook Form. Campos que não estão na tela vão para um resumo.

import type { FieldValues, Path, UseFormSetError, UseFormSetFocus } from 'react-hook-form'
import { ErroHttp } from '../http'

export interface ErroForaDaTela {
  campo: string
  mensagem: string
}

interface Formulario<T extends FieldValues> {
  setError: UseFormSetError<T>
  setFocus: UseFormSetFocus<T>
}

/**
 * Aplica o 422 ao formulário: erro em cada campo da tela, foco no primeiro deles (na ordem da tela) e
 * devolve os erros de campos fora da tela. Devolve `null` quando o erro não é um 422 com `errors`.
 */
export function aplicarErros422<T extends FieldValues>(
  erro: unknown,
  formulario: Formulario<T>,
  camposNaTela: readonly Path<T>[],
): ErroForaDaTela[] | null {
  if (!(erro instanceof ErroHttp) || erro.status !== 422 || !erro.corpo.errors) return null

  const naTela = new Set<string>(camposNaTela)
  const fora: ErroForaDaTela[] = []

  for (const [campo, mensagens] of Object.entries(erro.corpo.errors)) {
    const mensagem = mensagens[0] ?? ''
    if (naTela.has(campo)) {
      formulario.setError(campo as Path<T>, { type: 'server', message: mensagem })
    } else {
      fora.push({ campo, mensagem })
    }
  }

  const primeiro = camposNaTela.find((campo) => campo in (erro.corpo.errors ?? {}))
  if (primeiro !== undefined) formulario.setFocus(primeiro)

  return fora
}
