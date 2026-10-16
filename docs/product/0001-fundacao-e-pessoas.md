# Product Document: Oikora One — Fundação + Pessoas

**Status:** Em descoberta
**Origem:** `docs/analise/` (análise do painel inChurch e da Central de Ajuda, 12–13/10/2026) + entrevista com o usuário (Product Owner, 2026-10-16)
**Data:** 2026-10-16

> Convenção de origem: **(CA)** = comportamento do inChurch documentado na Central de Ajuda; **(Análise)** = proposta de `docs/analise/analise-e-plano-nova-plataforma.md`; **(Usuário)** = confirmado na entrevista. Comportamento do inChurch descreve o AS-IS e só vira requisito quando confirmado.
> `docs/analise/especificacao-tecnica-php.md` (Laravel, Filament, banco, pacotes) **não** é fonte deste documento: essas escolhas pertencem ao ADR Arquiteto.

## 1. Overview

O Oikora One é a nova plataforma da denominação (3 igrejas locais, ~520 membros) para substituir o inChurch, módulo a módulo. Este primeiro documento cobre a **Fundação** (organização, acesso, papéis, auditoria) e o módulo **Pessoas** (cadastro único, ficha, vínculo, área do membro, formulário público, deduplicação e migração da base do inChurch). Os demais módulos (Células, Programação, Cuidado, Kids, Financeiro, Conteúdo) terão documentos próprios.

Objetivo de longo prazo (Usuário): depois da denominação, oferecer a plataforma a outras igrejas.

## 2. Problem Statement

No inChurch a gestão de pessoas — base de todos os módulos — é fragmentada:

- Uma mesma pessoa existe como usuário do app, visitante, frequentador, membro, usuário do painel e criança, ligados apenas por **e-mail igual** (CA). Quando o e-mail diverge, nada se conecta; desvincular pode **excluir o perfil de membro** (CA).
- Acesso ao painel só pelo **Painel V1**, em descontinuação; senha inicial definida pelo administrador; permissões sem restrição dentro da área (CA).
- Importação e mesclagem **duplicam ou sobrescrevem** sem pré-visualização e **sem volta** (CA).
- Obrigatoriedade de campos inconsistente entre canais; formulário público não personalizável (CA).
- Contagens diferentes entre telas sem explicação (434 × 466 × 513), gerando desconfiança da liderança.
- Baixa produtividade: 6 itens por página, edição fragmentada, aniversariantes do mês só por e-mail.

Hoje as secretarias das 3 igrejas operam o cadastro no inChurch.

## 3. Objectives

1. Uma pessoa, uma ficha, uma identidade em toda a denominação.
2. Acesso seguro e granular, sem senha definida por terceiros e sem depender de um segundo painel.
3. Cadastro rápido, sem duplicidade, com as mesmas regras em todos os canais.
4. Membro mantém os próprios dados pela área do membro, com revisão da secretaria quando configurado.
5. Migrar a base de Pessoas do inChurch unificada e conferida, preservando histórico.

## 4. Success Criteria

- **(Usuário)** As 3 secretarias operam Pessoas só no Oikora e o inChurch deixa de ser usado para Pessoas.
- Base migrada sem duplicados conhecidos e com contagens conferidas por uma definição única (Análise; ver `OPEN-019`).

## 5. Actors

| ID | Actor | Role | Objective | Allowed actions | Restrictions |
|---|---|---|---|---|---|
| ACTOR-001 | Administrador da denominação | Gestão da plataforma | Configurar a denominação e controlar acessos | Gerir igrejas, papéis e acessos; configurar regras de Pessoas; ver as 3 igrejas; ver auditoria | Ações registradas em auditoria |
| ACTOR-002 | Secretaria (por igreja local) | Operação do cadastro | Manter a base de pessoas da sua igreja correta e completa | Cadastrar, editar, mudar vínculo, inativar; aprovar alterações e contas; tratar duplicados; importar; exportar | Abre ficha só de pessoas da sua igreja; dados sensíveis só com permissão específica |
| ACTOR-003 | Pessoa com conta (membro/frequentador/visitante) | Autoatendimento | Ver e atualizar os próprios dados | Criar conta; ver a própria ficha; propor/alterar campos permitidos; pedir exclusão de dados | Só os próprios dados; campos sob aprovação dependem da secretaria |
| ACTOR-004 | Visitante anônimo | Auto-cadastro | Registrar-se na igreja sem conta | Preencher o formulário público | Não vê nenhum dado da plataforma |

