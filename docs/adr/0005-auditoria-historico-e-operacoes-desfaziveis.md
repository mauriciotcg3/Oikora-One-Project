# ADR-0005: Auditoria, histórico e operações desfazíveis (LGPD) (módulo próprio de auditoria + snapshot para desfazer)

**Status:** Aceito — após confirmação do usuário (2026-10-19)
**Data:** 2026-10-19
**Contexto do projeto:** Oikora One — plataforma da denominação (3 igrejas, ~520 membros) que substitui o inChurch módulo a módulo, com plano de atender outras igrejas.
**Identificação:** Produto `OIK-ADR-0005` · Governança `ADR-0027`
**Fonte da verdade:** `oikora-one:docs/adr/0005-auditoria-historico-e-operacoes-desfaziveis.md`

## Contexto
O OIK-ADR-0001 fixou Laravel só como API `/api/v1`, com uma Action por caso de uso. O OIK-ADR-0002 pôs `organizacao_id` e RLS forçada em toda tabela de domínio, criou um papel de plataforma com `BYPASSRLS` "sempre auditado" e deixou para este ADR a escolha entre anonimizar e apagar; o modelo suporta as duas. O OIK-ADR-0003 deixou para cá a auditoria do uso de suporte com o operador identificado, a retenção dos eventos de autenticação e o destino da conta e dos acessos numa exclusão LGPD quando a pessoa tem acesso em outra organização. O OIK-ADR-0004 deixou a trilha formal das negações, exportações, mudanças de papel e de atribuição, uso de suporte e reconhecimento de responsável, e fixou que nenhum valor sensível vai a log e que as ações do responsável são registradas "em nome de" o dependente.

O documento de produto `docs/product/0001-fundacao-e-pessoas.md` traz as regras:

- **BR-005 / FR-011:** mudança de vínculo, inativação e reativação exigem motivo e geram evento na linha do tempo; a ficha aceita registros manuais com data retroativa.
- **BR-008 / FR-016–017 / FLOW-006:** mesclagem sempre manual, campo a campo, e desfazível; desfazer restaura as duas fichas.
- **BR-013 / NFR-003 / FR-005:** ações administrativas, mudanças de acesso e exportações vão para a auditoria; o administrador consulta quem fez o quê e quando.
- **BR-022 / FR-028:** pessoa excluída fica 30 dias na lixeira, recuperável; depois é removida.
- **BR-023 / FR-024 / FLOW-008 / `OPEN-013`:** o pedido de exclusão LGPD é aprovado ou recusado pela secretaria da igreja da pessoa; a política de histórico e retenção depois da exclusão depende de orientação jurídica, não bloqueia a arquitetura e bloqueia a virada.
- **NFR-002 / §14 / FR-027:** consentimento registrado (finalidade, data, origem) e preservado na migração; alteração pendente (campo, valor proposto, origem, status, justificativa).
- **§2 / §20:** no inChurch, importação e mesclagem sobrescrevem sem volta; aqui ambas precisam poder ser desfeitas, o que exige guardar o estado anterior e o histórico das operações. A importação por planilha ficou fora do MVP (`OPEN-011`), mas o mecanismo precisa existir para ela e para a migração (OIK-ADR-0009).
- `docs/analise/especificacao-tecnica-php.md` (§1, §5, §7, §10) sugere spatie/laravel-activitylog, snapshot "antes" na importação e `model:prune` na lixeira — referência, não decisão.

Informado pelo usuário em 2026-10-19: a auditoria é lida **só pelo administrador**; numa edição de ficha ela guarda **quais campos mudaram, sem os valores**; ações administrativas ficam **5 anos** e eventos de login/sessão **1 ano**; mesclagem desfazível por **90 dias**; enquanto o `OPEN-013` estiver aberto, a exclusão LGPD tem **os dois modos implementados e o modo é configuração obrigatória** antes da virada; a purga da lixeira usa **o mesmo caminho** da exclusão LGPD; os **termos de consentimento são da organização** (controladora), a partir de um modelo da plataforma; o membro **revoga consentimento pela área do membro**. Equipe de 2–4 desenvolvedores + IA, sem plantão 24h, expansão para centenas de organizações em 2–3 anos.

