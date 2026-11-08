# ADR-0013: Execução da spec em ondas

**Status:** Aceito
**Data:** 2026-11-08
**Contexto do projeto:** Oikora One, monólito modular Laravel com painel React, desenvolvido por equipe pequena com apoio de IA.
**Identificação:** Produto `OIK-ADR-0013` · Governança `ADR-0035`
**Fonte da verdade:** `oikora-one:docs/adr/0013-execucao-da-spec-em-ondas.md`

## Contexto
A OIK-SPEC-0001 foi publicada como 48 issues, mas a execução permaneceu em modo `per-task`, com uma sessão e uma branch por tarefa. A repetição de carregamento de contexto, gates e handoffs reduziu a vazão, enquanto branches concluídas ficaram fora da `main` e o tracker divergiu do código. A spec está aprovada, possui Context Packs e domínio suficientemente conhecido para execução consolidada. Esta decisão substitui, para specs explicitamente configuradas como `per-spec`, a regra de branch por task da OIK-ADR-0010 §3.

## Opções consideradas
- **Execução por task** — manter uma sessão, branch, gates e revisão por issue.
- **Execução por spec em ondas** — uma branch por spec, tasks agrupadas por dependências, checkpoints por onda e revisão consolidada.
- **Modelo híbrido** — executar a sessão por onda, mas manter branches e revisões separadas por task.

## Comparação

| Pilar | Execução por task | Execução por spec em ondas | Modelo híbrido |
|---|---|---|---|
| Desempenho | Ruim: repete contexto, setup e gates por issue. | Ótimo: amortiza contexto e gates por onda. | Aceitável: reduz parte do contexto, mas mantém custo de integração. |
| Simplicidade de manutenção | Bom: diffs pequenos e isolados. | Bom: checkpoint por task preserva rastreabilidade, com diff consolidado maior. | Ruim: dois modelos de unidade de trabalho e estado. |
| Boas práticas | Ótimo: trunk-based clássico com branches curtas. | Bom: continua por PR e CI, com branch de spec temporária. | Aceitável: aumenta reconciliações e conflitos sem benefício claro. |
| Escalabilidade | Aceitável: escala por pessoas, mas cobra alto custo por agente. | Ótimo: o DAG permite ondas grandes e paralelismo seguro. | Ruim: cresce o número de branches e merges intermediários. |
| Observabilidade / logs | Bom: cada issue tem histórico próprio. | Ótimo: issue, `execution/wN.md` e gates registram progresso e falhas. | Ruim: estado fica dividido entre onda, task e branches. |

## Decisão
Adotar `Execution mode: per-spec` para a OIK-SPEC-0001. A unidade de sessão passa a ser uma onda inteira, calculada por dependências formais e conflitos de arquivos. O trabalho usa uma branch por spec, `feat/spec-0001-fundacao-pessoas`, checkpoints em `execution/wN.md`, status por task nas issues e gates/revisão consolidados. A `main` continua protegida por PR, CI verde e aprovação humana conforme a OIK-ADR-0010.

## Justificativa
A opção por ondas reduz o custo repetido de contexto e validação, mantém o desenho modular já aprovado e torna as dependências verificáveis pelo tracker. O checkpoint por task reduz o risco do diff consolidado e permite retomada sem reler toda a spec. Para a equipe pequena e o prazo de entrega, a maior vazão supera o trade-off de uma branch mais longa, sem remover CI, revisão ou rastreabilidade.

## Consequências

**Positivas:**
- Menos sessões, handoffs e execuções redundantes de gates.
- Dependências, conflitos e progresso ficam rastreáveis por onda e por issue.

**Negativas / trade-offs aceitos:**
- A branch da spec e o PR consolidado terão diffs maiores.
- Uma falha de integração pode afetar várias tasks da onda, exigindo checkpoints frequentes.

## Observabilidade — o que precisa ser instrumentado
- **Eventos a logar:** início e fechamento de onda, task codificada, gate iniciado/finalizado, bloqueio e retomada.
- **Métricas a expor:** tempo por onda, tasks concluídas por onda, duração e taxa de falha dos gates, tempo em bloqueio.
- **Alertas a configurar:** onda sem checkpoint após trabalho iniciado e gate consolidado falhando repetidamente.
- **Cenário de incidente validado:** se um gate falhar fora do horário, o responsável consulta `execution/wN.md`, a issue da última task e a saída do gate para identificar a mudança e retomar sem escalar para quem escreveu o código.

## Lacunas conhecidas
As primeiras ondas servirão para calibrar seu tamanho; a dependência formal e o conflito de arquivos prevalecem sobre metas fixas de quantidade de tasks.
