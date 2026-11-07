import { getResponse } from 'msw'
import { setupWorker } from 'msw/browser'
import { carregarApiSimuladaIdentidade } from '../../features/Identidade'

/** Liga a API simulada (MSW) no navegador; só no `pnpm dev`, enquanto o backend não existe. */
export async function iniciarApiSimulada(): Promise<void> {
  const handlers = await carregarApiSimuladaIdentidade()
  try {
    await setupWorker(...handlers).start({ onUnhandledFrame: 'bypass', quiet: true })
  } catch (erro) {
    // Navegador sem Service Worker (ex.: aba privada, navegador embutido): intercepta o fetch da página.
    console.warn('[api simulada] Service Worker indisponível; usando interceptação do fetch.', erro)
    const fetchOriginal = window.fetch.bind(window)
    window.fetch = async (entrada, opcoes) => {
      const pedido = new Request(entrada, opcoes)
      return (await getResponse(handlers, pedido.clone())) ?? fetchOriginal(pedido)
    }
  }
}
