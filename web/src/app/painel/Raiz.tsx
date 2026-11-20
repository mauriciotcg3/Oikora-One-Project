import type { QueryClient } from '@tanstack/react-query'
import { RouterProvider } from '@tanstack/react-router'
import type { ReactNode } from 'react'
import type { InstanciaI18n } from '../../shared/i18n'
import { ProvedoresBase } from '../../shared/ui'
import { FronteiraDeErro } from './FronteiraDeErro'
import type { criarRoteador } from './roteador'
import { SessaoPainel } from './SessaoPainel'

// Raiz da entrada `painel`: tema, i18n e consultas; Error Boundary com `trace_id`; sessão (401,
// elevação e troca de organização) ligada ao cliente HTTP (OIK-ADR-0006 §10, §14).

interface Props {
  roteador: ReturnType<typeof criarRoteador>
  i18n: InstanciaI18n
  clienteConsulta: QueryClient
}

export function Raiz({ roteador, i18n, clienteConsulta }: Props): ReactNode {
  return (
    <ProvedoresBase i18n={i18n} clienteConsulta={clienteConsulta}>
      <FronteiraDeErro>
        <SessaoPainel clienteConsulta={clienteConsulta}>
          <RouterProvider router={roteador} />
        </SessaoPainel>
      </FronteiraDeErro>
    </ProvedoresBase>
  )
}
