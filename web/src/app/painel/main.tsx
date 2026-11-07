import '@fontsource/poppins/400.css'
import '@fontsource/poppins/500.css'
import '@fontsource/poppins/600.css'
import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { criarClienteConsulta } from './clienteConsulta'
import { i18nPainel } from './i18n'
import { Raiz } from './Raiz'
import { criarRoteador } from './roteador'

async function iniciar(): Promise<void> {
  if (import.meta.env.DEV && import.meta.env.VITE_API_SIMULADA !== 'false') {
    const { iniciarApiSimulada } = await import('./simulacao')
    await iniciarApiSimulada()
  }
  const elemento = document.getElementById('raiz')
  if (!elemento) throw new Error('painel.html sem o elemento #raiz')
  createRoot(elemento).render(
    <StrictMode>
      <Raiz roteador={criarRoteador()} i18n={i18nPainel} clienteConsulta={criarClienteConsulta()} />
    </StrictMode>,
  )
}

void iniciar()
