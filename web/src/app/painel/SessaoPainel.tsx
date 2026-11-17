import type { QueryClient } from '@tanstack/react-query'
import { useEffect, useRef, useState, type ReactNode } from 'react'
import { DialogoElevacao } from '../../features/Identidade'
import { configurarSessao } from '../../shared/http'

const CANAL_ABAS = 'oikora-painel'
const ROTA_LOGIN = '/painel/login'

interface Props {
  clienteConsulta: QueryClient
  children: ReactNode
}

/** Parte do `GET /me` usada aqui (`contrato-permissoes.md`, bootstrap da sessão). */
interface OrganizacaoDoMe {
  data?: { organizacao_ativa?: { id?: string } | null }
}

/** URL do login com a volta para onde a pessoa estava (OIK-ADR-0006 §10). */
export function urlDoLogin(local: Location): string {
  const volta = local.pathname + local.search
  return volta.startsWith(ROTA_LOGIN) ? ROTA_LOGIN : `${ROTA_LOGIN}?voltar=${encodeURIComponent(volta)}`
}

/**
 * Comportamentos de sessão da entrada `painel`: 401 vai ao login, 403 `elevacao_necessaria` abre o
 * diálogo sobre a tela atual e 409 `organizacao_trocada` recarrega esta aba e avisa as outras.
 */
export function SessaoPainel({ clienteConsulta, children }: Props): ReactNode {
  const [elevando, setElevando] = useState(false)
  const pendentes = useRef<((elevou: boolean) => void)[]>([])

  useEffect(() => {
    const canal = typeof BroadcastChannel === 'undefined' ? null : new BroadcastChannel(CANAL_ABAS)
    if (canal) {
      canal.onmessage = () => {
        clienteConsulta.clear()
        window.location.reload()
      }
    }

    const desligar = configurarSessao({
      organizacaoEsperada: () => clienteConsulta.getQueryData<OrganizacaoDoMe>(['me'])?.data?.organizacao_ativa?.id,
      aoNaoAutenticado: () => {
        clienteConsulta.clear()
        if (!window.location.pathname.startsWith(ROTA_LOGIN)) window.location.assign(urlDoLogin(window.location))
      },
      // Pedidos que chegam com o diálogo aberto esperam a mesma elevação.
      aoElevacaoNecessaria: () =>
        new Promise<boolean>((resolver) => {
          pendentes.current.push(resolver)
          setElevando(true)
        }),
      aoOrganizacaoTrocada: () => {
        canal?.postMessage({ tipo: 'organizacao_trocada' })
        clienteConsulta.clear()
        window.location.reload()
      },
    })

    return () => {
      desligar()
      canal?.close()
    }
  }, [clienteConsulta])

  const concluir = (elevou: boolean): void => {
    setElevando(false)
    const aguardando = pendentes.current
    pendentes.current = []
    aguardando.forEach((resolver) => resolver(elevou))
  }

  return (
    <>
      {children}
      <DialogoElevacao aberto={elevando} aoConcluir={concluir} />
    </>
  )
}
