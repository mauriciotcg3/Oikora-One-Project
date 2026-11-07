import react from '@vitejs/plugin-react'
import type { Connect, Plugin } from 'vite'
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

export default defineConfig({
  plugins: [react(), entradasSpa()],
  appType: 'mpa',
  build: {
    rollupOptions: { input: { painel: 'painel.html' } },
  },
  test: {
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
    css: false,
    restoreMocks: true,
  },
})
