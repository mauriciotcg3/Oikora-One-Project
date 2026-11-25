# Oikora-One-Project

## Documentação

A base compartilhada de produto, arquitetura, spec, módulos e contexto de
desenvolvimento está no [índice de documentação](docs/README.md). Os documentos
específicos do Oikora são versionados neste repositório; a governança `.ai`
mantém somente regras, skills, templates e espelhos reutilizáveis.

## Rodar com Docker

Precisa só do Docker Desktop aberto; PHP, Composer, Node e pnpm ficam dentro dos containers.

```bash
./dev up --build
```

Abra http://localhost:5173/painel/login. A primeira subida instala as dependências, roda as migrations e cria uma conta fictícia:

- e-mail `demo@oikora.test` ou celular `(11) 99999-0000`
- senha `oikora123`

Alterações em `web/src` e `api/` aparecem sozinhas. Depois de mudar dependências, rode o comando de novo; para parar, use `./dev down`.

O `./dev` é um atalho para `docker compose --env-file versoes.env --env-file dev.env`; o `dev.env` define `COMPOSE_FILE=compose.yaml:compose-dev.yaml`, o ajuste de dev: um único `worker-geral` consome `avisos,webhooks,default,pesadas`, e `worker-codigos` segue dedicado (2FA nunca espera). Para testar a separação de produção (três workers), use `./dev --profile producao up -d`. Produção e CI usam só o `versoes.env` (`docker compose --env-file versoes.env ...`), que sobe o `compose.yaml` puro.

O login com confirmação de 2FA e a recuperação de senha usam a API real quando
o ambiente Docker está ativo. Para desenvolver essas telas isoladamente, sem
Docker, use a API simulada:

```bash
pnpm dev:painel
```

Nela, a senha `oikora123` pede o código `123456`, e o código de recuperação é `ABCD-1234`.

## Testes da API

```bash
./dev run --rm --no-deps api composer gate
```

O comando roda Pint, PHPStan e Pest no banco `oikora_teste`, com o papel `oikora_app`. Precisa do Postgres no ar.
