# Checkpoint — OIK-SPEC-0001 WUI-1 (UI Standard, frontend)

**Branch:** `feat/t-50` (sem commit; arquivos no working tree)
**Tasks:** T-49 e T-50 já codificadas; T-51, T-52 e T-53 codificadas em 2026-11-20
**Atualizado em:** 2026-11-20

## Progresso

- T-51 feita: shell do painel, navegação por capacidades do `/me`, rota Auditoria com acesso negado, densidade dos botões escopada ao login (`size="large"`).
- T-52 feita: `JornadaGuiada` e `ConfirmacaoIrreversivel`; `etapaComErro422` em `shared/forms`.
- T-53 feita com desvio: `FiltrosNaUrl`, `TabelaDeRegistros` (MUI `Table`) e `DetalheEmCamadas`.

## Decisões e desvios

- DataGrid não usado: plano §14 diz "sem dependência nova" e o pacote não está instalado. Decisão do usuário: adicionar `@mui/x-data-grid` ou manter `Table`.
- `JornadaGuiada` e os campos das etapas: as features montam os campos com `Controller` e `inputRef={field.ref}` (não espalhar `field` inteiro no `TextField`, o `ref` iria para a raiz e o foco falha).
- Claim das issues #72, #73 e #74 não feito (escrita no tracker exige OK).

## Gate de saída

- `./dev run --rm --no-deps -T web pnpm --filter web gate`: typecheck, lint, 53 testes (11 arquivos), build, size-limit 275,13 KB de 350 KB.
- Testes de login e do shell são sensíveis a carga quando a suíte roda inteira (timeouts de 1–5 s em `findBy`); passam isolados.

## Próximo passo

- Revisão consolidada da WUI-1; depois WUI-2 (T-54 a T-57), que depende de T-22 e T-32.
