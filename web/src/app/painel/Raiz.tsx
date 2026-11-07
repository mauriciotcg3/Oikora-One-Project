import type { QueryClient } from '@tanstack/react-query'
import { RouterProvider } from '@tanstack/react-router'
import type { ReactNode } from 'react'
import type { InstanciaI18n } from '../../shared/i18n'
import { ProvedoresBase } from '../../shared/ui'
import type { criarRoteador } from './roteador'

// Raiz da entrada `painel`. Error Boundary com trace_id, OTel e sessão entram com a T-37.

interface Props {
  roteador: ReturnType<typeof criarRoteador>
  i18n: InstanciaI18n
  clienteConsulta: QueryClient
}

export function Raiz({ roteador, i18n, clienteConsulta }: Props): ReactNode {
  return (
    <ProvedoresBase i18n={i18n} clienteConsulta={clienteConsulta}>
      <RouterProvider router={roteador} />
    </ProvedoresBase>
  )
}
