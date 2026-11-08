# Oikora One — contexto do projeto para agentes

<!-- Fica no repositório do PRODUTO: quem tem acesso ao produto tem este
contexto. Mudanças por PR, como qualquer código. Com Tracker ligado, o estado
compartilhado das tasks vive nas issues; este arquivo guarda só contexto estável. -->

Project ID: oikora-one
Tracker: github:mauriciotcg3/Oikora-One-Project
Skills: backend, database, frontend
Execution mode: per-spec

Product documentation: docs/product/0001-fundacao-e-pessoas.md (entrada: docs/analise/)
ADRs: docs/adr/ (OIK)
Spec: docs/spec/0001-fundacao-e-pessoas-painel/

Current phase: DEVELOPMENT_IN_PROGRESS
Development authorization: APPROVED (OIK-SPEC-0001 em ondas, 2026-11-08; aprovações anteriores incorporadas ao baseline)
Current owner: Software Engineer

## Convenção Git (opcional)

Branch principal/integração: main protegida por PR (OIK-ADR-0010)
Branch da spec: `feat/spec-0001-fundacao-pessoas` (OIK-ADR-0013)
Padrão de mensagem de commit: Conventional Commits com escopo e onda, ex.: `feat(pessoas): descrição (OIK-SPEC-0001 W3)`

## Pontos quentes

- `package.json` e `pnpm-lock.yaml`
- `compose.yaml`
- `docs/spec/0001-fundacao-e-pessoas-painel/plan.md`

## Comandos do ambiente (CTX-011)

Só comandos que já rodaram com sucesso, com a duração observada.

Pré-requisitos: Node 26.13.0 e pnpm 12.16.1. Instalar dependências: `pnpm install --frozen-lockfile` (~1 min com cache do pnpm).
Teste focado: `<governança>/scripts/run-quiet.sh pnpm --filter web exec vitest run src/features/Identidade` (~20 s)
Suíte do módulo: `<governança>/scripts/run-quiet.sh pnpm test:web` (~50 s)
Pre-Review Gate do front: `<governança>/scripts/run-quiet.sh pnpm gate` (~1,5 min).
Subir tudo (Docker): `docker compose --env-file versoes.env up -d --build --wait web` (~2,5 min do zero).
Pre-Review Gate da API (Postgres no ar): `docker compose --env-file versoes.env run --rm --no-deps -T api composer gate` (~1 min; Pint, PHPStan 8, Pest).
Teste focado da API: `docker compose --env-file versoes.env run --rm --no-deps -T api ./vendor/bin/pest tests/Feature/Identidade` (~15 s).
Teste dos códigos: `docker compose --env-file versoes.env run --rm --no-deps -T api ./vendor/bin/pest tests/Feature/Avisos tests/Feature/Identidade` (~40 s).
Código no dev: o worker `worker-codigos` sobe junto com a API, e os adapters falsos gravam a mensagem em `api/storage/app/private/codigos-dev.log`. Conta fictícia com 2FA: `demo2fa@oikora.test`, senha do seeder. Depois de mudar código do job: `docker compose --env-file versoes.env restart worker-codigos`.