## Opções consideradas
- **A. spatie/laravel-activitylog adaptado** — auditoria gravada a partir dos eventos de cada model (`LogsActivity`), tabela com `organizacao_id` e RLS; desfazer a partir dos valores antigos guardados pelo pacote.
- **B. Módulo próprio de Auditoria + snapshot para desfazer** — tabela `auditoria` só de inclusão, sob RLS e particionada por mês, gravada pela Action na mesma transação a partir de um catálogo de eventos no código; estado anterior das operações desfazíveis numa tabela separada e com prazo.
- **C. Triggers genéricos no PostgreSQL** — trigger em cada tabela copia a linha alterada para a auditoria; o autor vem de variável de sessão definida pela aplicação.

Para o desfazer, considerou-se ainda reconstruir o estado por histórico de eventos (event log/event sourcing); descartado porque o sistema não registra toda mudança como evento e não há de onde reconstruir.

## Comparação

| Pilar | A. activitylog adaptado | B. Módulo próprio + snapshot | C. Triggers no banco |
|---|---|---|---|
| Desempenho | Bom — uma linha por save; ação em massa gera uma linha por registro | Ótimo — uma linha por caso de uso; partição mensal com índice `(organizacao_id, ocorrido_em)` | Bom — trigger em toda escrita, com JSON da linha inteira |
| Simplicidade de manutenção | Aceitável — começa rápido, mas exige reconfigurar organização, RLS, "em nome de", suporte e "sem valores"; o OIK-ADR-0004 já descartou o spatie/permission pelo mesmo encaixe forçado | Bom — código pequeno (tabela, serviço, enum de eventos), escrito e testado pela equipe; cada Action declara o evento | Ruim — regra em PL/pgSQL, fora do caminho do Laravel, difícil de testar e de evoluir |
| Boas práticas | Aceitável — grava valores por padrão (contra a decisão "sem valores" e o `OBS-002`); é model Eloquent comum, alterável; perde a intenção (uma mesclagem vira dezenas de updates) | Ótimo — auditoria por caso de uso, alinhada às Actions do OIK-ADR-0001; só inclusão garantida por GRANT; autor, "em nome de" e suporte explícitos; sem valores nem texto livre | Bom em cobertura (pega até SQL direto); Ruim na LGPD — copia contatos em claro e dados cifrados para a auditoria |
| Escalabilidade | Aceitável — tabela única polimórfica sem partição; purga por DELETE em massa | Ótimo — purga por DROP de partição; volume proporcional a ações, não a escritas | Aceitável — volume proporcional a todas as escritas de todas as organizações |
| Observabilidade / logs | Bom — lista de atividades pronta | Ótimo — evento de auditoria com o mesmo nome do evento de log técnico; `request_id` liga auditoria e log | Aceitável — diferença de linha sem contexto de negócio; "quem" depende de variável de sessão |

## Decisão
Adotar a **opção B**: módulo próprio de Auditoria, separado da linha do tempo e do log técnico, e desfazer por **snapshot** do estado anterior com prazo.

1. **Três registros separados.**
   - **Linha do tempo** (`eventos_linha_do_tempo`): dado de negócio da Pessoa, sob RLS, mostrado na ficha conforme as permissões do OIK-ADR-0004; aceita registros manuais com data retroativa; segue o ciclo de vida da ficha (lixeira, exclusão LGPD).
   - **Auditoria** (`auditoria`): prestação de contas — quem fez o quê, quando, em nome de quem e com que autorização; só inclusão; lida só pelo administrador.
   - **Log técnico:** diagnóstico, sem dado pessoal, retenção curta; ferramentas no OIK-ADR-0010.
