# ADR-0004: Autorização recurso × ação × escopo e dados sensíveis (modelo próprio + criptografia de documentos e saúde)

**Status:** Aceito — após confirmação do usuário (2026-10-19)
**Data:** 2026-10-19
**Contexto do projeto:** Oikora One — plataforma da denominação (3 igrejas, ~520 membros) que substitui o inChurch módulo a módulo, com plano de atender outras igrejas.
**Identificação:** Produto `OIK-ADR-0004` · Governança `ADR-0026`
**Fonte da verdade:** `oikora-one:docs/adr/0004-autorizacao-recurso-acao-escopo-e-dados-sensiveis.md`

## Contexto
O OIK-ADR-0001 fixou Laravel só como API `/api/v1`, com uma Action por caso de uso. O OIK-ADR-0002 pôs a RLS do PostgreSQL só na fronteira da organização e deixou para este ADR a visibilidade entre igrejas da mesma organização (BR-006), a projeção restrita da busca e a criptografia de campos sensíveis. O OIK-ADR-0003 fixou conta global + `acessos` (conta × organização) sob RLS, e determinou que papéis e escopos se penduram no acesso, que abilities de token não substituem permissões, que o responsável age pela própria conta e que o suporte da plataforma só entra com autorização temporária do administrador; o que essa autorização libera e como o responsável chega à ficha dependente ficaram para cá.

O documento de produto `docs/product/0001-fundacao-e-pessoas.md` traz as regras:

- **Atores e permissões (§5, §13):** só Administrador da denominação e Secretaria (por igreja) operam o painel no MVP; a Secretaria cadastra, edita, muda vínculo, inativa, mescla, importa e exporta pessoas da sua igreja; o administrador vê as 3 igrejas e gere papéis, acessos e configurações.
- **Papéis (BR-018):** Administrador e Secretaria vêm prontos e editáveis; o administrador cria papéis novos.
- **Visibilidade entre igrejas (BR-006, FLOW-009):** a secretaria só abre a ficha de pessoas da sua igreja; na busca, de outras igrejas vê só nome, igreja e vínculo, e a partir daí pode pedir transferência; aprovada, a ficha passa a ser aberta pelo destino e deixa de ser pela origem.
- **Sensíveis (BR-007, §14):** saúde, documentos, endereço/contatos e fé/conversão só aparecem a quem tem permissão.
- **Área do membro (BR-009):** cada campo é oculto, só leitura, editável direto ou editável com aprovação; recusa tem justificativa e avisa o membro.
- **Responsável (BR-024, FLOW-010):** familiar com conta, reconhecido pela secretaria, vê e atualiza a ficha de pessoa sem e-mail e sem celular, com as mesmas regras de campo; a secretaria retira a responsabilidade quando quiser.
- **§20:** o filtro de visibilidade atravessa todas as telas, exportações e buscas.

A especificação de referência (`docs/analise/especificacao-tecnica-php.md` §5 e §8) sugere spatie/laravel-permission, `atribuicoes_papel` com escopo, global scope `EscopoDoUsuario`, abilities iguais às permissões e cast `encrypted` para CPF/RG/saúde; é referência, não decisão.

Informado pelo usuário em 2026-10-19: permissão de sensíveis **por categoria**; o papel Secretaria pronto vem com **todas as categorias, menos saúde**; o painel precisa buscar por **CPF exato** e por **celular/telefone/e-mail (inclusive parcial)**, sem filtro por endereço nem por fé/conversão sobre dado cifrado; escopo menor que igreja (célula, ministério, sala do Kids) fica como **ponto de extensão**; a autorização de suporte libera **um papel existente + igrejas + prazo de até 72h, com sensíveis desligados salvo marcação por categoria**, e nunca exportação nem gestão de acessos; a responsabilidade **encerra automaticamente** quando o dependente ganha acesso próprio; a exportação traz **só as categorias que quem exporta vê**. Equipe de 2–4 desenvolvedores + IA, sem plantão 24h, expansão para centenas de organizações em 2–3 anos.

## Opções consideradas
Modelo de autorização:
- **A. spatie/laravel-permission (modo times, time = organização) + tabela própria de escopo** — pacote para papéis/permissões; escopo por igreja/regional em tabela à parte.
- **B. Modelo próprio sobre Gate/Policies do Laravel** — catálogo de permissões no código; papéis e atribuições (acesso × papel × escopo) em tabelas sob a RLS por organização; alcance calculado por requisição e aplicado por filtro global obrigatório e por Policies.
- **C. B + escopo de igreja também na RLS** — a segunda barreira do banco passa a cobrir igreja, não só organização.

