// Propagação W3C Trace Context no navegador (OIK-ADR-0006 §14): cada pedido abre um trace novo, e o
// `trace_id` acompanha o erro até o Error Boundary, para o suporte achar o pedido no log da API.

export interface ContextoTrace {
  traceId: string
  traceparent: string
}

let ultimoTraceId: string | undefined

function hexAleatorio(bytes: number): string {
  const valores = new Uint8Array(bytes)
  crypto.getRandomValues(valores)
  return Array.from(valores, (valor) => valor.toString(16).padStart(2, '0')).join('')
}

/** Abre um trace novo (`00-<trace-id>-<span-id>-01`) e o guarda como o último do navegador. */
export function novoTrace(): ContextoTrace {
  const traceId = hexAleatorio(16)
  ultimoTraceId = traceId
  return { traceId, traceparent: `00-${traceId}-${hexAleatorio(8)}-01` }
}

/** `trace_id` do pedido mais recente; o Error Boundary usa quando o erro não traz o próprio. */
export function traceAtual(): string {
  return ultimoTraceId ?? novoTrace().traceId
}
