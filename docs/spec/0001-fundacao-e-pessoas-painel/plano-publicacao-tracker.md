# Plano de publicação das tasks de permissões no tracker

**Preparado em:** 2026-11-07
**Escopo:** T-47 e T-48 da OIK-SPEC-0001
**Estado:** reconciliação Git concluída; bloqueado para escrita até restaurar o acesso à governança central e executar a prévia do publicador.

## Reconciliação concluída

- O identificador canônico do projeto é `oikora-one`.
- O tracker canônico está ligado em `github:mauriciotcg3/Oikora-One-Project`.
- A branch `t-13-permissoes-ondas` foi reconciliada por rebase sobre `origin/main`, preservando o trabalho local e o trabalho do Mauricio.
- O script esperado em `/home/thiagonascimento/Desktop/pessoal/.ai/scripts/issue.py` não está disponível nesta máquina.

Nenhuma issue deve ser criada enquanto o publicador da governança central não estiver disponível para validar o lote e apresentar a prévia sem `--confirm`.

## Lote proposto

| Ordem | Task | Título da issue | Dependências | Resultado esperado |
|---|---|---|---|---|
| 1 | T-47 | Solicitações e concessões temporárias de permissão (API) | T-07, T-13, T-15 | Issue `fw:tarefa`/`fw:pronta`, bloqueada pelas dependências ainda abertas |
| 2 | T-48 | Solicitação e aprovação temporária de permissões (frontend) | T-37, T-40, T-47 | Issue `fw:tarefa`/`fw:pronta`, com dependência explícita da T-47 |

O publicador deve ler o arquivo completo e permanecer idempotente: se as tasks anteriores ainda não estiverem no tracker, o plano pode abranger todas as tasks pendentes da spec, mas T-47 e T-48 precisam manter estes IDs, dependências e arquivos de saída.

## Sequência segura de publicação

1. **Concluído:** reconciliar a branch com `origin/main` e registrar `github:mauriciotcg3/Oikora-One-Project` no `project.md` canônico.
2. Restaurar/instalar a governança no caminho registrado e confirmar que `fw_project.py` e `issue.py` existem.
3. Se o tracker ficar `off`, encerrar o plano sem publicação; `tasks.md` continua sendo a fonte de estado.
4. Se o tracker ficar ligado, executar localmente `issue.py check-tasks docs/spec/0001-fundacao-e-pessoas-painel/tasks.md` e corrigir qualquer colisão de dependências/arquivos.
5. Executar `issue.py status` para confirmar repositório e estado atual; o comando deve rodar sozinho.
6. Se as labels ainda não existirem, executar `issue.py labels-setup` sem `--confirm`, apresentar o plano e obter aprovação explícita antes de repetir com `--confirm`.
7. Executar `issue.py publish --tasks docs/spec/0001-fundacao-e-pessoas-painel/tasks.md` sem `--confirm`; revisar quantidade de issues, títulos, labels, dependências e arquivos.
8. Apresentar esse plano final ao usuário. Somente após aprovação explícita, repetir exatamente com `--confirm`.
9. Revisar o diff gerado em `tasks.md`, que deve acrescentar `- **Issue:** #N` sem alterar IDs, objetivos ou dependências.
10. Registrar no handoff as URLs publicadas e a primeira task desbloqueada; não iniciar T-47 enquanto T-07/T-13/T-15 não estiverem concluídas.

## Gate de aprovação

A publicação no GitHub é escrita externa. O plano sem `--confirm` não autoriza a gravação. O `--confirm` só pode ser usado depois de o usuário aprovar o plano exibido pelo script, em uma segunda etapa explícita.