Pastores, recepção e demais papéis (Tesouraria, Líder de célula, Kids…) **não operam o painel no MVP** (Usuário).

## 6. Scope (In Scope)

- Organização: denominação → igrejas locais (sem regionais hoje).
- Acesso: conta de acesso ligada à Pessoa, convite para o painel, papéis com escopo, permissões por ação, permissão separada para dados sensíveis, auditoria.
- Pessoas: cadastro rápido com checagem de duplicidade, ficha completável, vínculo e situação com motivo, matrícula única na denominação, linha do tempo, família/relacionamentos, funções com histórico, tipos de membro, busca e lista produtivas, exportação.
- Duplicados: detecção e mesclagem manual com desfazer.
- Área do membro: auto-cadastro de conta, vínculo confirmado pela secretaria, atualização da ficha com aprovação configurável por campo.
- Formulário público (link/QR Code).
- LGPD: consentimento e pedido de exclusão de dados.
- Migração da base de Pessoas do inChurch (unificação, relatório de conflitos, preservação de histórico).

## 7. Out of Scope

- **Campos personalizados** (Usuário) — fase seguinte.
- Pastores e recepção como usuários do painel (Usuário).
- Regionais (não existem hoje; ver `ARCHITECTURE_CONSIDERATION` de expansão).
- Células, Programação/Eventos, Cuidado/Jornadas, Kids (check-in, salas), Financeiro, Conteúdo/Push, API pública e webhooks — documentos próprios.
- Oferta a outras igrejas/denominações (objetivo futuro, não desta fase).

## 8. Business Capabilities

```text
Organização (denominação e igrejas)
Identidade e Acesso (conta, convite, papéis, escopo, sensíveis)
Auditoria
Cadastro de Pessoas (ficha, vínculo, matrícula, linha do tempo, família, funções)
Qualidade da Base (duplicidade, mesclagem, obrigatoriedade por vínculo)
Autoatendimento do Membro (área do membro, aprovação de alterações)
Auto-cadastro Público
Privacidade / LGPD (consentimento, exclusão de dados)
Migração do inChurch
```

## 9. Functional Requirements

| ID | Requisito |
|---|---|
| FR-001 | O administrador deve conseguir cadastrar e manter as igrejas locais da denominação. |
| FR-002 | O administrador deve conseguir dar acesso ao painel a uma Pessoa existente, escolhendo papel e escopo, por convite enviado à pessoa. |
| FR-003 | A pessoa convidada deve definir a própria senha ao aceitar o convite. |
| FR-004 | O administrador deve conseguir revogar um acesso imediatamente. |
| FR-005 | O administrador deve conseguir consultar o registro de auditoria (quem fez o quê, quando, inclusive exportações). |
| FR-006 | A secretaria deve conseguir cadastrar uma pessoa em uma única tela (nome, celular ou e-mail, vínculo, igreja, como chegou), com opção "salvar e cadastrar outro". |
| FR-007 | Durante o cadastro, o sistema deve alertar sobre possível duplicidade na denominação e permitir abrir a ficha existente ou confirmar que é outra pessoa. |
| FR-008 | A secretaria deve conseguir completar a ficha por seções, vendo o percentual de completude conforme o vínculo. |
| FR-009 | A secretaria deve conseguir mudar o vínculo (visitante, frequentador, membro) e a situação (ativo, inativo), sempre informando motivo. |
| FR-010 | O sistema deve atribuir matrícula ao membro, única na denominação, automática ou manual. |
| FR-011 | A ficha deve exibir a linha do tempo automática (entrada, mudança de vínculo/tipo, inativação, reativação, com motivo) e permitir registros manuais com data retroativa. |
| FR-012 | A secretaria deve conseguir registrar família/relacionamentos entre pessoas. |
| FR-013 | A secretaria deve conseguir atribuir funções (com período) e tipo de membro. |
| FR-014 | A secretaria deve conseguir listar pessoas em tabela com 25/50/100 por página, filtros (inclusive aniversariantes do mês), ações em massa e exportação por download direto. |
| FR-015 | A secretaria deve conseguir buscar pessoas em toda a denominação; de pessoas de outra igreja vê apenas dados mínimos (ver `OPEN-004`). |
| FR-016 | A secretaria deve conseguir ver a lista de possíveis duplicados e mesclá-los escolhendo campo a campo o valor que fica. |
| FR-017 | A secretaria deve conseguir desfazer uma mesclagem. |
| FR-018 | Qualquer pessoa deve conseguir criar uma conta na área do membro. |
| FR-019 | A secretaria deve confirmar a qual Pessoa uma conta nova pertence (ou criar a Pessoa) antes de a conta acessar dados da ficha. |
| FR-020 | O membro deve conseguir ver e atualizar a própria ficha na área do membro. |
| FR-021 | O administrador deve conseguir configurar, por campo, se a alteração feita pelo membro entra direto ou passa por aprovação. |
| FR-022 | A secretaria deve conseguir aprovar ou recusar alterações pendentes campo a campo, com justificativa na recusa, e o membro deve ser avisado. |
| FR-023 | Um visitante deve conseguir se cadastrar por formulário público (link ou QR Code) sem conta. |
| FR-024 | O membro deve conseguir pedir a exclusão dos seus dados; o pedido segue para aprovação. |
| FR-025 | A base de Pessoas do inChurch deve ser migrada unificando membros, visitantes/frequentadores, usuários do app e usuários do painel (crianças: ver `OPEN-012`). |
| FR-026 | A migração deve gerar um relatório de conflitos para a secretaria revisar antes da virada. |
| FR-027 | A migração deve preservar matrícula, linha do tempo, funções com datas e consentimentos. |
| FR-028 | Excluir uma pessoa deve exigir confirmação e permitir recuperação por um período (ver `OPEN-014`). |

