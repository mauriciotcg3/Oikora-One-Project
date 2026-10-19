# Product Document: Oikora One — Fundação + Pessoas

**Status:** `READY_FOR_ARCHITECTURE` (validado pelo usuário em 2026-10-19)
**Origem:** `docs/analise/` (análise do painel inChurch e da Central de Ajuda, 12–13/10/2026) + entrevista com o usuário (Product Owner, 2026-10-16)
**Data:** 2026-10-16 (entrevista) · 2026-10-19 (validação)

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

- **(Usuário)** As 3 secretarias operam Pessoas só no Oikora e o inChurch deixa de ser usado para Pessoas — na virada conjunta com Células, Kids e Eventos (BR-014).
- **(Usuário)** Critério de pronto desta fase, antes da virada: ensaio de migração aprovado (contagens conferidas, conflitos revisados pelas 3 secretarias) **e** homologação de uso aprovada pelas secretarias (BR-017).
- Base migrada sem duplicados conhecidos e com contagens conferidas por uma definição única (contagem oficial: BR-012).

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
- Área do membro (site e app): auto-cadastro de conta, vínculo confirmado pela secretaria, campos configuráveis (oculto, leitura, edição direta ou com aprovação), responsável por familiar sem contato.
- Formulários públicos "visitante" e "aceitei Jesus" (link/QR Code), com revisão.
- Novos convertidos e datas comemorativas (aniversários, batismos, casamentos).
- Avisos por e-mail, WhatsApp e dentro da plataforma.
- LGPD: consentimento e pedido de exclusão de dados.
- Migração da base de Pessoas do inChurch (unificação, relatório de conflitos, preservação de histórico).

## 7. Out of Scope

- **Campos personalizados** (Usuário) — fase seguinte.
- Importação/atualização por planilha depois da migração (Usuário).
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
| FR-015 | A secretaria deve conseguir buscar pessoas em toda a denominação; de pessoas de outra igreja vê apenas nome, igreja e vínculo. |
| FR-016 | A secretaria deve conseguir ver a lista de possíveis duplicados e mesclá-los escolhendo campo a campo o valor que fica. |
| FR-017 | A secretaria deve conseguir desfazer uma mesclagem. |
| FR-018 | Qualquer pessoa deve conseguir criar uma conta na área do membro. |
| FR-019 | A secretaria deve confirmar a qual Pessoa uma conta nova pertence (ou criar a Pessoa) antes de a conta acessar dados da ficha. |
| FR-020 | O membro deve conseguir ver e atualizar a própria ficha na área do membro, pelo site (no celular) e pelo app. |
| FR-021 | O administrador deve conseguir configurar, para cada campo, se na área do membro ele é oculto, só leitura, editável direto ou editável com aprovação. |
| FR-022 | A secretaria deve conseguir aprovar ou recusar alterações pendentes campo a campo, com justificativa na recusa, e o membro deve ser avisado. |
| FR-023 | Uma pessoa deve conseguir se cadastrar sem conta por dois formulários públicos de cada igreja (link ou QR Code): "visitante" e "aceitei Jesus"; o cadastro passa por revisão da secretaria. |
| FR-024 | O membro deve conseguir pedir a exclusão dos seus dados; a secretaria da igreja dele aprova ou recusa. |
| FR-025 | A base de Pessoas do inChurch deve ser migrada unificando membros, visitantes/frequentadores, usuários do app e usuários do painel (crianças: documento do Kids). |
| FR-026 | A migração deve gerar um relatório de conflitos para a secretaria revisar antes da virada. |
| FR-027 | A migração deve preservar matrícula, linha do tempo, funções com datas e consentimentos. |
| FR-028 | Excluir uma pessoa deve exigir confirmação; a pessoa fica na lixeira e pode ser recuperada por 30 dias. |
| FR-029 | O administrador deve usar verificação em duas etapas para entrar no painel; a secretaria pode ativá-la. |
| FR-030 | O administrador deve conseguir editar as permissões dos papéis prontos e criar papéis novos. |
| FR-031 | A secretaria deve conseguir solicitar a transferência de uma pessoa de outra igreja para a sua; a secretaria de origem aprova ou recusa. |
| FR-032 | A secretaria deve conseguir acompanhar a lista de novos convertidos (quem aceitou Jesus, com data e canal) da sua igreja. |
| FR-033 | A secretaria deve conseguir ver aniversariantes do dia, da semana e do mês na tela e exportá-los. |
| FR-034 | A secretaria deve conseguir ver aniversários de batismo e de casamento do dia, da semana e do mês na tela e exportá-los. |
| FR-035 | Uma pessoa com conta deve conseguir ver e atualizar a ficha de um familiar sem e-mail e sem celular, quando a secretaria a reconhecer como responsável por ele. |
| FR-036 | Convites e avisos devem chegar por e-mail, por WhatsApp e como aviso dentro da plataforma. |

