# ADR-0012: Solicitação e concessão temporária de permissões

**Status:** Aceito — confirmado pelo responsável do produto em 2026-11-07
**Data:** 2026-11-07
**Contexto do projeto:** Oikora One, autorização multi-organização com papéis, perfis e alcance por escopo
**Identificação:** Produto `OIK-ADR-0012` · Governança `não atribuída durante a transição dos ADRs 0005–0014`
**Fonte da verdade:** `oikora-one:docs/adr/0012-solicitacao-e-concessao-de-permissoes.md`

## Contexto

A ADR-0004 define catálogo em código, papéis, atribuições por escopo,
anti-escalada e alcance calculado por requisição. A descoberta do esquema de
permissões do OmniPlus confirmou a utilidade de separar papel e perfil, mas o
produto também precisa atender necessidades individuais e temporárias sem
ampliar o acesso de todas as pessoas ligadas ao mesmo perfil.

Conceder permissões diretamente ao acesso sem processo formal criaria
privilégios difíceis de descobrir, revisar e revogar. Alterar o perfil inteiro
para uma exceção individual produziria o mesmo excesso de acesso em escala
maior. A decisão precisa, portanto, preservar o alcance e a anti-escalada da
ADR-0004 e tornar cada exceção temporária, explicável e auditável.

## Opções consideradas

- **Concessão temporária mediante solicitação e aprovação** — registrar o pedido, a decisão e uma concessão explícita com vencimento.
- **Permissão direta permanente no acesso** — permitir que o administrador mantenha exceções individuais sem fluxo ou vencimento.
- **Alterar ou duplicar o perfil** — representar toda exceção por um perfil novo ou pela ampliação do perfil compartilhado.

## Comparação

| Pilar | Concessão temporária aprovada | Permissão direta permanente | Alterar ou duplicar perfil |
|---|---|---|---|
| Desempenho | Bom — soma concessões válidas ao alcance da requisição, com consulta indexável por organização, acesso e vencimento | Ótimo — leitura simples, mas sem expiração segura | Aceitável — reutiliza atribuições, mas multiplica perfis e relações |
| Simplicidade de manutenção | Bom — máquina de estados explícita e regra única de validade | Ruim — exceções invisíveis e revogação manual | Ruim — proliferação de perfis quase iguais e diffs difíceis de explicar |
| Boas práticas | Ótimo — menor privilégio, expiração, segregação de funções, anti-escalada e sem autoaprovação | Ruim — privilégio duradouro sem revisão formal | Aceitável — usa o modelo existente, mas mistura regra coletiva e exceção individual |
| Escalabilidade | Bom — pedidos e concessões crescem linearmente e podem ser particionados ou arquivados no futuro | Aceitável — poucas linhas, mas revisão operacional degrada com o volume | Ruim — número de perfis cresce com combinações individuais |
| Observabilidade / logs | Ótimo — cada transição tem ator, motivo, versão, escopo e evento | Ruim — não explica por que nem até quando o acesso existe | Aceitável — auditoria mostra mudança do perfil, mas não a necessidade individual com clareza |

## Decisão

1. Não haverá edição administrativa livre de permissões diretas no acesso.
2. O usuário poderá solicitar uma ou mais permissões do catálogo,
   informando escopo, justificativa e duração de até 72 horas.
3. Toda concessão será temporária e explícita, separada das atribuições de
   perfil, com início, vencimento, solicitante, aprovador e justificativa.
4. No MVP, somente um administrador da organização aprova. Ele precisa de
   `permissoes.solicitacoes.aprovar` e de todas as permissões solicitadas em
   alcance igual ou superior. Autoaprovação é negada.
5. Permissões sensíveis podem ser solicitadas, mas cada categoria sensível
   deve aparecer explicitamente na revisão e na auditoria.
6. `papeis.gerir`, `acessos.gerir`, `perfis.gerir` e permissões de exportação
   não podem ser obtidas por esse fluxo na primeira versão.
7. A concessão entra no mesmo cálculo de alcance da ADR-0004 e nunca ultrapassa
   a organização ativa.
8. Solicitação, aprovação, recusa, expiração e revogação são auditadas. A
   justificativa de recusa é obrigatória.
9. A aprovação é idempotente e protegida contra concorrência. O aprovador não
   altera silenciosamente o pedido: para reduzir permissão, escopo ou duração,
   recusa e o solicitante cria uma nova versão.
10. Ao vencer ou ser revogada, a concessão deixa de valer na requisição
    seguinte, sem depender de cache persistente.
11. A persistência usa `solicitacoes_permissao`, `solicitacao_permissoes`,
    `concessoes_permissao` e `concessao_permissoes`, todas com
    `organizacao_id`, FKs compostas e RLS conforme a ADR-0002.

## Justificativa

A concessão temporária aprovada tem custo de leitura e de implementação maior
que uma coluna ou relação direta, mas preserva menor privilégio e torna o ciclo
de vida determinístico. Ela evita a proliferação de perfis, escala por registros
independentes e fornece evidência suficiente para reconstruir quem possuía qual
alcance em uma data. O limite de 72 horas reduz o risco de privilégios
esquecidos; a aprovação por administrador com anti-escalada evita delegação
acima do próprio alcance; e a revisão explícita de categorias sensíveis preserva
o fluxo solicitado sem esconder seu risco.

## Consequências

**Positivas:**
- o alcance efetivo passa a explicar tanto atribuições de perfil quanto concessões temporárias válidas;
- toda exceção tem solicitante, aprovador, justificativa, escopo e vencimento;
- revogação e expiração refletem na requisição seguinte;
- permissões sensíveis permanecem visíveis como categorias na decisão e na auditoria.

**Negativas / trade-offs aceitos:**
- o módulo Autorização ganha uma máquina de estados e quatro estruturas de persistência;
- o cálculo de alcance precisa considerar concessões válidas além das atribuições;
- backend e frontend precisam de caixas de solicitações próprias e aprováveis;
- aprovação concorrente e expiração exigem testes e instrumentação específicos.

## Observabilidade — o que precisa ser instrumentado

- **Eventos a logar:** `authz.permission_requested`, `authz.permission_request_approved`, `authz.permission_request_rejected`, `authz.permission_grant_expired`, `authz.permission_grant_revoked` e `authz.escalation_blocked`, sempre sem valores de campos sensíveis.
- **Métricas a expor:** solicitações por estado, tempo entre pedido e decisão, conflitos de decisão, bloqueios por anti-escalada e concessões vencidas ainda não processadas para efeitos secundários.
- **Alertas a configurar:** crescimento sustentado de conflitos ou erros na decisão e concessões vencidas que continuem aparecendo como efetivas.
- **Cenário de incidente validado:** às 3h, se uma pessoa ainda acessar dados de saúde depois do vencimento, o plantão localiza a concessão pelo `request_id`, confirma `expira_em`, compara o evento de expiração e o resultado do cálculo de alcance e diagnostica se houve falha de relógio, consulta ou cache sem escalar para o desenvolvedor.

## Lacunas conhecidas

O ID do espelho na governança não foi atribuído porque os ADRs 0005–0014
permanecem no modelo de transição e a instalação central da governança não está
disponível nesta máquina. A fonte de verdade do produto está aceita e não
depende desse espelho.
