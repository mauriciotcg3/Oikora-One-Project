# ADR-0002: Banco de dados e isolamento multi-organização (PostgreSQL + RLS por organização)

**Status:** Aceito — após confirmação do usuário (2026-10-19)
**Data:** 2026-10-19
**Contexto do projeto:** Oikora One — plataforma da denominação (3 igrejas, ~520 membros) que substitui o inChurch módulo a módulo, com plano de atender outras igrejas.
**Identificação:** Produto `OIK-ADR-0002` · Governança `ADR-0024`
**Fonte da verdade:** `oikora-one:docs/adr/0002-banco-e-isolamento-multi-organizacao.md`

## Contexto
O OIK-ADR-0001 fixou Laravel 12+/PHP 8.4+ em monólito modular, exposto só pela API `/api/v1`, e deixou para este ADR o banco de dados e o isolamento entre organizações. O documento de produto `docs/product/0001-fundacao-e-pessoas.md` traz as regras que o modelo de dados precisa garantir:

- **Expansão (§20):** hoje é uma denominação, mas há plano de oferecer a plataforma a outras igrejas; isso exige isolamento de dados entre organizações e hierarquia variável, com ou sem regionais.
- **Unicidade na denominação:** uma pessoa existe uma única vez na denominação (BR-001); a matrícula é única na denominação e acompanha o membro na transferência entre igrejas (BR-004, BR-019); a pessoa tem vínculo com uma só igreja local por vez (BR-015).
- **Busca na denominação com visibilidade restrita (BR-006):** a secretaria busca em todas as igrejas da denominação, mas de pessoas de outras igrejas vê só nome, igreja e vínculo.
- **Dados sensíveis (BR-007, §14, NFR-002):** saúde, documentos, endereço e contatos, e dados de fé/conversão, tratados conforme a LGPD.
- **Volume (NFR-001):** 3 igrejas e ~520 membros, além de visitantes, frequentadores e contas.

A especificação de referência (`docs/analise/especificacao-tecnica-php.md` §2, §3, §12.1) propõe "multi-igreja num banco só", com `organizacao_id`/`igreja_id` em cada registro e isolamento por global scope e policies; usa colunas `jsonb` (`campos_extras`) e um campo `nome_busca` sem acento; e recomenda PostgreSQL "pelo JSONB e pela busca sem acento". A baseline da governança (`architecture-standard.md` §3, ADR global ADR-0001) já define PostgreSQL como banco relacional.

Informado pelo usuário em 2026-10-19: expectativa de **centenas de organizações clientes ou mais** em 2–3 anos; **talvez** um cliente grande exija banco dedicado no futuro (não agora, mas o caminho deve existir); entre a denominação e a igreja local há **no máximo um nível** (regional opcional). Equipe pequena (2–4 desenvolvedores + IA) e disponibilidade em horário comercial e cultos, sem plantão 24h (OIK-ADR-0001).

Termos usados aqui: **organização** é o cliente da plataforma (uma denominação ou uma igreja independente) e é a fronteira de isolamento; **igreja local** e **regional** são unidades dentro de uma organização. Quem vê o quê dentro da organização (BR-006, BR-007) é autorização (OIK-ADR-0004), não isolamento.

## Opções consideradas
Banco de dados:
- **PostgreSQL** — baseline da governança; JSONB indexável, `pg_trgm`/`unaccent`, índice parcial, Row-Level Security (RLS) nativo.
- **MySQL 8** — collation sem acento e JSON, mas sem RLS, sem índice parcial e sem busca por similaridade.

Isolamento entre organizações:
- **A. Banco compartilhado, coluna `organizacao_id` + global scope + policies** — isolamento só na aplicação.
- **B. A + RLS do PostgreSQL por organização** — a aplicação continua filtrando; o banco recusa linhas de outra organização como segunda barreira.
- **C. Schema por organização** — um schema PostgreSQL por cliente, troca de `search_path` por requisição.
- **D. Banco por organização** — um banco (ou instância) por cliente.

## Comparação

Banco de dados:

