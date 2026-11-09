# Ambiente de Desenvolvimento/Execução

**Última validação:** 2026-11-08
**Sistema testado:** Linux 7.0 x86_64, Docker Compose 2.35.1

## Dependências exigidas (fonte: ADRs)

| Dependência | Versão exigida | ADR de origem |
|---|---|---|
| Docker Engine com Compose v2 | versão atual compatível com Compose Specification | OIK-ADR-0011 |
| PHP | 8.5.12, na imagem da API | OIK-ADR-0001; OIK-ADR-0011 |
| PostgreSQL | 17.11 Alpine | OIK-ADR-0002; OIK-ADR-0011 |
| Caddy | 2.11.9 Alpine | OIK-ADR-0011 |
| Node.js / pnpm | 26.13.0 / 12.16.1, na imagem web | OIK-ADR-0006 |
| MinIO | `RELEASE.2025-10-15T17-29-55Z`, compilado do código-fonte oficial para desenvolvimento local | OIK-SPEC-0001 T-01 |

As versões de imagens e do MinIO ficam centralizadas em `versoes.env`; Node e pnpm também são fixados em `package.json` e `web/Dockerfile`.

## Passos de instalação (resumo executável)

1. Instale Docker Engine e o plugin Docker Compose v2.
2. Na raiz do repositório, execute `./dev up -d --build --wait` (atalho para `docker compose --env-file versoes.env --env-file dev.env ...`).
3. Aguarde `api-preparar` instalar dependências, criar a chave local, migrar e popular o banco; ele deve terminar com código 0.
4. Acesse o painel em `http://localhost:5173/painel/login`, a API via `http://localhost:8080` e o console local do objeto em `http://localhost:9001`.

O `dev.env` define `COMPOSE_FILE=compose.yaml:compose-dev.yaml`: em dev, `worker-geral` consome `avisos,webhooks,default,pesadas` e `worker-pesadas` fica desligado (profile `producao`); `worker-codigos` continua dedicado. O `compose.yaml` sozinho mantém a separação de produção (`worker-codigos`, `worker-geral`, `worker-pesadas`). Produção e CI usam só o `versoes.env` (`docker compose --env-file versoes.env ...`), que sobe o `compose.yaml` puro. Para testar a separação em dev: `./dev --profile producao up -d`.

## Variáveis de ambiente obrigatórias

| Variável | Descrição | Origem |
|---|---|---|
| `PHP_VERSAO` | tag base da imagem PHP | `versoes.env`; OIK-SPEC-0001 T-01 |
| `POSTGRES_VERSAO` | tag base do PostgreSQL | `versoes.env`; OIK-ADR-0002 |
| `CADDY_VERSAO` | tag base do proxy | `versoes.env`; OIK-ADR-0011 |
| `MINIO_VERSAO` | release compilada para o serviço local | `versoes.env`; OIK-SPEC-0001 T-01 |
| `COMPOSE_FILE` | arquivos do Compose (`compose.yaml:compose-dev.yaml` em dev) | `dev.env`; ajuste de dev dos workers |

As credenciais de banco e MinIO presentes no Compose são exclusivamente locais. Segredos reais não devem ser versionados.

## Validação (smoke test)

Execute:

```bash
./dev ps --all
curl -fsS http://localhost:8080/api/v1/saude
curl -fsS http://localhost:9000/minio/health/live
```

O Compose deve listar `postgres` e `minio` saudáveis; `api`, `web`, `caddy`, `scheduler` e os workers de fila em execução (dois em dev, três com `--profile producao` ou em produção); `api-preparar` encerrado com código 0. A saúde da API deve responder HTTP 200 com `vivo: true`, banco `ok`, versão e filas, e o MinIO deve responder HTTP 200.

## Problemas conhecidos por sistema operacional

| SO | Observação |
|---|---|
| Linux | O usuário precisa ter acesso ao socket do Docker. |
| Windows/WSL2 | O web publica explicitamente em `127.0.0.1:5173` para evitar resolução de `localhost` somente por IPv6. |
| Todos | A primeira compilação local do MinIO baixa o código-fonte e dependências Go e pode levar vários minutos. O artefato é para desenvolvimento; produção usa armazenamento S3 conforme OIK-ADR-0011. |

## Changelog

| Data | Mudança | Motivo |
|---|---|---|
| 2026-11-08 | Documentado o ambiente completo com API, frontend, PostgreSQL, MinIO, proxy, scheduler e workers separados. | OIK-SPEC-0001 T-01 |