2. **Gravação da auditoria.** Catálogo de eventos de auditoria num enum PHP versionado (mesmo padrão do catálogo de permissões do OIK-ADR-0004); a Action grava o evento pelo serviço de auditoria **na mesma transação** da mudança — se a auditoria falha, a ação falha. Cada linha guarda: `organizacao_id`, `ocorrido_em`, evento, ator (`acesso_id`/`conta_id` ou `operador_id`), `autorizacao_suporte_id` quando houver, "em nome de" (Pessoa dependente), alvo (tipo e ID), igreja, **nomes dos campos alterados (sem valores)**, metadados codificados (motivo codificado, filtro e categorias de exportação, contagem), canal (painel/área do membro/app/job/comando) e `request_id`. Nunca valores de campo nem texto livre; o motivo digitado fica na linha do tempo, não na auditoria.
3. **Imutabilidade e leitura.** O papel da aplicação tem só `INSERT` e `SELECT` em `auditoria` (sem `UPDATE`/`DELETE`); tabela particionada por mês; a purga por retenção é `DROP` de partição feito pelo papel dono do schema num comando agendado. A leitura exige `auditoria.ver`, que vem só no papel Administrador pronto e respeita o alcance (OIK-ADR-0004); o suporte nunca lê a auditoria.
4. **Auditoria da plataforma.** `auditoria_plataforma`, fora da RLS de organização, registra todo uso do papel com `BYPASSRLS` (comando, operador, motivo, organizações afetadas) e a gestão de operadores; é lida só pela plataforma. Ações do operador **dentro** de uma organização, sob autorização de suporte, gravam na auditoria da própria organização com o operador e a autorização identificados, e o administrador as vê.
5. **O que é auditado.**
   - Ações administrativas sobre Pessoas: cadastro, edição (campos), mudança de vínculo/situação, transferência (pedido/aprovação/recusa), mesclagem e desfazer, importação e desfazer, exclusão, recuperação e purga da lixeira, aprovação/recusa de alterações pendentes e de contas pendentes de vínculo.
   - Exportações: pedida, concluída e negada (filtro, categorias, contagem).
   - Acessos e autorização: convites, concessão e revogação de acesso, papéis e atribuições (permissões/escopo antes e depois, que não são dado pessoal), configuração de campos da área do membro, 2FA ligado/desligado.
   - Suporte: autorização criada/revogada/expirada e cada ação sob ela.
   - Responsável: reconhecimento, fim (motivo) e ações do responsável "em nome de" o dependente.
   - LGPD: pedido de exclusão (estado do §12) e execução (modo, módulos atingidos).
   - Autenticação: os eventos de login, 2FA, elevação, sessão, token e recuperação do OIK-ADR-0003.
   - Negações de segurança: `authz.escalation_blocked`, `authz.last_admin_blocked`, tentativa proibida ao suporte e `export.denied`. O `authz.denied` comum (alto volume, diagnóstico) fica só no log técnico.
   - Consentimentos têm registro próprio (regra 9), não duplicado na auditoria.
6. **Operações desfazíveis (mesclagem e importação).** `operacoes_desfaziveis` (organização, tipo, autor, estado `executada|desfeita|expirada`, `expira_em` = execução + 90 dias) com o **snapshot** do estado anterior de cada registro afetado (sensíveis cifrados com a chave de campos do OIK-ADR-0004) e a lista do que foi criado ou movido (vínculos, relacionamentos, funções, eventos, conta). Desfazer é uma Action de compensação, auditada: recria a ficha absorvida, devolve o que foi movido e restaura cada campo **só se o valor atual ainda é o que a operação gravou**; se alguém mudou depois, mantém o atual e mostra o conflito à secretaria. Depois do prazo, um job apaga o snapshot e a operação fica só na auditoria. A importação por planilha (fora do MVP) e a carga da migração usam o mesmo mecanismo; o ensaio e a repetição da migração são do OIK-ADR-0009.
7. **Lixeira.** Excluir marca a Pessoa (`excluida_em`, `excluida_por`, motivo) e um escopo global a esconde de telas, buscas e exportações; recuperar é possível por 30 dias. Um job diário, executado **por organização com o contexto definido** (OIK-ADR-0002), chama para as vencidas o mesmo removedor da regra 8.
8. **Exclusão LGPD com dois modos.** Cada módulo que guarda dado pessoal de uma Pessoa registra um **removedor** (contrato por módulo) com os modos `anonimizar` e `apagar`; módulo novo (Células, Kids, Eventos) só entra com o seu.
   - **Anonimizar:** apaga contatos, documentos, saúde, endereço, foto e textos livres (motivos, observações); nome vira "Pessoa removida"; mantém vínculos, datas e participações sem identificação, para contagens.
   - **Apagar:** remove a Pessoa e tudo que só existe por ela.
   - **Nos dois modos:** apaga snapshots de desfazer e alterações pendentes da pessoa; a auditoria fica só com IDs, que deixam de identificar alguém; a conta perde o acesso desta organização e é excluída só se não tiver acesso em outra (OIK-ADR-0003); os consentimentos seguem o modo.
   - O modo é **configuração obrigatória da plataforma**, sem padrão: em produção, o removedor recusa executar sem ela. É definido com orientação jurídica antes da virada (`OPEN-013`).
   - Um registro mínimo `exclusoes_executadas` (pessoa, pedido, data, modo) permite **reaplicar as exclusões depois de restaurar um backup** (OIK-ADR-0011).