## 10. Business Rules

| ID | Regra | Origem |
|---|---|---|
| BR-001 | Uma pessoa existe uma única vez na denominação. | Análise + Usuário |
| BR-002 | Uma conta de acesso pertence a no máximo uma Pessoa; a ligação é confirmada pela secretaria, nunca deduzida por e-mail igual. | Análise + Usuário |
| BR-003 | Vínculos possíveis: visitante, frequentador, membro. Só membro tem matrícula. | CA + Usuário |
| BR-004 | A matrícula é única na denominação e acompanha o membro se mudar de igreja local. | Usuário |
| BR-005 | Mudança de vínculo, inativação e reativação exigem motivo e geram evento na linha do tempo. | CA (manter) |
| BR-006 | A secretaria só abre a ficha de pessoas com vínculo na sua igreja; na busca, de pessoas de outras igrejas vê apenas nome, igreja e vínculo. | Usuário |
| BR-007 | Saúde, documentos, endereço e contatos, e dados de fé/conversão só são exibidos a quem tem permissão de dados sensíveis. | Usuário |
| BR-008 | A mesclagem de duplicados é sempre manual e pode ser desfeita. | Usuário |
| BR-009 | Na área do membro, cada campo segue a configuração: oculto, só leitura, editável direto ou editável com aprovação; toda recusa tem justificativa e avisa o membro. | Usuário |
| BR-010 | Ninguém define a senha de outra pessoa; acesso ao painel é por convite com expiração. | Análise (resolve C5) |
| BR-011 | As regras de obrigatoriedade são as mesmas em todos os canais (painel, formulário público, área do membro, migração). Ponto de partida: os campos obrigatórios configurados hoje no inChurch (lista a levantar, `OPEN-003`). | Análise (resolve C8) + Usuário |
| BR-012 | Todo número exibido informa o critério. A contagem oficial é "pessoas ativas por vínculo (visitante, frequentador, membro) em cada igreja"; o total da denominação é a soma das igrejas. A mesma definição confere a migração. | Análise (resolve C7) + Usuário |
| BR-013 | Ações administrativas, mudanças de acesso e exportações são registradas em auditoria. | Análise |
| BR-014 | A virada de Pessoas para o Oikora só acontece quando Células, Kids e Eventos também estiverem prontos no Oikora; até lá, o inChurch continua sendo a base oficial de pessoas. Não há operação com Pessoas no Oikora e os demais módulos no inChurch. | Usuário (resolve `OPEN-015`) |
| BR-015 | Uma pessoa tem vínculo com uma só igreja local por vez; mudar de igreja é transferência (BR-019). | Usuário |
| BR-016 | Verificação em duas etapas é obrigatória para o administrador da denominação e opcional para a secretaria. | Usuário |
| BR-017 | Até a virada conjunta, o Oikora é usado só em teste/homologação; nenhum canal (formulário público, área do membro, painel) entra em uso real antes dela. | Usuário |
| BR-018 | Os papéis Administrador e Secretaria vêm prontos; o administrador pode editar suas permissões e criar papéis novos. | Usuário |
| BR-019 | Transferência entre igrejas: a secretaria de destino solicita e a secretaria de origem aprova ou recusa; a matrícula se mantém (BR-004) e a transferência entra na linha do tempo. | Usuário |
| BR-020 | O vínculo só avança passo a passo: Visitante → Frequentador → Membro. Não pula etapa e não volta; quem deixa de participar é inativado com motivo. | Usuário |
| BR-021 | A pessoa fica na lista de novos convertidos até a secretaria registrar o batismo ou mudar o vínculo; não há prazo fixo. O registro de conversão continua na ficha. | Usuário (corrige o prazo de 3 meses do inChurch) |
| BR-022 | Pessoa excluída pela secretaria fica 30 dias na lixeira, recuperável; depois é removida. Exclusão a pedido do titular segue BR-023. | Usuário |
| BR-023 | O pedido de exclusão de dados (LGPD) é aprovado ou recusado pela secretaria da igreja da pessoa, com justificativa na recusa. O que acontece com o histórico depois da exclusão será definido com orientação jurídica (`OPEN-013`). | Usuário |
| BR-024 | Pessoa sem e-mail e sem celular não tem conta própria; um familiar com conta, reconhecido pela secretaria como responsável, pode ver e atualizar a ficha dela, com as mesmas regras de campo (BR-009). | Usuário |
| BR-025 | Cadastros feitos pelos formulários públicos ("visitante" e "aceitei Jesus") entram como visitante e passam pela revisão da secretaria; o formulário "aceitei Jesus" também inclui a pessoa em novos convertidos. | Usuário |