## 10. Business Rules

| ID | Regra | Origem |
|---|---|---|
| BR-001 | Uma pessoa existe uma única vez na denominação. | Análise + Usuário |
| BR-002 | Uma conta de acesso pertence a no máximo uma Pessoa; a ligação é confirmada pela secretaria, nunca deduzida por e-mail igual. | Análise + Usuário |
| BR-003 | Vínculos possíveis: visitante, frequentador, membro. Só membro tem matrícula. | CA, confirmar transições em `OPEN-006` |
| BR-004 | A matrícula é única na denominação e acompanha o membro se mudar de igreja local. | Usuário |
| BR-005 | Mudança de vínculo, inativação e reativação exigem motivo e geram evento na linha do tempo. | CA (manter) |
| BR-006 | A secretaria só abre a ficha de pessoas com vínculo na sua igreja; pode encontrar pessoas de outras igrejas na busca. | Usuário |
| BR-007 | Saúde, documentos, endereço e contatos, e dados de fé/conversão só são exibidos a quem tem permissão de dados sensíveis. | Usuário |
| BR-008 | A mesclagem de duplicados é sempre manual e pode ser desfeita. | Usuário |
| BR-009 | Alterações do membro seguem a configuração por campo (direto ou com aprovação); toda recusa tem justificativa e avisa o membro. | Usuário |
| BR-010 | Ninguém define a senha de outra pessoa; acesso ao painel é por convite com expiração. | Análise (resolve C5) |
| BR-011 | As regras de obrigatoriedade por vínculo são as mesmas em todos os canais (painel, formulário público, área do membro, importação, migração). | Análise (resolve C8) |
| BR-012 | Todo número exibido informa o critério (ex.: "membros ativos da igreja X"). | Análise (resolve C7) |
| BR-013 | Ações administrativas, mudanças de acesso e exportações são registradas em auditoria. | Análise |
| BR-014 | A partir da virada de Pessoas, cadastro e edição de pessoas acontecem só no Oikora; Pessoas no inChurch fica só para consulta. | Usuário (ver `OPEN-015`) |

## 11. Processes / Flows

```text
FLOW-001 — Cadastro rápido
Trigger: pessoa nova chega (culto, visita, ligação).
Actor action: secretaria informa nome, celular ou e-mail, vínculo, igreja, como chegou.
System behavior: verifica duplicidade na denominação enquanto digita.
Result: Pessoa criada (ou ficha existente aberta); evento de entrada na linha do tempo.
Alternative/error flows: suspeita de duplicado → "Abrir ficha" ou "É outra pessoa";
  duplicado em outra igreja → secretaria vê só dados mínimos (OPEN-004/OPEN-005).
```

```text
FLOW-002 — Conta na área do membro
Trigger: pessoa cria conta.
Actor action: informa dados de identificação.
System behavior: conta fica "pendente de vínculo"; secretaria recebe na fila.
Result: secretaria liga a conta a uma Pessoa existente ou cria a Pessoa; conta passa a ver a ficha.
Alternative/error flows: secretaria recusa (conta não pertence à igreja / suspeita) → pessoa avisada.
```

