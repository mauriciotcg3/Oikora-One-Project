# Ambiente de desenvolvimento do web/ (OIK-ADR-0011 §4). Build a partir da raiz do workspace.
FROM node:26.13.0-slim

RUN npm install -g pnpm@12.16.1

WORKDIR /app
COPY package.json pnpm-workspace.yaml pnpm-lock.yaml ./
COPY web/package.json web/package.json
COPY packages/api-contrato/package.json packages/api-contrato/package.json
RUN pnpm install --frozen-lockfile

COPY web web
COPY packages packages

EXPOSE 5173
CMD ["pnpm", "--filter", "web", "exec", "vite", "--host", "0.0.0.0", "--port", "5173", "--strictPort"]