## 11. Processes / Flows

```text
FLOW-001 — Cadastro rápido
Trigger: pessoa nova chega (culto, visita, ligação).
Actor action: secretaria informa nome, celular ou e-mail, vínculo, igreja, como chegou.
System behavior: verifica duplicidade na denominação enquanto digita.
Result: Pessoa criada (ou ficha existente aberta); evento de entrada na linha do tempo.
Alternative/error flows: suspeita de duplicado → "Abrir ficha" ou "É outra pessoa";
  duplicado em outra igreja → secretaria vê nome, igreja e vínculo e pode pedir transferência (FLOW-009).
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
FLOW-004 — Formulários públicos ("visitante" e "aceitei Jesus")
Trigger: pessoa acessa o link/QR Code de uma igreja.
System behavior: aplica as mesmas regras de obrigatoriedade e de duplicidade; o cadastro vai para revisão da secretaria.
Actor action (secretaria): revisa, trata possível duplicado e confirma.
Result: Pessoa criada como visitante; pelo formulário "aceitei Jesus", também entra em novos convertidos (BR-025).
Alternative/error flows: duplicado → mesclagem (FLOW-006); cadastro inválido → secretaria descarta.
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
Trigger: preparação da virada conjunta (Pessoas, Células, Kids e Eventos — BR-014).
System behavior: extrai as pessoas do inChurch, cruza por e-mail, CPF, celular e nome + nascimento, gera relatório de conflitos.
Actor action: secretarias revisam conflitos; contagens conferidas por definição única.
Result: base unificada no Oikora; a partir da virada, Pessoas no inChurch fica só para consulta.
```

```text
FLOW-008 — Pedido de exclusão de dados (LGPD)
Trigger: membro pede exclusão.
Actor action: secretaria da igreja da pessoa aprova ou recusa, com justificativa na recusa (BR-023).
Result: dados excluídos conforme a política definida com orientação jurídica (OPEN-013); pedido registrado e titular avisado.
```

```text
FLOW-010 — Responsável por pessoa sem contato
Trigger: familiar com conta pede para cuidar da ficha de uma pessoa sem e-mail e sem celular.
Actor action: secretaria reconhece o familiar como responsável.
Result: o responsável vê e atualiza a ficha da pessoa na área do membro, seguindo as regras de campo (BR-024).
Alternative/error flows: secretaria recusa → nada muda; secretaria pode retirar a responsabilidade a qualquer momento.
```