| Pilar | PostgreSQL | MySQL 8 |
|---|---|---|
| Desempenho | Ótimo — `pg_trgm` + `unaccent` atendem a busca de nome sem acento e com erro de digitação (duplicados BR-001, busca BR-006); JSONB com índice GIN para campos extras | Bom — collation `utf8mb4_0900_ai_ci` ignora acento, mas não há busca por similaridade; JSON sem índice GIN |
| Simplicidade de manutenção | Bom — suporte maduro no Laravel; índice único parcial expressa "matrícula única entre os não excluídos" direto no banco | Aceitável — sem índice parcial, regras desse tipo viram colunas geradas ou ficam só na aplicação |
| Boas práticas | Ótimo — é a baseline da governança (`architecture-standard.md` §3, ADR-0001 global) e a recomendação da especificação (§12.1) | Ruim — exigiria ADR de exceção à baseline (`ARCH-003`) |
| Escalabilidade | Ótimo — RLS nativo, partição e réplicas de leitura disponíveis se o volume pedir | Bom em carga, mas sem RLS o isolamento fica só na aplicação |
| Observabilidade / logs | Bom — `pg_stat_statements`, `application_name` e variáveis de sessão mostram origem e organização da consulta | Bom — `performance_schema` e slow log |

Isolamento entre organizações:

| Pilar | A. Coluna + scope + policies | B. A + RLS por organização | C. Schema por organização | D. Banco por organização |
|---|---|---|---|---|
| Desempenho | Ótimo — índices iniciados por `organizacao_id`; um pool de conexões | Ótimo — a policy acrescenta um filtro sobre coluna já indexada | Bom por consulta; catálogo e cache de planos crescem com centenas de schemas | Ruim em custo — conexões, memória e backup por banco |
| Simplicidade de manutenção | Ótimo — um schema, uma migration | Bom — uma policy padrão por tabela, contexto obrigatório por requisição/job e testes rodando com o papel sem privilégio | Ruim — cada migration roda em centenas de schemas; `search_path` em filas, testes e relatórios | Ruim — centenas de bancos para migrar, guardar e monitorar com 2–4 devs |
| Boas práticas | Aceitável — uma consulta sem filtro (`DB::table`, `withoutGlobalScopes`, exportação, job) vaza dado sensível de outra organização | Ótimo — defesa em profundidade para dados LGPD; contexto ausente falha fechado e com erro | Bom — isolamento forte, mas fora do caminho comum do Laravel | Ótimo — isolamento físico |
| Escalabilidade | Ótimo — milhares de organizações pequenas no mesmo banco | Ótimo — idem; um cliente grande sai para instância dedicada exportando por `organizacao_id` | Ruim — degrada a partir de centenas de organizações | Aceitável — escala por cliente, mas a operação cresce linearmente |
| Observabilidade / logs | Bom — `organizacao_id` no contexto de log (OIK-ADR-0001) | Ótimo — idem, e violação de isolamento vira erro explícito com tabela, organização e rota | Aceitável — erro de `search_path` é difícil de diagnosticar | Bom — por banco, mas espalhado em centenas de alvos |

## Decisão
Adotar **PostgreSQL** e a opção **B** (banco compartilhado com `organizacao_id`, global scope e policies, e RLS por organização como segunda barreira):

