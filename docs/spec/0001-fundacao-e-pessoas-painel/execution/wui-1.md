# Checkpoint — OIK-SPEC-0001 WUI-1 (UI Standard, frontend)

**Branch:** `feat/t-50` (sem commit; arquivos no working tree)
**Tasks:** T-49 e T-50 já codificadas; T-51, T-52 e T-53 codificadas em 2026-11-20
**Atualizado em:** 2026-11-20

## Progresso

- T-51 feita: shell do painel, navegação por capacidades do `/me`, rota Auditoria com acesso negado, densidade dos botões escopada ao login (`size="large"`).
- T-52 feita: `JornadaGuiada` e `ConfirmacaoIrreversivel`; `etapaComErro422` em `shared/forms`.
- T-53 feita: `FiltrosNaUrl`, `TabelaDeRegistros` (DataGrid MIT em chunk `lazy`) e `DetalheEmCamadas`.

## Decisões e desvios

- DataGrid: `@mui/x-data-grid` 9.18.0 adicionado com aprovação do usuário (ADR-0006 §6); plano §14 atualizado. `package.json` e `pnpm-lock.yaml` mudaram; a imagem `web` foi reconstruída (`./dev build web`). Paginação no servidor feita na `TabelaDeRegistros` + `usePaginacaoDaUrl` (a T-55 só consome).
- `JornadaGuiada` e os campos das etapas: as features montam os campos com `Controller` e `inputRef={field.ref}` (não espalhar `field` inteiro no `TextField`, o `ref` iria para a raiz e o foco falha).
- Claim das issues #72, #73 e #74 não feito (escrita no tracker exige OK).

## Gate de saída

- `./dev run --rm --no-deps -T web pnpm --filter web gate`: typecheck, lint, 53 testes (11 arquivos), build, size-limit 276,16 KB de 350 KB; DataGrid em `GradeDeRegistros-*.js` (162,44 KB gzip) separado.
- `vite.config.ts`: `testTimeout` 15 s; os testes de login (5 s padrão) estouravam quando a suíte inteira roda em paralelo.

## Próximo passo

- Revisão consolidada da WUI-1; depois WUI-2 (T-54 a T-57), que depende de T-22 e T-32.

## Galeria de desenvolvimento

- `/painel/galeria` (`web/src/app/painel/galeria/Galeria.tsx`): componentes reais com dados de exemplo e "API" em memória; só no `pnpm dev` (`import.meta.env.DEV`; o build de produção não a inclui: o hash do `painel-*.js` não mudou). Teste de fumaça em `__tests__/galeria.test.tsx`. Gate: 62 testes, 277,81 KB.