```text
FLOW-009 — Transferência entre igrejas
Trigger: pessoa vinculada a outra igreja passa a frequentar a igreja da secretaria.
Actor action: secretaria de destino encontra a pessoa na busca e solicita a transferência.
System behavior: pedido vai para a secretaria da igreja de origem.
Result: aprovada → vínculo passa para a igreja de destino, matrícula mantida, evento na linha do tempo;
  a secretaria de destino passa a abrir a ficha e a de origem deixa de abrir.
Alternative/error flows: origem recusa com justificativa → vínculo não muda; destino é avisado.
```

## 12. States / Lifecycles

```text
Vínculo da Pessoa (uma igreja por vez, BR-015): Visitante → Frequentador → Membro
Situação: Ativo ⇄ Inativo (sempre com motivo)
Transições proibidas: pular etapa (Visitante → Membro) e voltar (Membro → Frequentador, Frequentador → Visitante) — BR-020
Saída: inativação com motivo; reativação volta ao mesmo vínculo
Quem executa: secretaria da igreja do vínculo; administrador
```

```text
Conta da área do membro: Pendente de vínculo → Vinculada → Bloqueada
Quem executa: secretaria (vincular/recusar); administrador (bloquear)
```

```text
Pedido de transferência: Solicitado → Aprovado | Recusado (com justificativa)
Quem executa: secretaria de destino (solicitar); secretaria de origem (aprovar/recusar)
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

```text
Pessoa excluída: Na lixeira (30 dias) → Recuperada | Removida
Quem executa: secretaria (excluir, recuperar)
```

```text
Pedido de exclusão de dados: Solicitado → Aprovado → Concluído | Recusado (com justificativa)
Quem executa: titular (solicitar); secretaria da igreja (aprovar/recusar)
```

```text
Cadastro por formulário público: Recebido → Confirmado | Descartado
Quem executa: secretaria
```

## 13. Functional Permissions

```text
Administrador da denominação pode gerir igrejas, papéis (editar e criar), acessos e configurações de Pessoas
Administrador da denominação pode ver pessoas das 3 igrejas
Secretaria pode cadastrar, editar, mudar vínculo, inativar, mesclar e exportar pessoas da sua igreja
Secretaria pode buscar pessoas de outras igrejas (nome, igreja, vínculo), mas não abrir a ficha
Secretaria pode solicitar transferência para a sua igreja e aprovar/recusar transferências saindo da sua igreja
Secretaria sem permissão de sensíveis não vê saúde, documentos, endereço/contatos, dados de fé/conversão
Secretaria pode aprovar/recusar pedidos de exclusão de dados e cadastros de formulário público da sua igreja
Secretaria pode reconhecer ou retirar um responsável por pessoa sem contato
Membro pode ver e alterar só a própria ficha (e a de quem ele é responsável), conforme a configuração de cada campo
Membro não vê campos configurados como ocultos
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
Relacionamento (família; inclui "responsável por" pessoa sem contato)
Conversão (aceitou Jesus, data, canal — culto, formulário, etc.) → lista de novos convertidos
Configuração de campo na área do membro (oculto, só leitura, editável direto, editável com aprovação)
Função (função, desde, até)
Evento de linha do tempo (atividade, motivo, autor, data de ocorrência)
Alteração pendente (campo, valor proposto, origem, status, justificativa)
Consentimento (finalidade, data, origem)
Conta de acesso (ligada a uma Pessoa) → atribuições de papel com escopo
Papel → permissões (recurso × ação × escopo, inclui sensíveis)
```

Campos herdados do inChurch (assistente de 5 etapas, CA). Campos obrigatórios: os configurados hoje no inChurch (BR-011; lista a levantar em `OPEN-003`).

## 15. Integrations

- **Extração da base do inChurch** para migração (a análise indica a API pública do inChurch ou a exportação "em formato de importação").
- **Envio de convites e avisos** (convite ao painel, conta vinculada, alteração aprovada/recusada, transferência, pedido de exclusão) por **e-mail**, **WhatsApp** e **aviso dentro da plataforma** (Usuário).
- **Publicação do app** nas lojas de aplicativos, além do site (Usuário).
- Preenchimento de endereço a partir do CEP (Análise; resolve C2).

## 16. Non-functional Requirements

| ID | Requisito percebido pelo negócio | Origem |
|---|---|---|
| NFR-001 | Volume inicial: 3 igrejas, ~520 membros, além de visitantes/frequentadores e contas. | Usuário |
| NFR-002 | Dados pessoais tratados conforme LGPD, com consentimento registrado e exclusão a pedido. | Análise + Usuário |
| NFR-003 | Auditoria de ações administrativas e exportações. | Análise |
| NFR-004 | Interface em português do Brasil. | ASSUMPTION-001 |
| NFR-005 | Área do membro disponível como site (funcionando bem no navegador do celular) e como app nas lojas; formulários públicos funcionam no celular. | Usuário |
| NFR-006 | Acessibilidade nível WCAG AA. | Análise + Usuário |

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

```text
AC-008
Dado uma pessoa com vínculo "Visitante",
quando a secretaria tentar mudá-la direto para "Membro",
então o sistema não permite e indica que ela precisa passar por "Frequentador".
```

```text
AC-009
Dado uma pessoa que se cadastrou pelo formulário "aceitei Jesus",
quando a secretaria confirmar o cadastro,
então a pessoa aparece como visitante e na lista de novos convertidos até o batismo ou a mudança de vínculo.
```

```text
AC-010
Dado uma pessoa excluída pela secretaria há menos de 30 dias,
quando a secretaria abrir a lixeira,
então consegue recuperá-la com todos os dados.
```

```text
AC-011
Dado um campo configurado como "oculto" na área do membro,
quando o membro abrir a própria ficha,
então esse campo não aparece.
```

```text
AC-012
Dado o ensaio de migração,
quando as contagens forem comparadas,
então "pessoas ativas por vínculo em cada igreja" no Oikora bate com o inChurch, e as diferenças estão explicadas no relatório de conflitos.
```

## 18. Assumptions

| ID | Suposição |
|---|---|
| ASSUMPTION-001 | A plataforma opera só em português do Brasil nesta fase. |
| ASSUMPTION-002 | O administrador da denominação tem sempre acesso a dados sensíveis. **Confirmada pelo usuário (2026-10-16).** |
| ASSUMPTION-003 | Os critérios de duplicidade iniciais são os recomendados pela CA: (nome completo E nascimento) OU CPF OU e-mail. **Confirmada pelo usuário (2026-10-16).** |
| ASSUMPTION-004 | No MVP só operam o painel Administrador e Secretaria, mesmo que o admin crie outros papéis. |
| ASSUMPTION-005 | O cadastro de Pessoa já comporta crianças ligadas a responsáveis (relacionamento), para o Kids usar na virada conjunta. |
| ASSUMPTION-006 | O cadastro rápido (FR-006) salva com o mínimo (nome, celular ou e-mail, vínculo, igreja); os obrigatórios de BR-011 definem quando a ficha está completa (barra de completude), e não impedem o cadastro inicial. **Confirmada pelo usuário (2026-10-16).** |
| ASSUMPTION-007 | Pessoas migradas mantêm o vínculo atual do inChurch, mesmo que o histórico não tenha passado por todas as etapas de BR-020; a regra vale para mudanças feitas no Oikora. **Confirmada pelo usuário (2026-10-16).** |
| ASSUMPTION-008 | Quem vem de outra denominação entra como visitante e segue BR-020 (com o tipo de entrada, ex.: "Transferência", registrado na ficha). **Confirmada pelo usuário (2026-10-16).** |

## 19. Open Questions

| ID | Pergunta | Status |
|---|---|---|
| OPEN-001 | Papéis livres ou só prontos? | Resolvida (2026-10-16) — decisão: Administrador e Secretaria vêm prontos e editáveis; o admin pode criar papéis (BR-018). |
| OPEN-002 | Verificação em duas etapas é obrigatória? | Resolvida (2026-10-16) — decisão: obrigatória só para o administrador (BR-016). |
| OPEN-003 | Quais campos são obrigatórios? | Parcial (2026-10-16) — decisão: os mesmos configurados hoje no inChurch (BR-011). Falta levantar a lista exata nas Preferências › Membros do inChurch (a análise cita 15 campos sem listá-los). Não bloqueia a arquitetura. |
| OPEN-004 | O que a secretaria vê de uma pessoa de outra igreja na busca? | Resolvida (2026-10-16) — decisão: nome, igreja e vínculo (BR-006). |
| OPEN-005 | Transferência entre igrejas: quem inicia e quem aprova? | Resolvida (2026-10-16) — decisão: secretaria de destino pede, secretaria de origem aprova (FLOW-009). |
| OPEN-006 | Vínculo em mais de uma igreja? Pular ou voltar etapa? | Resolvida (2026-10-16) — decisão: uma igreja por vez (BR-015); só avança passo a passo, sem voltar; saída por inativação (BR-020). |
| OPEN-007 | O que o membro vê e edita na área do membro? | Resolvida (2026-10-16) — decisão: configurável por campo — oculto, só leitura, editável direto ou com aprovação (BR-009). |
| OPEN-008 | Canal de convites e avisos? | Resolvida (2026-10-16) — decisão: e-mail, WhatsApp e aviso dentro da plataforma (FR-036). |
| OPEN-009 | Como funciona o formulário público? | Resolvida (2026-10-16) — decisão: formulários "visitante" e "aceitei Jesus", ambos com revisão da secretaria (BR-025). |
| OPEN-010 | Novos convertidos e datas comemorativas entram no MVP? | Resolvida (2026-10-16) — decisão: sim, os três (FR-032–034); novos convertidos ficam na lista até batismo ou mudança de vínculo (BR-021). |
| OPEN-011 | Importação por planilha no MVP? | Resolvida (2026-10-16) — decisão: fora do MVP (§7). |
| OPEN-012 | Crianças do Kids entram na migração como Pessoas? | Resolvida (2026-10-16) — decisão: migração das crianças fica no documento do Kids; este documento só garante que Pessoa suporta crianças e responsáveis (ASSUMPTION-005). |
| OPEN-013 | LGPD: o que acontece com o histórico da igreja quando os dados de uma pessoa são excluídos a pedido; termos de consentimento. | Parcial (2026-10-16) — decisão: a secretaria da igreja aprova o pedido (BR-023). Falta: política de histórico/retenção e termos, a definir com orientação jurídica. Não bloqueia a arquitetura; bloqueia o uso real (virada). |
| OPEN-014 | Prazo de recuperação de pessoa excluída? | Resolvida (2026-10-16) — decisão: 30 dias na lixeira (BR-022). |
| OPEN-015 | Com Pessoas só no Oikora, como Células, Eventos, Kids e o app do inChurch recebem pessoas novas e alteradas? | Resolvida (2026-10-16) — decisão: a virada de Pessoas espera Células, Kids e Eventos ficarem prontos (BR-014); sem convivência com Pessoas dividida. |
| OPEN-016 | A matrícula atual no inChurch é por denominação ou por igreja? | Aberta — usuário não sabe; verificar no inChurch antes do ensaio de migração. Se for por igreja e houver números repetidos, definir regra de renumeração. Não bloqueia a arquitetura. |
| OPEN-017 | Pessoas sem e-mail e sem celular podem ter conta? | Resolvida (2026-10-16) — decisão: não têm conta própria; um familiar responsável cuida da ficha (BR-024, FLOW-010). |
| OPEN-018 | O membro acessa por site, por app, ou ambos? | Resolvida (2026-10-16) — decisão: os dois (NFR-005). |
| OPEN-019 | Definição única de contagem? | Resolvida (2026-10-16) — decisão: pessoas ativas por vínculo e igreja; denominação = soma (BR-012). |
| OPEN-020 | Até a virada, o Oikora é usado só em teste/homologação ou alguma parte vai para uso real? | Resolvida (2026-10-16) — decisão: só teste/homologação até a virada (BR-017). |
| OPEN-021 | Como a Fundação + Pessoas é dada como pronta antes da virada? | Resolvida (2026-10-16) — decisão: ensaio de migração aprovado e homologação pelas secretarias (§4). |

## 20. Architecture Considerations

```text
ARCHITECTURE_CONSIDERATION
Observation: hoje a plataforma atende uma denominação, mas há plano de oferecê-la a outras igrejas.
Why it may matter architecturally: isolamento de dados entre organizações e hierarquia variável (com ou sem regionais).
```

```text
ARCHITECTURE_CONSIDERATION
Observation: substituição por módulos, mas Pessoas, Células, Kids e Eventos viram juntos (BR-014); quando viram Financeiro e Conteúdo ainda não foi definido.
Why it may matter architecturally: dispensa sincronizar pessoas com o inChurch, mas a migração precisa trazer junto os dados desses quatro módulos e ser ensaiada até a virada.
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
Observation: área do membro como site e como app nas lojas (NFR-005), usada por pessoas comuns e por responsáveis de familiares.
Why it may matter architecturally: dois canais de acesso com as mesmas regras de campo e de aprovação; publicação nas lojas.
```

```text
ARCHITECTURE_CONSIDERATION
Observation: avisos por e-mail, WhatsApp e dentro da plataforma (FR-036).
Why it may matter architecturally: envio por múltiplos canais, com comportamento em falha a definir.
```

```text
ARCHITECTURE_CONSIDERATION
Observation: exclusão a pedido (LGPD) com política de histórico ainda a definir com orientação jurídica (OPEN-013); lixeira de 30 dias.
Why it may matter architecturally: pode exigir anonimizar mantendo contagens/histórico, ou apagar por completo — a solução deve suportar a decisão que vier.
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
- Área do membro, site e app (auto-cadastro, vínculo confirmado, campos configuráveis, responsável por familiar sem contato)
- Formulários públicos "visitante" e "aceitei Jesus", com revisão
- Pedido de exclusão de dados (LGPD) aprovado pela secretaria; lixeira de 30 dias
- Avisos por e-mail, WhatsApp e dentro da plataforma
- Novos convertidos; aniversariantes; aniversários de batismo e casamento
- Migração do inChurch com relatório de conflitos e contagem oficial
- Família/relacionamentos, funções com histórico, tipos de membro (Usuário: só campos personalizados ficam fora)

