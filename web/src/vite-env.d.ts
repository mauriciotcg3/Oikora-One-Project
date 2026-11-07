/// <reference types="vite/client" />

interface ImportMetaEnv {
  /** Versão do build enviada em `X-Cliente` (OIK-ADR-0006 §14). */
  readonly VITE_VERSAO_BUILD?: string
  /** `false` desliga a API simulada (MSW) no `pnpm dev`. */
  readonly VITE_API_SIMULADA?: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