Dados sensíveis em repouso:
- **S1. Sem criptografia por campo** — só disco/backup cifrados (OIK-ADR-0011) + permissões.
- **S2. Documentos e saúde cifrados na aplicação** — CPF/RG e saúde com chave própria; CPF com hash de busca; contatos, endereço e fé/conversão em claro, protegidos por permissão e projeção.
- **S3. Todas as categorias sensíveis cifradas.**

## Comparação

Modelo de autorização:

| Pilar | A. spatie + escopo próprio | B. Modelo próprio (Gate/Policies) | C. B + RLS por igreja |
|---|---|---|---|
| Desempenho | Bom — permissões em cache, mas o cache do pacote é global e precisaria ser particionado por organização para não misturar papéis sob RLS | Bom — alcance em poucas consultas pequenas por requisição; filtro `igreja_id IN (…)` indexado | Aceitável — policy por linha mais pesada; busca do BR-006 exige função `SECURITY DEFINER` |
| Simplicidade de manutenção | Aceitável — dois modelos (pacote + escopo); pacote amarra papéis ao `User`, não ao `acesso`; migrations do pacote adaptadas para `organizacao_id` e RLS | Bom — ~4 tabelas e um serviço de alcance; o que o pacote daria (CRUD de papéis, middleware) é pouco código | Ruim — cada exceção (busca na denominação, transferência vista pelas duas igrejas, responsável, titular) vira regra SQL; regra de negócio dividida entre PHP e banco |
| Boas práticas | Aceitável — padrão de mercado, mas encaixe forçado com conta global + acessos (OIK-ADR-0003) | Bom — nega por padrão, Policies nativas, catálogo tipado e versionado, um ponto de decisão | Bom em defesa em profundidade; Ruim em manter a regra num lugar só |
| Escalabilidade | Bom | Bom — tipo de escopo extensível (célula, ministério) sem mexer no núcleo | Aceitável — cada novo tipo de escopo exige policies novas em todas as tabelas |
| Observabilidade / logs | Aceitável — negações genéricas do pacote; log à parte | Bom — um ponto emite `authz.denied` com permissão, escopo e motivo | Ruim — a RLS some com linhas em silêncio; "a secretaria não acha a pessoa" sem diagnóstico pelo suporte |

Dados sensíveis em repouso:

| Pilar | S1. Sem cifra por campo | S2. Documentos + saúde | S3. Todas as categorias |
|---|---|---|---|
| Desempenho | Ótimo | Bom — só decifra quando a coluna é lida, e ela só é lida para quem pode ver | Aceitável — decifra em toda leitura de ficha e lista |
| Simplicidade de manutenção | Ótimo | Bom — cast `encrypted` com chave própria e rotação; nada pedido busca ou ordena por esses campos | Ruim — quebra a busca parcial por celular/e-mail pedida e a lista de convertidos por data (BR-021) |
| Boas práticas | Aceitável — dump, réplica ou injeção expõem CPF e saúde (art. 11 LGPD) | Bom — protege os dados de maior dano em dumps, backups, réplicas e logs | Bom em proteção; cifrar fé/conversão protege pouco numa base de igreja, onde o vínculo já revela a fé |
| Escalabilidade | Ótimo | Bom | Aceitável |
| Observabilidade / logs | Neutro | Aceitável — exige `crypto.decrypt_failed` (sem valor) e alerta de chave errada após deploy | Aceitável |

## Decisão
Adotar **B (modelo próprio sobre Gate/Policies)** e **S2 (documentos e saúde cifrados na aplicação)**, com as regras abaixo.

