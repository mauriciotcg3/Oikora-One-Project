# ADR-0015: Registro de tentativas que falham na auditoria

**Status:** Aceito — após confirmação do usuário (2026-11-20)
**Data:** 2026-11-20
**Contexto do projeto:** Oikora One — plataforma da denominação (3 igrejas, ~520 membros) que substitui o inChurch módulo a módulo, com plano de atender outras igrejas.
**Identificação:** Produto `OIK-ADR-0015` · Governança `ADR-0043`
**Fonte da verdade:** `oikora-one:docs/adr/0015-registro-de-tentativas-recusadas-na-auditoria.md`

## Contexto

O FR-037 pede filtro por resultado e destaque de falhas na auditoria, e o NFR-008 pede que falhas mostrem situação, severidade e próximo passo. A OIK-ADR-0005 §2 grava a auditoria na mesma transação da ação: se a ação falha, a transação desfaz e a linha some. Hoje a trilha só contém ações que deram certo (exceto as negações de segurança do §5). Não há dado para filtrar ou destacar falhas. O cenário da OIK-ADR-0014 (uma secretaria não consegue concluir a conexão do WhatsApp às 3h) depende de enxergar a etapa e o código da falha.

## Opções consideradas

- **A. Gravar a tentativa que falhou em transação própria, depois do desfazer** — nova coluna `resultado`; a linha de falha não contém valores.
- **B. Adiar "resultado"** — a tela de Auditoria sai sem filtro e destaque de falhas.
- **C. Obter as falhas dos logs e do OpenTelemetry** — não altera a auditoria, mas cria uma segunda fonte de consulta.

## Comparação

| Pilar | A. Transação própria | B. Adiar | C. Logs/OTel |
|---|---|---|---|
| Desempenho | **Bom** — um `INSERT` extra só no caminho de falha; sucesso não muda. | **Ótimo** — nada a fazer. | **Aceitável** — consulta em ferramenta externa, fora da tela. |
| Simplicidade de manutenção | **Bom** — um catálogo e uma tabela; exige uma exceção documentada à §2. | **Ótimo** — nenhuma mudança. | **Ruim** — duas fontes com retenções e permissões diferentes. |
| Boas práticas | **Bom** — a prestação de contas inclui o que foi tentado; mantém a imutabilidade e a ausência de valores. | **Ruim** — descumpre FR-037 e NFR-008. | **Aceitável** — log técnico sem dado pessoal, mas sem alcance por organização/permissão. |
| Escalabilidade | **Bom** — volume limitado ao catálogo de falhas relevantes; partições mensais existentes. | **Ótimo**. | **Aceitável** — depende do coletor, ainda não implantado. |
| Observabilidade / logs | **Ótimo** — falha, `request_id` e `trace_id` na mesma consulta que o suporte já usa. | **Ruim** — o plantão não vê a falha. | **Bom** — o trace existe, mas não a ponte com o administrador da igreja. |

## Decisão

Adotar a **opção A**, como extensão da OIK-ADR-0005 §2 (as demais regras da 0005 permanecem).

1. A tabela `auditoria` ganha `resultado` (`sucesso` ou `falha`, padrão `sucesso`), `codigo_falha` (código estável do catálogo, só quando `falha`) e `trace_id`.
2. Quando uma ação auditada do §5 termina em erro, a camada de aplicação grava **uma linha de falha em transação própria, depois do desfazer da transação de negócio**. A ação continua falhando e o erro original é o que o cliente recebe.
3. A linha de falha tem o mesmo conteúdo permitido do §2: evento tentado, ator, alvo, igreja, canal, `request_id`, `trace_id`, nomes de campo e metadados catalogados. **Nunca valores, texto livre, mensagens de exceção nem segredos.** `codigo_falha` vem de um catálogo versionado.
4. Se a gravação da linha de falha também falhar, ela não troca o erro original: registra no log técnico e dispara o alerta abaixo.
5. A leitura segue a OIK-ADR-0004: `auditoria.ver`, alcance e RLS. A consulta passa a aceitar o filtro `resultado`.
6. Entram no catálogo as falhas de regra de negócio e de execução das ações auditadas do §5 (informado pelo usuário em 2026-11-20). Ficam de fora os erros de validação 422 comuns e o `authz.denied` comum, de alto volume, que seguem só no log técnico. A T-54 define os códigos.
7. As linhas de falha seguem a mesma retenção da auditoria, cinco anos, sem regra própria (informado pelo usuário em 2026-11-20).

## Justificativa

É a única opção que entrega o FR-037 completo na tela que o administrador já usa, sem segunda fonte. O custo é uma exceção estreita à §2: a imutabilidade, a ausência de valores e o isolamento por organização continuam valendo, e a gravação extra só ocorre quando a ação já falhou. A volta ao estado anterior da transação de negócio fica intacta.

## Consequências

**Positivas:**
- O administrador e o plantão filtram por resultado e veem falha, código legível e correlação.
- A tela de Auditoria cumpre o NFR-008 sem consultar logs.

**Negativas / trade-offs aceitos:**
- A trilha deixa de conter só fatos consumados; o texto de produto e a documentação do módulo precisam dizer isso.
- Falha de gravação da falha não impede o erro de ser devolvido; essa linha pode faltar, e por isso há alerta.
- Migration nova na tabela particionada, com coluna anulável ou com padrão, sem reescrita das partições (a T-54 usa `database-change`).

## Observabilidade — o que precisa ser instrumentado

- **Eventos a logar:** falha ao gravar a linha de falha (evento, código, `request_id`, `trace_id`, sem dados pessoais); consulta de auditoria com `resultado=falha`.
- **Métricas a expor:** linhas de falha por evento e código; falhas de gravação da falha; percentual de falhas sem `trace_id`.
- **Alertas a configurar:** qualquer falha ao gravar a linha de falha; falha de evento crítico sem `request_id`/`trace_id`. Limiares na OIK-ADR-0010.
- **Cenário de incidente validado:** às 3h, uma secretaria não conclui a conexão do WhatsApp. O plantão filtra a auditoria por organização, período e `resultado=falha`, lê o código legível, abre os detalhes técnicos e usa o `trace_id` para achar a chamada ao provedor, sem token nem payload.

## Lacunas conhecidas

- Lista de códigos de falha: definida na T-54, dentro do critério da decisão 6.
- Os limiares dos alertas dependem da OIK-ADR-0010 e do coletor.
