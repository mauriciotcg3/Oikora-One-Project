import { setupServer } from 'msw/node'

/** API simulada dos testes; pedido sem handler falha o teste (equivalente ao preventStrayRequests, OIK-ADR-0010 §2). */
export const servidor = setupServer()
