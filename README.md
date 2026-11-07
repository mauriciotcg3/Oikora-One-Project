# Oikora-One-Project

## Rodar com Docker

Precisa só do Docker Desktop aberto; Node e pnpm ficam dentro do container.

```bash
docker compose up --build
```

Abra http://localhost:5173/painel/login. Alterações em `web/src` aparecem sozinhas no navegador.
Depois de mudar dependências, rode o comando de novo; para parar, use `docker compose down`.

A API ainda é simulada: a senha `oikora123` pede o código `123456`, e o código de recuperação é `ABCD-1234`.