1. **Catálogo de permissões no código.** Permissões `recurso.acao` (ex.: `pessoas.ver`, `pessoas.editar`, `pessoas.exportar`, `pessoas.mesclar`, `pessoas.transferencias.aprovar`, `papeis.gerir`, `acessos.gerir`) num enum PHP versionado; sensíveis por categoria e ação: `pessoas.sensiveis.{saude,documentos,contatos,fe}.{ver,editar}` (contatos inclui endereço). Permissão nova só por código e migration; o administrador combina permissões do catálogo, não as inventa.
2. **Papéis e atribuições sob RLS.** `papeis` (organização, nome, `sistema`), `papel_permissoes` e `atribuicoes_papel` (acesso × papel × `escopo_tipo` × `escopo_id`), todas com `organizacao_id` e RLS do OIK-ADR-0002, FKs compostas. Escopos do MVP: `organizacao`, `regional`, `igreja`; o tipo é extensível — um módulo que precise de escopo menor (célula, ministério, sala do Kids) o define no próprio ADR/Spec, junto com a regra que o traduz em linhas visíveis. Um acesso pode ter várias atribuições; permissões somam.
3. **Papéis prontos (BR-018).** Administrador: todas as permissões no escopo da organização. Secretaria: operação de Pessoas da sua igreja (§13) e as categorias sensíveis documentos, contatos e fé/conversão — saúde só se o administrador marcar. Ambos editáveis, com duas travas: sempre existe ao menos um acesso ativo com o papel Administrador no escopo da organização, e esse papel não perde `papeis.gerir`/`acessos.gerir`; **ninguém concede permissão ou escopo que não tem** (anti-escalada).
4. **Alcance por requisição.** Depois que o contexto de organização é validado (OIK-ADR-0003), um serviço calcula o **alcance** do acesso: para cada permissão, o conjunto de igrejas em que ela vale (organização e regional expandem para suas igrejas). Calculado a cada requisição, sem cache entre requisições no MVP, para que revogar valha na hora. Abilities do token do app não entram no cálculo.
5. **Três pontos de aplicação, negando por padrão.**
   - **Action/Policy:** toda Action declara a permissão exigida e autoriza via Policy contra o recurso (`pessoas.editar` + igreja da pessoa no alcance). Um teste de arquitetura falha se uma Action não declarar permissão.
   - **Consulta:** modelos com escopo de igreja (Pessoa e o que é lido por ela) têm um filtro global de alcance obrigatório sobre a igreja atual da pessoa (mantida pela transferência). Sem alcance definido no contexto (requisição ou job), a consulta gera erro, não lista vazia — o mesmo fail-closed da RLS. Tabelas filhas (contatos, endereços, linha do tempo) são lidas pela Pessoa, nunca listadas por fora dela.
   - **Resposta:** API Resources montam a projeção pelo alcance e pelas categorias sensíveis; a consulta nem seleciona colunas de categoria não permitida.