```text
FLOW-003 — Atualização pelo membro
Trigger: membro altera a ficha.
System behavior: campos "diretos" gravam e entram na linha do tempo; campos "com aprovação" viram alteração pendente.
Actor action (secretaria): aprova ou recusa campo a campo, com justificativa na recusa.
Result: ficha atualizada; membro avisado do resultado.
```

```text
FLOW-004 — Formulário público
Trigger: visitante acessa link/QR Code.
System behavior: aplica as mesmas regras de obrigatoriedade e de duplicidade.
Result: Pessoa criada como visitante (aprovação: OPEN-009).
```

```text
FLOW-005 — Acesso ao painel por convite
Trigger: administrador decide dar acesso a uma Pessoa.
Actor action: escolhe papel e escopo (denominação ou igreja), envia convite.
Result: pessoa aceita e define senha; acesso registrado em auditoria.
Alternative/error flows: convite expira; administrador revoga a qualquer momento.
```

```text
FLOW-006 — Mesclagem de duplicados
Trigger: suspeita de duplicado (cadastro, lista de suspeitos, migração).
Actor action: secretaria compara e escolhe o valor de cada campo.
Result: uma Pessoa única, com histórico das duas; registro em auditoria.
Alternative/error flows: desfazer mesclagem restaura as duas fichas.
```

```text
FLOW-007 — Migração do inChurch
Trigger: preparação da virada de Pessoas.
System behavior: extrai as pessoas do inChurch, cruza por e-mail, CPF, celular e nome + nascimento, gera relatório de conflitos.
Actor action: secretarias revisam conflitos; contagens conferidas por definição única.
Result: base unificada no Oikora; inChurch Pessoas passa a ser só consulta (BR-014).
```

```text
FLOW-008 — Pedido de exclusão de dados (LGPD)
Trigger: membro pede exclusão.
Actor action: responsável aprova ou recusa (OPEN-013).
Result: dados excluídos/anonimizados conforme política; pedido registrado.
```

## 12. States / Lifecycles

```text
Vínculo da Pessoa (por igreja): Visitante → Frequentador → Membro
Situação: Ativo ⇄ Inativo (sempre com motivo)
Transições proibidas: a confirmar (OPEN-006)
Quem executa: secretaria da igreja do vínculo; administrador
```

```text
Conta da área do membro: Pendente de vínculo → Vinculada → Bloqueada
Quem executa: secretaria (vincular/recusar); administrador (bloquear)
```

```text
Convite de acesso ao painel: Enviado → Aceito | Expirado | Revogado
Quem executa: administrador
```

```text
Alteração pendente: Pendente → Aprovada | Recusada (com justificativa)
Quem executa: secretaria
```

```text
Mesclagem: Executada → Desfeita
Quem executa: secretaria
```

## 13. Functional Permissions

```text
Administrador da denominação pode gerir igrejas, papéis, acessos e configurações de Pessoas
Administrador da denominação pode ver pessoas das 3 igrejas
Secretaria pode cadastrar, editar, mudar vínculo, inativar, mesclar e exportar pessoas da sua igreja
Secretaria pode buscar pessoas de outras igrejas, mas não abrir a ficha
Secretaria sem permissão de sensíveis não vê saúde, documentos, endereço/contatos, dados de fé/conversão
Membro pode ver e alterar só a própria ficha, nos campos permitidos
Visitante anônimo pode apenas enviar o formulário público
Ninguém pode definir a senha de outra pessoa
```

## 14. Functional Data

```text
Denominação
- nome
Igreja local
- nome, pertence à denominação
Pessoa
- nome completo, nascimento, sexo, foto, estado civil, data de casamento
- documentos (CPF, RG, país do documento)            [sensível]
- contatos (celular, é WhatsApp, telefone, e-mail)    [sensível]
- endereço (CEP, logradouro, número, complemento, bairro, cidade, estado, país) [sensível]
- saúde (necessidades especiais, tipo sanguíneo)      [sensível]
- dados eclesiásticos (aceitou Jesus e data/canal, batismo, batizado por, igreja de origem, é líder, é pastor) [sensível: fé/conversão]
- adicionais (escolaridade, profissão, nacionalidade, naturalidade)
Vínculo
- igreja, tipo (visitante/frequentador/membro), situação, tipo de membro, data e motivo de entrada/saída, matrícula
Relacionamento (família)
Função (função, desde, até)
Evento de linha do tempo (atividade, motivo, autor, data de ocorrência)
Alteração pendente (campo, valor proposto, origem, status, justificativa)
Consentimento (finalidade, data, origem)
Conta de acesso (ligada a uma Pessoa) → atribuições de papel com escopo
Papel → permissões (recurso × ação × escopo, inclui sensíveis)
```

