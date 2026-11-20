import createClient, { type Client, type ClientOptions } from 'openapi-fetch';
import type { components, operations, paths } from './esquema';

// Tipos gerados de `api/openapi.json` por `pnpm --filter @oikora/api-contrato gerar` (OIK-ADR-0006 §8).
export type { components, operations, paths };

export type ClienteApi = Client<paths>;

/**
 * Cliente fino e tipado da `/api/v1`. Sem React e sem DOM: o `web/src/shared/http` o envolve com
 * CSRF, `X-Request-Id` e tratamento de erro (FE-COMM-001). Campos de projeção chegam opcionais nos
 * tipos, porque a API pode omiti-los.
 */
export function criarClienteApi(opcoes: ClientOptions = {}): ClienteApi {
  return createClient<paths>({ baseUrl: '/api/v1', credentials: 'include', ...opcoes });
}
