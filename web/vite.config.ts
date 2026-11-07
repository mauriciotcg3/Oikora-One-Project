import react from '@vitejs/plugin-react'
import { loadEnv, type Connect, type Plugin } from 'vite'
import { defineConfig } from 'vitest/config'

// Cada entrada é uma SPA (OIK-ADR-0006 §1): /painel e /painel/* servem painel.html.
const reescreverEntradas: Connect.NextHandleFunction = (req, _res, next) => {
  if (req.url && /^\/painel(\/[^.?]*)?(\?.*)?$/.test(req.url)) req.url = '/painel.html'
  next()
}

function entradasSpa(): Plugin {
  return {
      name: 'oikora-entradas-spa',
      configureServer: (server) => void server.middlewares.use(reescreverEntradas),
      configurePreviewServer: (server) => void server.middlewares.use(reescreverEntradas),
  }
}

export default defineConfig(({ mode }) => {
  const ambiente = loadEnv(mode, '.', '')
  const api = ambiente.API_PROXY

  return {
  plugins: [react(), entradasSpa()],
  appType: 'mpa',
  server: {
    // Mesma origem para front e API: a sessão vem em cookie (OIK-ADR-0003 §9).
    proxy: api ? { '/api': api, '/sanctum': api } : undefined,
    // No Docker com pasta do Windows, o container não recebe os eventos de arquivo alterado.
    watch: ambiente.OBSERVAR_POR_POLLING === 'true' ? { usePolling: true, interval: 300 } : undefined,
  },
  build: {
    rollupOptions: { input: { painel: 'painel.html' } },
  },
  test: {
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
    css: false,
    restoreMocks: true,
  },
  }
})
