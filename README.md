# Oikora-One-Project

## Rodar com Docker

Precisa só do Docker Desktop aberto; PHP, Composer, Node e pnpm ficam dentro dos containers.

```bash
docker compose --env-file versoes.env up --build
```

Abra http://localhost:5173/painel/login. A primeira subida instala as dependências, roda as migrations e cria uma conta fictícia:

- e-mail `demo@oikora.test` ou celular `(11) 99999-0000`
- senha `oikora123`

Alterações em `web/src` e `api/` aparecem sozinhas. Depois de mudar dependências, rode o comando de novo; para parar, use `docker compose down`.

Ainda não têm backend: confirmação do código de 2FA e "Esqueci a senha" (T-11 e T-12). Para ver essas telas, use a API simulada, sem Docker:

```bash
pnpm dev:painel
```

Nela, a senha `oikora123` pede o código `123456`, e o código de recuperação é `ABCD-1234`.

## Testes da API

```bash
docker compose --env-file versoes.env run --rm --no-deps api composer gate
```

O comando roda Pint, PHPStan e Pest no banco `oikora_teste`, com o papel `oikora_app`. Precisa do Postgres no ar.