Campos herdados do inChurch (assistente de 5 etapas, CA). Lista final de campos e obrigatórios por vínculo: `OPEN-003`.

## 15. Integrations

- **Extração da base do inChurch** para migração (a análise indica a API pública do inChurch ou a exportação "em formato de importação").
- **Envio de convite e avisos** ao membro/usuário (convite, recusa de alteração, resultado de vínculo). Canal: `OPEN-008`.
- Preenchimento de endereço a partir do CEP (Análise; resolve C2).

## 16. Non-functional Requirements

| ID | Requisito percebido pelo negócio | Origem |
|---|---|---|
| NFR-001 | Volume inicial: 3 igrejas, ~520 membros, além de visitantes/frequentadores e contas. | Usuário |
| NFR-002 | Dados pessoais tratados conforme LGPD, com consentimento registrado e exclusão a pedido. | Análise + Usuário |
| NFR-003 | Auditoria de ações administrativas e exportações. | Análise |
| NFR-004 | Interface em português do Brasil. | ASSUMPTION-001 |
| NFR-005 | Funcionar nos navegadores comuns e em telas de celular (área do membro e formulário público). | Análise; confirmar |
| NFR-006 | Acessibilidade nível WCAG AA. | Análise; confirmar |

## 17. Acceptance Criteria

```text
AC-001
Dado uma pessoa já cadastrada na denominação,
quando a secretaria digitar nome e nascimento (ou CPF, ou e-mail) iguais no cadastro rápido,
então o sistema deve avisar a possível duplicidade antes de salvar.
```

```text
AC-002
Dado um administrador,
quando der acesso ao painel a uma pessoa com papel "Secretaria" e escopo de uma igreja,
então a pessoa recebe convite, define a própria senha e só abre fichas daquela igreja.
```

```text
AC-003
Dado uma secretaria sem permissão de dados sensíveis,
quando abrir a ficha de uma pessoa da sua igreja,
então não vê saúde, documentos, endereço/contatos nem dados de fé/conversão.
```

```text
AC-004
Dado uma mesclagem realizada,
quando a secretaria desfizer a mesclagem,
então as duas fichas voltam a existir com seus dados originais.
```

```text
AC-005
Dado um campo configurado "com aprovação",
quando o membro alterar esse campo na área do membro,
então a alteração fica pendente e só vale depois de aprovada pela secretaria.
```

```text
AC-006
Dado uma conta nova na área do membro,
quando a secretaria ainda não confirmou a Pessoa dessa conta,
então a conta não vê dados de nenhuma ficha.
```

```text
AC-007
Dado um membro transferido entre igrejas da denominação,
quando a transferência for concluída,
então a matrícula permanece a mesma.
```

## 18. Assumptions

| ID | Suposição |
|---|---|
| ASSUMPTION-001 | A plataforma opera só em português do Brasil nesta fase. |
| ASSUMPTION-002 | O administrador da denominação tem sempre acesso a dados sensíveis. |
| ASSUMPTION-003 | Os critérios de duplicidade iniciais são os recomendados pela CA: (nome completo E nascimento) OU CPF OU e-mail. |
| ASSUMPTION-004 | Papéis além de Administrador e Secretaria podem existir na Fundação, mas só esses dois operam o MVP. |

## 19. Open Questions