6. **Busca na denominação (BR-006).** Uma única classe de consulta pode sair do filtro de alcance: busca por nome/CPF exato/contato na organização inteira e devolve, para quem está fora do alcance, só identificador público, nome, igreja e vínculo — escolhidos no `SELECT`, não escondidos depois. Um teste de arquitetura garante que nenhum outro código remove esse filtro. Pedido de transferência (FLOW-009) é visível à secretaria de destino (quem pediu) e à de origem (quem aprova); aprovada, o alcance muda sozinho porque segue a igreja atual da pessoa.
7. **Dados sensíveis (BR-007).** Sem `…ver` da categoria, os campos dela não aparecem em ficha, lista, busca nem exportação; sem `…editar`, a API recusa a escrita (422 por campo). A busca por CPF exato e por contato funciona para quem tem a busca; o resultado segue a projeção de quem buscou.
8. **Criptografia por campo (S2).** CPF, RG (e país do documento) e saúde (necessidades especiais, tipo sanguíneo) cifrados na aplicação (cast `encrypted`) com **chave própria, separada da `APP_KEY`**, guardada no cofre de segredos do ambiente e com rotação por comando (chaves anteriores aceitas na leitura até recifrar). CPF tem `cpf_hash` (HMAC com chave própria) para busca exata e detecção de duplicados (OIK-ADR-0002). Contatos, endereço e fé/conversão ficam em claro, protegidos pelas regras 5–7, pela RLS e pela cifra de disco/backup do OIK-ADR-0011. Valores sensíveis nunca vão a log.
9. **Exportação.** Usa a mesma consulta (filtro de alcance) e a mesma projeção de quem exporta: só igrejas e categorias que ele vê na tela, com `pessoas.exportar` no escopo. O job de exportação guarda só quem pediu e o filtro, e recalcula o alcance quando roda — acesso revogado no meio não exporta.
10. **Regras de campo da área do membro (BR-009).** Catálogo de campos da ficha no código (cada campo com sua categoria) e configuração por organização (`oculto`, `leitura`, `edicao_direta`, `edicao_aprovacao`). Um único serviço de política de campo serve leitura e escrita da área do membro, no site e no app: oculto não sai na resposta; escrita em campo oculto/só leitura ou desconhecido é recusada (422 por campo); edição direta aplica; com aprovação cria alteração pendente para a secretaria. As categorias sensíveis restringem a equipe, não o titular: para o próprio membro (e para o responsável) vale só a configuração de campo.
11. **Responsável (BR-024, FLOW-010).** `responsabilidades` (organização, Pessoa dependente, Pessoa responsável, quem reconheceu, início, fim, motivo do fim), sob RLS. O acesso à ficha dependente é por relação, não por papel: a conta do responsável, com acesso ativo na organização ligado à Pessoa responsável, lê e altera a ficha dependente pelas rotas da área do membro, com as regras de campo da regra 10, mesmo se o dependente for de outra igreja da organização. Cada ação registra o autor (conta do responsável) e "em nome de" o dependente. Só é reconhecida para dependente sem acesso próprio; quando ele ganha acesso, a responsabilidade **encerra automaticamente**, com evento na linha do tempo e aviso ao responsável. A secretaria reconhece e retira (`pessoas.responsaveis.gerir`, no alcance da igreja do dependente).
12. **Autorização temporária de suporte (OIK-ADR-0003 §13).** `autorizacoes_suporte` passa a guardar: operador, papel existente da organização, igrejas (escopo), categorias sensíveis liberadas (nenhuma por padrão, marcação por categoria) e prazo de no máximo 72h, revogável. Sob a autorização, o operador tem o alcance desse papel limitado a essas igrejas e categorias. Independentemente do papel, são sempre negadas ao suporte: exportação, `papeis.gerir`, `acessos.gerir`, criar autorizações de suporte e configurações de segurança.
13. **Painel e app.** Rotas do painel exigem elevação (OIK-ADR-0003) e ao menos uma atribuição de papel; rotas da área do membro exigem acesso ativo ligado a uma Pessoa. O token do app continua sem acesso ao painel.
14. **Fora deste ADR:** registro de auditoria das negações, exportações, mudanças de papel e uso de suporte, retenção e anonimização (OIK-ADR-0005, `OPEN-013`); telas de papéis e de configuração de campo (OIK-ADR-0006); cofre de segredos, cifra de disco/backup e guarda da chave fora do backup do banco (OIK-ADR-0011); nomes finais de tabelas, enum completo de permissões e rotas (Spec).

## Justificativa
- **Simplicidade de manutenção:** o modelo do spatie (papéis no `User`, cache global) briga com duas decisões já aceitas — conta global com acessos (OIK-ADR-0003) e RLS por organização (OIK-ADR-0002) — e o escopo por igreja teria de ser feito à parte de qualquer jeito. O modelo próprio fica em poucas tabelas e um serviço de alcance, todo no padrão Gate/Policy do Laravel, que a equipe de 2–4 devs + IA mantém sem dependência extra. Pôr igreja na RLS (C) espalharia cada exceção de negócio em SQL.
- **Boas práticas:** a RLS segue como barreira dura entre organizações; entre igrejas, a regra fica num único ponto testável e fail-closed (sem alcance = erro), com teste de arquitetura para Actions sem permissão e para quem sai do filtro. Anti-escalada e administrador mínimo evitam que um papel editável tranque a organização ou dê mais do que quem concede tem. A cifra S2 protege CPF e saúde — os dados de maior dano (art. 11 LGPD, roubo de identidade) — em dumps, réplicas e backups, sem quebrar a busca por contato pedida.
- **Escalabilidade:** o alcance não cresce com o número de organizações (é por acesso) e o tipo de escopo aceita célula/ministério quando Células e Kids chegarem (BR-014) sem mexer no núcleo.
- **Desempenho:** alcance em poucas consultas indexadas por acesso; filtro por `igreja_id` indexado; decifra só o que é exibido a quem pode ver. Com ~520 membros o custo é desprezível; com centenas de organizações continua por requisição e por acesso.
- **Observabilidade:** toda negação passa pelo mesmo ponto e é logada com permissão, escopo e motivo, o que deixa o suporte responder "por que a secretaria não vê" sem desenvolvedor — o que a RLS por igreja (C) não permitiria.

## Consequências