9. **Consentimento.** `termos` por organização (finalidade, versão, texto, hash, vigência), criados a partir de um modelo da plataforma; `consentimentos` só de inclusão (Pessoa, finalidade, versão do termo, `concedido|revogado`, data, origem — formulário público, área do membro, secretaria, migração do inChurch —, autor e "em nome de"). O estado atual é o último registro por finalidade. O membro revoga pela área do membro (LGPD art. 8 §5); revogar é um novo registro, nunca alteração.
10. **Retenção (configurável, revisada com o jurídico no `OPEN-013`).** Auditoria de ações: 5 anos. Eventos de autenticação: 1 ano (acima dos 6 meses do Marco Civil para registros de acesso). Snapshot de desfazer: 90 dias. Lixeira: 30 dias. Linha do tempo e consentimentos: enquanto a ficha existir. Auditoria da plataforma: 5 anos. Log técnico: OIK-ADR-0010.
11. **Fora deste ADR:** ferramentas de log, métricas e alertas (OIK-ADR-0010); retenção e cifra de backups e o procedimento de restauração (OIK-ADR-0011); telas de auditoria, lixeira, desfazer e consentimento (OIK-ADR-0006); avisos ao titular (OIK-ADR-0008); nomes finais de tabelas, enum completo de eventos e campos de cada removedor (Spec).

## Justificativa
- **Boas práticas (LGPD):** auditoria sem valores e sem texto livre não vira mais um lugar para procurar dado pessoal numa exclusão, e cumpre o "nenhum valor sensível em log" do OIK-ADR-0004. O estado anterior, que tem dado pessoal, fica num lugar só, cifrado e com prazo de 90 dias. Só inclusão garantida por GRANT, e não por convenção, impede que uma correção apressada apague a trilha.
- **Simplicidade de manutenção:** um evento por caso de uso casa com as Actions do OIK-ADR-0001; a mesclagem aparece como "mesclagem", não como dezenas de updates. Um único removedor serve lixeira e LGPD, e cada módulo novo implementa o seu uma vez. O pacote (A) exigiria desligar quase tudo o que ele faz por padrão.
- **Escalabilidade:** volume proporcional a ações, partição mensal e purga por `DROP` aguentam centenas de organizações sem DELETE em massa; o índice por organização mantém a consulta do administrador rápida.
- **Observabilidade:** o mesmo nome de evento e o `request_id` ligam a auditoria (o quê, para o administrador) ao log técnico (por quê falhou, para o suporte).
- **`OPEN-013`:** implementar os dois modos e exigir a escolha evita refazer o removedor quando o jurídico decidir, e o sistema não executa uma exclusão irreversível com um padrão que ninguém aprovou.

## Consequências

**Positivas:**
- Administrador responde "quem fez o quê" (FR-005) inclusive para ações do suporte e do responsável.
- Mesclagem e importação desfazíveis sem sobrescrever o que mudou depois (resolve a dor do §2).
- Exclusão LGPD pronta para anonimizar ou apagar, reaplicável após restauração de backup.
- Consentimentos com prova de versão do termo e histórico de concessão e revogação.

