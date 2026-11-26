/** Junta a seleção desta página à das outras: só os ids da página atual mudam. */
export function mesclarSelecao(anteriores: ReadonlySet<string>, idsDaPagina: readonly string[], marcadasNaPagina: Iterable<string>): Set<string> {
  const daPagina = new Set(idsDaPagina)
  const novas = new Set([...anteriores].filter((id) => !daPagina.has(id)))
  for (const id of marcadasNaPagina) novas.add(id)
  return novas
}