**Positivas:**
- Uma regra de visibilidade para telas, buscas e exportações (§20), aplicada antes da consulta e conferida na Action.
- Papéis editáveis pelo administrador sem risco de trancar a organização ou escalar privilégio.
- CPF e saúde ilegíveis num dump ou backup vazado; busca por CPF exato e por contato continuam.
- Membro e responsável seguem exatamente a mesma política de campo, no site e no app.
- Suporte entra só com o que o administrador liberou, por prazo curto, sem exportar nem mexer em acessos.

**Negativas / trade-offs aceitos:**
- O CRUD de papéis, a tela de permissões e o serviço de alcance são código próprio a manter (sem pacote).
- A separação entre igrejas não tem segunda barreira no banco; depende do filtro global e dos testes de arquitetura.
- Campos cifrados não permitem busca parcial, ordenação nem filtro (só CPF exato via hash); a chave própria precisa de cofre, rotação e cópia fora do backup do banco — perder a chave é perder CPF, RG e saúde.
- Contatos, endereço e fé/conversão ficam em claro no banco, protegidos só por permissão, RLS e cifra de disco.
- A interpretação "sensíveis restringem a equipe, não o titular" precisa constar na Spec e nos testes para não ser revertida por engano.

## Observabilidade — o que precisa ser instrumentado
- **Eventos a logar:** `authz.denied` (permissão, tipo/ID do escopo pedido, motivo codificado: sem permissão, fora do alcance, categoria sensível, suporte proibido, campo não editável; `acesso_id`, `request_id`); `authz.context_missing` (consulta com escopo de igreja sem alcance definido); `authz.role.changed` e `authz.assignment.changed` (quem, papel, permissões/escopo antes e depois); `authz.escalation_blocked`; `authz.last_admin_blocked`; `authz.cross_church_search` (quem buscou, quantos resultados fora do alcance, sem termo em claro quando for CPF/contato); `export.requested`/`export.completed`/`export.denied` (filtro, categorias, contagem); `guardian.recognized`/`guardian.ended` (motivo: secretaria, dependente ganhou acesso); `support.scope_applied` (operador, papel, igrejas, categorias, expiração); `crypto.decrypt_failed` (campo, versão da chave, sem valor). Nenhum valor sensível em log (`OBS-002`). Retenção e trilha de auditoria formal no OIK-ADR-0005.
- **Métricas a expor:** negações por motivo e por permissão; `authz.context_missing` por rota/job; buscas na denominação por dia; exportações por organização e categoria; responsabilidades ativas; autorizações de suporte ativas; falhas de decifra; tempo do cálculo de alcance (p95).
- **Alertas a configurar:** qualquer `authz.context_missing` em produção (bug de rota/job sem alcance); qualquer `crypto.decrypt_failed` (chave errada ou corrompida após deploy); pico de `authz.denied` por um mesmo acesso (sondagem); exportação com categoria sensível fora do horário habitual; `authz.last_admin_blocked`. Ferramentas e limiares no OIK-ADR-0010.
- **Cenário de incidente validado:** segunda de manhã a secretaria da igreja B diz que não consegue abrir a ficha de uma pessoa que "é dela". O suporte filtra `authz.denied` pelo acesso da secretaria e vê `motivo=fora_do_alcance` com a igreja atual da pessoa = A; nos eventos de transferência, encontra o pedido de B ainda pendente na secretaria de A. Resolve orientando a secretaria de A a aprovar, sem desenvolvedor. Num segundo caso, depois de um deploy, fichas abrem sem CPF e o alerta de `crypto.decrypt_failed` dispara com a versão da chave: o suporte identifica que o segredo da chave de campos não foi configurado no ambiente e restaura o valor do cofre.

## Lacunas conhecidas
- Lista final de permissões do catálogo, nomes de tabelas e rotas: Spec.
- Escopo menor que igreja (célula, ministério, sala do Kids) e como ele vira linhas visíveis: no ADR/Spec de Células e Kids.
- Cofre de segredos, guarda da chave de campos fora do backup do banco e periodicidade de rotação: OIK-ADR-0011.
- Trilha de auditoria, retenção dos eventos e anonimização vs exclusão (`OPEN-013`): OIK-ADR-0005.
- Se um responsável poderá cuidar de menores que têm conta própria (BR-024 hoje só cobre pessoa sem contato): fora do MVP; exige decisão de produto.
- Interface do operador de suporte e seleção de papel/igrejas na autorização: OIK-ADR-0006 / Spec.