OUT OF SCOPE
- Campos personalizados; importação por planilha após a migração; demais módulos; pastores/recepção no painel; regionais
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

## Handoff

```text
READY_FOR_ARCHITECTURE

Product document: docs/product/0001-fundacao-e-pessoas.md
Validated by user: 2026-10-19
Resolved questions: OPEN-001, 002, 004–012, 014, 015, 017–021 (decisões registradas em §19)
Remaining non-blocking items:
  - OPEN-003 — levantar no inChurch a lista exata de campos obrigatórios (BR-011)
  - OPEN-013 — política de histórico/retenção na exclusão LGPD, com orientação jurídica (bloqueia a virada, não a arquitetura)
  - OPEN-016 — numeração atual da matrícula no inChurch (antes do ensaio de migração)
  - ASSUMPTION-001, 004, 005 (não confirmadas explicitamente)
Architecture considerations (§20):
  - expansão futura para outras igrejas (isolamento de dados, hierarquia variável)
  - virada conjunta Pessoas + Células + Kids + Eventos; migração ensaiável a partir do inChurch
  - mesclagem desfazível e auditoria
  - permissão recurso × ação × escopo com categoria de dados sensíveis
  - área do membro como site e app nas lojas
  - avisos por e-mail, WhatsApp e dentro da plataforma
  - exclusão LGPD com política ainda aberta (anonimizar ou apagar)
Not a decision: docs/analise/especificacao-tecnica-php.md (Laravel + Filament) — só vale após ADR OIK.
Recommended next step: Architecture / ADR analysis (ADR Arquiteto, sessão nova, a pedido do usuário)
```
