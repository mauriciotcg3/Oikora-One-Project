import { describe, expect, expectTypeOf, it } from 'vitest';
import { criarClienteApi } from '../index';

// OIK-SPEC-0001 T-05 — cliente tipado gerado do contrato. Os `@ts-expect-error` só passam no
// `tsc --noEmit` (script `test`) se o TypeScript recusar o que o contrato não tem.

const BASE = 'http://localhost/api/v1';

function responderJson(corpo: unknown, chamadas: Request[] = []): (requisicao: Request) => Promise<Response> {
  return async (requisicao) => {
    chamadas.push(requisicao);
    return new Response(JSON.stringify(corpo), { status: 200, headers: { 'Content-Type': 'application/json' } });
  };
}

describe('cliente da API', () => {
  it('chama GET /saude na base da /api/v1 e devolve a resposta tipada', async () => {
    const chamadas: Request[] = [];
    const cliente = criarClienteApi({
      baseUrl: BASE,
      fetch: responderJson({ vivo: true, banco: 'ok', versao: 'dev', filas: { codigos: 0 } }, chamadas),
    });

    const { data, error } = await cliente.GET('/saude');

    expect(error).toBeUndefined();
    expect(chamadas).toHaveLength(1);
    expect(chamadas[0]?.method).toBe('GET');
    expect(chamadas[0]?.url).toBe(`${BASE}/saude`);
    expect(chamadas[0]?.credentials).toBe('include');
    expect(data?.filas['codigos']).toBe(0);
    expectTypeOf(data?.banco).toEqualTypeOf<'ok' | 'indisponivel' | undefined>();
    expectTypeOf(data?.filas).toEqualTypeOf<Record<string, number> | undefined>();
  });

  it('recusa no TypeScript campo e rota que o contrato não tem', async () => {
    const cliente = criarClienteApi({ baseUrl: BASE, fetch: responderJson({}) });

    const { data } = await cliente.GET('/saude');
    // @ts-expect-error `inexistente` não está no schema de /saude
    expect(data?.inexistente).toBeUndefined();

    // @ts-expect-error a rota não existe no contrato
    await cliente.GET('/rota-que-nao-existe');
  });
});
