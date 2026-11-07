import { QueryClient } from '@tanstack/react-query'

export function criarClienteConsulta(): QueryClient {
  return new QueryClient({ defaultOptions: { queries: { staleTime: 30_000 } } })
}
