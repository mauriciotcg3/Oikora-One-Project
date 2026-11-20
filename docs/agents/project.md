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
- `compose-dev.yaml`, `dev.env` e `dev`
- `docs/spec/0001-fundacao-e-pessoas-painel/plan.md`

## Comandos do ambiente (CTX-011)

Só comandos que já rodaram com sucesso, com a duração observada.

Pré-requisitos: Node 26.13.0 e pnpm 12.16.1. Instalar dependências: `pnpm install --frozen-lockfile` (~1 min com cache do pnpm).
Teste focado: `<governança>/scripts/run-quiet.sh pnpm --filter web exec vitest run src/features/Identidade` (~20 s)
Suíte do módulo: `<governança>/scripts/run-quiet.sh pnpm test:web` (~50 s)
Pre-Review Gate do front: `<governança>/scripts/run-quiet.sh pnpm gate` (~1,5 min; inclui `size-limit`, painel ≤ 350 KB gzip).
Subir tudo (Docker): `./dev up -d --build --wait` (~5 min na primeira compilação do MinIO; ~1 min com cache).
Estado dos serviços: `./dev ps --all` (esperado em dev, via `./dev`: nove serviços — sem `worker-pesadas`, cuja fila `pesadas` o `worker-geral` assume; `api-preparar` encerrado com código 0). Produção/CI (só `versoes.env`, `compose.yaml` puro): dez serviços, com `worker-codigos`, `worker-geral` e `worker-pesadas` separados (U-30).
Smoke da API: `curl -fsS http://localhost:8080/api/v1/saude` (<1 s). Smoke do MinIO: `curl -fsS http://localhost:9000/minio/health/live` (<1 s).
Pre-Review Gate da API (Postgres no ar): `./dev exec -T -e COMPOSER_PROCESS_TIMEOUT=0 api composer gate` (~7 min; Pint, PHPStan 8, Pest com 391 testes, Deptrac). Sem `COMPOSER_PROCESS_TIMEOUT=0`, o Pest passa de 300 s desde a W5 e o Composer o interrompe.
Deptrac sozinho: `./dev exec -T api ./vendor/bin/deptrac analyse --no-progress` (<10 s).
Testes focados da W2: `./dev exec -T api ./vendor/bin/pest tests/Feature/Observabilidade tests/LogSeguro tests/Feature/Autorizacao tests/Autorizacao tests/Feature/Pessoas tests/Feature/Avisos tests/Arquitetura` (~60 s).
Migrar o banco de dev depois de migration nova: `./dev exec -T api php artisan migrate --database=pgsql_dono --force` (<5 s).
Teste focado da API: `./dev run --rm --no-deps -T api ./vendor/bin/pest tests/Feature/Identidade` (~15 s).
Teste dos códigos: `./dev run --rm --no-deps -T api ./vendor/bin/pest tests/Feature/Avisos tests/Feature/Identidade` (~40 s).
Testes focados da W1: `./dev exec -T api ./vendor/bin/pest tests/Unit/Autorizacao tests/Feature/Autorizacao tests/Feature/Auditoria tests/Rls/AuditoriaImutavelTest.php tests/Arquitetura/DeclaracoesDeSegurancaTest.php` (~5 s).
Gate do frontend na imagem: `./dev run --rm --no-deps -T web pnpm --filter web gate` (~25 s; 10 testes e build).
Testes focados da W3: `./dev exec -T api ./vendor/bin/pest tests/Feature/Identidade/LoginTest.php tests/Rls/ContasRlsTest.php tests/Feature/Pessoas tests/Contrato` (~60 s).
Testes focados da W4: `./dev exec -T api ./vendor/bin/pest tests/Feature/Avisos tests/Feature/Identidade/OrganizacaoAtivaTest.php tests/Feature/Identidade/ElevacaoPainelTest.php tests/Feature/Pessoas/CadastroRapidoTest.php tests/Feature/Pessoas/DuplicidadeTest.php tests/Feature/Autorizacao tests/Rls/ConcessoesPermissaoRlsTest.php tests/Arquitetura` (~90 s). Front: `pnpm --filter web gate` no host (~1 min; 23 testes, size-limit).
Testes focados da W5: `./dev exec -T api ./vendor/bin/pest tests/Feature/Avisos tests/Feature/Organizacao tests/Feature/Pessoas/FichaTest.php tests/Feature/Pessoas/VinculoSituacaoTest.php tests/Unit/Pessoas tests/Feature/Auditoria` (~2 min). Arquivos privados em dev e testes: disco `arquivos` local servido pelo Laravel com URL assinada (`/api/v1/arquivos/...`); nos testes, apontar `filesystems.disks.arquivos.root` para uma pasta de teste e `Storage::forgetDisk('arquivos')` (o `Storage::fake` troca a URL assinada por uma falsa). Canais reais de aviso: `OIKORA_AVISOS_PROVEDORES=real` com `POSTMARK_REMETENTE_AVISOS`.
Suíte inteira da API: `./dev exec -T api ./vendor/bin/pest` (~4 min, 300+ testes). PHPStan com mensagens inteiras: `./dev exec -T api ./vendor/bin/phpstan analyse --no-progress --memory-limit=1G --error-format=raw` (~1 min).
Provedores reais de código (T-11): `OIKORA_CODIGOS_PROVEDORES=real` com `POSTMARK_SERVER_TOKEN`, `POSTMARK_REMETENTE`, `META_WHATSAPP_TOKEN`, `META_WHATSAPP_NUMERO_ID` e `TWILIO_CONTA_SID`, `TWILIO_TOKEN`, `TWILIO_SERVICO_SID` (ou `TWILIO_REMETENTE`). Sem a variável, dev e testes ficam nos falsos.
Login pela entrada `painel` (T-16): o corpo do `POST /api/v1/auth/login` leva `entrada: painel`, que já abre a elevação; sem ela, as rotas `/painel/*` respondem 403 `elevacao_necessaria`. Nos testes, `entrarNoPainel()` já faz isso.
Contrato depois de mudar rota ou resposta (T-05): `./dev exec -T api php artisan scramble:export` (~5 s; grava `api/openapi.json`) e `pnpm --filter @oikora/api-contrato gerar` (<1 s; grava `src/esquema.ts`). Versionar os dois. Teste do contrato: `./dev exec -T api ./vendor/bin/pest tests/Contrato` (~10 s). Gate do pacote: `pnpm --filter @oikora/api-contrato gate` (~5 s; regera, compara, `tsc` e Vitest).
Código no dev: o worker `worker-codigos` sobe junto com a API, e os adapters falsos gravam a mensagem em `api/storage/app/private/codigos-dev.log`. Conta fictícia com 2FA: `demo2fa@oikora.test`, senha do seeder. Depois de mudar código do job: `./dev restart worker-codigos`.
