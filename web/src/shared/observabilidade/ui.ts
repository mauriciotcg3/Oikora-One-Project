import { traceAtual } from './trace'

/** Contexto permitido num evento de interface: códigos e nomes, nunca conteúdo de campo (OIK-ADR-0014). */
export type ContextoEventoUi = Readonly<Record<string, string | number | boolean>>

/** Registra um evento `ui.*` com o `trace_id` corrente; o coletor OTel entra com a OIK-ADR-0010. */
export function registrarEventoUi(evento: `ui.${string}`, contexto: ContextoEventoUi = {}): void {
  console.info(evento, { trace_id: traceAtual(), ...contexto })
}