**Negativas / trade-offs aceitos:**
- A auditoria não mostra o valor antigo de um campo; para isso há o snapshot (90 dias, só de mesclagem/importação) e a linha do tempo.
- Código próprio de auditoria a escrever e testar, e cada Action precisa declarar o evento — esquecer é defeito, coberto por teste de arquitetura.
- Desfazer com conflito exige decisão da secretaria; não é um "voltar tudo" cego.
- Cada módulo novo precisa entregar o seu removedor antes de guardar dado pessoal.
- Dados apagados ainda existem nos backups até a rotação (OIK-ADR-0011); a reaplicação depende do registro `exclusoes_executadas`.
- A virada continua bloqueada até o jurídico escolher o modo e revisar os prazos (`OPEN-013`).

## Observabilidade — o que precisa ser instrumentado
- **Eventos a logar:** `audit.write_failed` (evento, organização, `request_id`; a Action falha junto); `undo.executed`/`undo.conflict` (operação, tipo, campos em conflito, sem valores)/`undo.expired`; `trash.deleted`/`trash.restored`/`trash.purged` (contagem por organização); `trash.purge.failed` (organização, motivo); `lgpd.request.created`/`approved`/`rejected`/`completed`; `lgpd.erasure.failed` (módulo, modo, organização); `lgpd.erasure.blocked_no_policy`; `lgpd.erasure.reapplied` (após restauração); `consent.granted`/`consent.revoked` (finalidade, versão, origem); `audit.retention.purged` (partição, linhas); `platform.bypass_used` gravado também em `auditoria_plataforma`. Todos com `request_id` e sem dado pessoal (`OBS-002`).
- **Métricas a expor:** linhas de auditoria por organização e por evento/dia; falhas de gravação da auditoria; operações desfazíveis ativas e conflitos ao desfazer; pessoas na lixeira e idade da mais antiga; pedidos LGPD pendentes por igreja e idade do mais antigo; tempo de execução do removedor; consentimentos revogados por finalidade; tamanho das partições.
- **Alertas a configurar:** qualquer `audit.write_failed`; `trash.purge.failed` ou pessoa na lixeira há mais de 31 dias; `lgpd.erasure.failed` ou `lgpd.erasure.blocked_no_policy` em produção; pedido LGPD aprovado e não concluído em 24h; partição de retenção vencida e não removida; uso do papel de plataforma sem linha em `auditoria_plataforma`. Ferramentas e limiares no OIK-ADR-0010.
- **Cenário de incidente validado:** terça de manhã a secretaria da igreja A diz que desfez uma mesclagem e o celular da pessoa "voltou errado". O suporte filtra `undo.conflict` pela operação e vê que o campo `celular` estava em conflito: a secretaria da igreja B atualizou o número depois da mesclagem, e o desfazer manteve o valor atual em vez de restaurar o antigo. Na auditoria (lida pelo administrador), aparece `pessoas.editar` com `campos=[celular]`, autor e data, sem o número em si. O suporte explica o conflito e a secretaria escolhe o valor certo, sem desenvolvedor. Num segundo caso, o alerta `lgpd.erasure.failed` aponta `modulo=kids` e `modo=anonimizar`: o pedido fica em "Aprovado", não em "Concluído", e o suporte sabe que o removedor do módulo Kids falhou e pode ser reexecutado sem tocar nos outros módulos.

## Lacunas conhecidas
- Modo da exclusão LGPD (anonimizar ou apagar), prazos de retenção e texto-base dos termos de consentimento: dependem de orientação jurídica (`OPEN-013`); o ADR fixa o mecanismo e prazos iniciais configuráveis.
- Finalidades de consentimento da organização (ex.: comunicação, uso de imagem, dados de saúde no Kids): a levantar com o produto antes da Spec.
- Prazo de rotação dos backups e procedimento de reaplicação das exclusões após restauração: OIK-ADR-0011.
- Se o titular poderá consultar quem acessou seus dados (direito de acesso, LGPD art. 18): não pedido no MVP; a auditoria já guarda o necessário.
- Enum completo de eventos de auditoria, nomes de tabelas e campos de cada removedor: Spec.