| ID | Pergunta | Status |
|---|---|---|
| OPEN-001 | O administrador cria/edita papéis livremente no MVP ou só existem os papéis prontos? | Aberta |
| OPEN-002 | Verificação em duas etapas (2FA) é obrigatória para administrador e secretaria? | Aberta |
| OPEN-003 | Quais campos são obrigatórios para visitante, frequentador e membro? (validar com as secretarias) | Aberta |
| OPEN-004 | Na busca, o que a secretaria vê de uma pessoa de outra igreja (nome, igreja, nascimento…)? | Aberta |
| OPEN-005 | Transferência entre igrejas: quem inicia e quem aprova? | Aberta |
| OPEN-006 | Uma pessoa pode ter vínculo em mais de uma igreja ao mesmo tempo? Pode ir de visitante direto a membro? | Aberta |
| OPEN-007 | O que o membro vê e edita na área do membro (inclui os próprios dados sensíveis?). | Aberta |
| OPEN-008 | Por qual canal o membro recebe convite e avisos (e-mail, WhatsApp, SMS)? | Aberta |
| OPEN-009 | Formulário público: quais formulários (visitante, membro, "aceitou Jesus"), passa por aprovação, perguntas configuráveis? | Aberta |
| OPEN-010 | Novos convertidos e datas comemorativas (aniversários, batismos, casamentos) entram no MVP? | Aberta |
| OPEN-011 | Importação por planilha (além da migração) é necessária no MVP? | Aberta |
| OPEN-012 | Crianças do Kids entram na migração como Pessoas agora, mesmo com o módulo Kids em fase posterior? | Aberta |
| OPEN-013 | LGPD: quem aprova exclusão de dados; o que acontece com o histórico de quem é excluído ou inativado; termos de consentimento. | Aberta |
| OPEN-014 | Exclusão de pessoa: prazo de recuperação (a análise sugere lixeira de 30 dias). | Aberta |
| OPEN-015 | Com Pessoas só no Oikora, como Células, Eventos, Kids e o app do inChurch (que continuam em uso) recebem pessoas novas e alteradas? | Aberta — `FUNCTIONAL_DECISION_REQUIRED` |
| OPEN-016 | A matrícula atual no inChurch é por denominação ou por igreja? (impacta a unificação na migração) | Aberta |
| OPEN-017 | Pessoas sem e-mail e sem celular (idosos, crianças) podem ter conta na área do membro? | Aberta |
| OPEN-018 | O membro acessa por site, por app, ou ambos? (necessidade de negócio; a forma técnica é do ADR) | Aberta |
| OPEN-019 | Qual definição única de contagem será usada para conferir a migração (ex.: "membros ativos por igreja")? | Aberta |

## 20. Architecture Considerations

```text
ARCHITECTURE_CONSIDERATION
Observation: hoje a plataforma atende uma denominação, mas há plano de oferecê-la a outras igrejas.
Why it may matter architecturally: isolamento de dados entre organizações e hierarquia variável (com ou sem regionais).
```

```text
ARCHITECTURE_CONSIDERATION
Observation: substituição módulo a módulo, com Pessoas só no Oikora enquanto outros módulos seguem no inChurch.
Why it may matter architecturally: pode exigir que mudanças de pessoas cheguem ao inChurch durante a convivência (OPEN-015).
```

```text
ARCHITECTURE_CONSIDERATION
Observation: mesclagem e importação precisam poder ser desfeitas; tudo auditado.
Why it may matter architecturally: exige guardar o estado anterior e o histórico das operações.
```

```text
ARCHITECTURE_CONSIDERATION
Observation: permissão por recurso × ação × escopo, com categoria separada de dados sensíveis.
Why it may matter architecturally: o filtro de visibilidade atravessa todas as telas, exportações e buscas.
```

```text
ARCHITECTURE_CONSIDERATION
Observation: área do membro usada por pessoas comuns, potencialmente pelo celular (OPEN-018).
Why it may matter architecturally: escolha entre site, app ou ambos.
```

```text
ARCHITECTURE_CONSIDERATION
Observation: migração a partir do inChurch, com unificação e relatório de conflitos.
Why it may matter architecturally: forma de extração e ensaio de migração repetível.
```

## 21. MVP Priorities

```text
MUST HAVE
- Organização (denominação + 3 igrejas)
- Conta ligada à Pessoa, convite ao painel, papéis Administrador e Secretaria com escopo, permissão de sensíveis, auditoria
- Cadastro rápido com duplicidade, ficha completável, vínculo/situação com motivo, matrícula única na denominação, linha do tempo
- Busca na denominação com visibilidade restrita; lista produtiva com exportação
- Mesclagem manual com desfazer
- Área do membro (auto-cadastro, vínculo confirmado, aprovação configurável por campo)
- Formulário público
- Pedido de exclusão de dados (LGPD)
- Migração do inChurch com relatório de conflitos
- Família/relacionamentos, funções com histórico, tipos de membro (Usuário: só campos personalizados ficam fora)

SHOULD HAVE
- Lixeira com recuperação

A CONFIRMAR (OPEN-010, OPEN-011)
- Novos convertidos, datas comemorativas, importação por planilha

OUT OF SCOPE
- Campos personalizados; demais módulos; pastores/recepção no painel; regionais
```

---

## Rastreabilidade

```text
Product Requirement (este documento)
 ↓
Architecture Decision
 ↓
ADR (OIK-ADR-NNNN)
 ↓
Technical Spec (spec-tecnica)
 ↓
Task
 ↓
Implementation
```
