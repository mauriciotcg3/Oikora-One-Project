# Oikora One — contexto do projeto para agentes

<!-- Fica no repositório do PRODUTO: quem tem acesso ao produto tem este
contexto. Mudanças por PR, como qualquer código. Com Tracker ligado, o estado
compartilhado das tasks vive nas issues; este arquivo guarda só contexto estável. -->

Project ID: oikora-one
Tracker: github:mauriciotcg3/Oikora-One-Project
Skills: backend, database, frontend
Execution mode: per-task

Product documentation: docs/product/0001-fundacao-e-pessoas.md (entrada: docs/analise/)
ADRs: docs/adr/ (OIK)
Spec: docs/spec/0001-fundacao-e-pessoas-painel/

Current phase: DEVELOPMENT_IN_PROGRESS
Development authorization: APPROVED (T-38, recorte do login, 2026-11-02)
Current owner: Software Engineer

## Convenção Git (opcional)

Branch principal/integração: main (trunk-based, OIK-ADR-0010; Fase-1 a aposentar)
Padrão de branch: t-NN-<slug>, curta, a partir da main (OIK-SPEC-0001, U-34)
Padrão de mensagem de commit: Conventional Commits com escopo e task, ex.: `feat(web): descrição (T-NN)`

## Pontos quentes

- `package.json` e `pnpm-lock.yaml`
- `compose.yaml`
- `docs/spec/0001-fundacao-e-pessoas-painel/plan.md`

## Comandos do ambiente (CTX-011)

Só comandos que já rodaram com sucesso, com a duração observada.

Pré-requisitos: Node 26.13.0 e pnpm 12.16.1. Instalar dependências: `pnpm install --frozen-lockfile` (~1 min com cache do pnpm).
Teste focado: `<governança>/scripts/run-quiet.sh pnpm --filter web exec vitest run src/features/Identidade` (~20 s)
Suíte do módulo: `<governança>/scripts/run-quiet.sh pnpm test:web` (~50 s)
Pre-Review Gate (duração aproximada): `<governança>/scripts/run-quiet.sh pnpm gate` (~1,5 min). Só cobre o front; `composer gate` ainda não existe.