1. **Banco:** PostgreSQL 17 ou superior (versão exata fixada no bootstrap/Spec, conforme o provedor do OIK-ADR-0011), com as extensões `pg_trgm` e `unaccent`. Um único banco para todas as organizações.
2. **Fronteira de isolamento = organização.** Toda tabela de domínio tem a coluna `organizacao_id` (FK para `organizacoes`), inclusive tabelas filhas (contatos, endereços, vínculos, linha do tempo), para que a policy seja a mesma em todas. Ficam fora só `organizacoes`, catálogos globais e as tabelas de identidade/conta, cujo escopo é decidido no OIK-ADR-0003; cada exceção é listada nominalmente na Spec.
3. **Primeira barreira — aplicação:** o contexto de organização é resolvido por requisição (organização do usuário autenticado; nos formulários públicos, pelo identificador do formulário/igreja, nunca por parâmetro livre do cliente) e aplicado por global scope nos Models e por policies nas Actions. Visibilidade entre igrejas da mesma organização (BR-006) e dados sensíveis (BR-007) seguem no OIK-ADR-0004.
4. **Segunda barreira — RLS:** toda tabela com `organizacao_id` tem RLS habilitada e forçada (`ENABLE` + `FORCE ROW LEVEL SECURITY`) com uma policy padrão `USING`/`WITH CHECK` `organizacao_id = <organização do contexto>`. A aplicação grava o contexto numa variável de sessão do PostgreSQL no início de cada requisição e de cada job e o limpa no fim. **Contexto ausente gera erro, não lista vazia** (fail-closed e ruidoso); o mecanismo exato (variável de sessão ou `SET LOCAL` por transação, conforme o pooler do OIK-ADR-0011) fica na Spec.
5. **Papéis de banco:** um papel dono do schema, usado só por migrations; um papel da aplicação (API e workers) que não é dono das tabelas e não tem `BYPASSRLS`; e um papel de plataforma com `BYPASSRLS`, usado só por comandos administrativos explícitos entre organizações (provisionar organização, métricas da plataforma, mover cliente), sempre auditados (OIK-ADR-0005).
6. **Integridade entre organizações:** como FKs do PostgreSQL não passam pela RLS, referências entre tabelas de domínio usam **FK composta** `(organizacao_id, <tabela>_id)` contra uma chave única `(organizacao_id, id)` na tabela referenciada. Assim, uma pessoa nunca aponta para a igreja, regional ou família de outra organização.
7. **Unicidades na organização:** toda regra de unicidade inclui `organizacao_id` — matrícula única na denominação (BR-004), `cpf_hash` para detectar duplicados (BR-001), e similares —, com índice único parcial quando a regra ignora registros excluídos (lixeira, BR-022).
8. **Hierarquia:** `organizacoes` → `regionais` (opcional) → `igrejas`, com `igrejas.regional_id` anulável e FK composta garantindo que regional e igreja são da mesma organização. Um nível intermediário no máximo; mais níveis exigem novo ADR.
9. **Chaves:** chave interna `bigint` (identity) e identificador público UUID nas rotas da `/api/v1`, para não expor sequências enumeráveis entre organizações e para preservar as chaves ao mover uma organização.
10. **Busca:** `nome_busca` (minúsculo, sem acento) com índice trigram (GIN, prefixado pela organização quando o plano exigir) atende o detector de duplicados e a busca na denominação; a projeção restrita do BR-006 é aplicada pela API (OIK-ADR-0004).
11. **Caminho para instância dedicada:** um cliente que exija banco próprio recebe uma instância separada do mesmo código e do mesmo schema, carregada por uma exportação/importação por `organizacao_id`. Essa ferramenta é a mesma que atende saída de cliente e portabilidade LGPD e é construída quando o primeiro caso aparecer; não se mantém código específico por cliente.
12. **Verificação contínua (CI):** testes rodam com o papel da aplicação, não com o dono, para que a RLS seja exercitada; um teste de arquitetura falha se uma tabela tiver `organizacao_id` sem RLS forçada e policy, ou se uma tabela de domínio não tiver `organizacao_id` e não estiver na lista de exceções.
13. **Fora deste ADR:** conta/login entre organizações (OIK-ADR-0003); permissões, visibilidade entre igrejas e criptografia de campos sensíveis (OIK-ADR-0004); auditoria, desfazer e anonimização vs exclusão (OIK-ADR-0005, `OPEN-013`); provedor, versão exata, pooler, backups e PITR (OIK-ADR-0011).

## Justificativa
- **Boas práticas e escalabilidade:** com centenas de organizações previstas, schema ou banco por cliente (C, D) multiplicam migrations, backups e monitoramento além do que 2–4 desenvolvedores sustentam. O banco compartilhado escala para esse volume de igrejas pequenas e médias sem redesenho, e o caminho de instância dedicada atende ao "talvez" de um cliente grande sem custo agora.
- **Boas práticas (LGPD):** o dado guardado é sensível (saúde, documentos, fé/conversão). Na opção A, uma única consulta esquecida (relatório, exportação, job, `DB::table`) vaza dados de outra organização em silêncio. A RLS transforma esse erro em falha visível e sem vazamento; global scope e policies continuam sendo a regra do dia a dia, e a RLS cobre o que escapar.
- **Simplicidade de manutenção:** a RLS fica restrita a uma policy padrão por tabela, só na fronteira de organização; a visibilidade entre igrejas, que muda mais, fica na aplicação (OIK-ADR-0004). O teste de arquitetura impede tabela nova sem a policy.
- **Desempenho:** a policy filtra por uma coluna que já inicia os índices; com ~520 membros hoje e organizações pequenas, o custo é desprezível. `pg_trgm`/`unaccent` resolvem no banco a busca sem acento e por similaridade que o detector de duplicados precisa.
- **PostgreSQL vs MySQL:** só o PostgreSQL oferece RLS, índice parcial e busca trigram, e é a baseline da governança; MySQL exigiria exceção (`ARCH-003`) e deixaria o isolamento só na aplicação.
- **Observabilidade:** violação de isolamento e contexto ausente viram erros com nome, tabela, organização e rota/job, em vez de dados errados na tela.

## Consequências

**Positivas:**
- Vazamento entre organizações exige falhar ao mesmo tempo na aplicação e no banco; contexto esquecido falha fechado.
- Um schema e uma migration para todas as organizações; a operação não cresce com o número de clientes.
- Unicidades e referências garantidas pelo banco, por organização (matrícula, duplicados, igreja/regional da mesma organização).
- Busca sem acento e por similaridade no próprio PostgreSQL, sem serviço de busca adicional nesta fase.
- Cliente grande pode ir para instância dedicada com o mesmo código.

**Negativas / trade-offs aceitos:**
- Toda requisição e todo job precisam definir o contexto de organização; jobs longos e comandos agendados precisam de middleware próprio, e esquecer o contexto vira erro em produção (aceito: é preferível a vazamento).
- Migrations ficam mais longas: coluna `organizacao_id`, chave única `(organizacao_id, id)`, FKs compostas e policy em cada tabela; exige helper de migration e disciplina.
- Testes precisam rodar com o papel da aplicação e semear o contexto; consultas de plataforma entre organizações exigem o papel com `BYPASSRLS`, auditado.
- Backup e restore são do banco inteiro; restaurar ou exportar uma só organização exige a ferramenta de exportação por `organizacao_id`, ainda não construída.
- O uso de pooler em modo transação (OIK-ADR-0011) obriga a definir o contexto por transação, não por sessão.
- Todas as organizações compartilham a mesma instância: uma consulta pesada de um cliente afeta os demais até que ele seja movido para instância dedicada.

## Observabilidade — o que precisa ser instrumentado
- **Eventos a logar:** `tenant.context.missing` (erro) com rota ou nome do job/comando e `request_id`; `tenant.rls_violation` (erro, SQLSTATE `42501`) com tabela, operação, organização do contexto, rota/job e `request_id`; `platform.bypass_used` com comando, operador, motivo e organizações afetadas; `application_name` distinto por processo (`oikora-api`, `oikora-worker`, `oikora-migrate`, `oikora-platform`) para separar origens no PostgreSQL. Nenhum dado pessoal no log (OIK-ADR-0001).
- **Métricas a expor:** contagem de `tenant.context.missing` e `tenant.rls_violation` por rota/job (esperado: zero); latência p95 de consultas (`pg_stat_statements`) e conexões em uso vs limite; tamanho em linhas/bytes por organização nas tabelas principais, para identificar candidatos a instância dedicada.
- **Alertas a configurar:** qualquer `tenant.rls_violation` ou `tenant.context.missing` em produção (indica defeito, não carga); uso de conexões acima do limite seguro; uso do papel de plataforma fora de um comando registrado. Ferramentas e limiares no OIK-ADR-0010.
- **Cenário de incidente validado:** na segunda de manhã a secretaria percebe que pessoas excluídas há mais de 30 dias continuam na lixeira (BR-022). O alerta da madrugada mostra `tenant.context.missing` no job `limpar-lixeira`, disparado por um comando agendado novo que não passou pelo middleware de contexto. Como a RLS falhou fechado, nenhuma linha de nenhuma organização foi apagada indevidamente; pelo nome do job no log, o suporte sabe qual rotina corrigir e que basta reexecutá-la por organização, sem ler código nem restaurar backup.

## Lacunas conhecidas
- Escopo das tabelas de identidade (uma conta com acesso a mais de uma organização ou contas por organização): OIK-ADR-0003; a lista de tabelas fora da RLS é fechada na Spec depois dele.
- Mecanismo exato do contexto (variável de sessão vs `SET LOCAL`), pooler e versão exata do PostgreSQL: dependem do provedor/hospedagem do OIK-ADR-0011.
- Critério comercial para oferecer instância dedicada e prazo da ferramenta de exportação por organização: não definidos.
- Volume por organização além de "centenas de organizações": não estimado; partição de tabelas não é decidida aqui e só entra com medição.
- Política de histórico após exclusão LGPD (`OPEN-013`): OIK-ADR-0005; o modelo suporta anonimizar ou apagar.
