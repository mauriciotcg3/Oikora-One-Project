# OIK-SPEC-0001: Fundação + Pessoas no painel

**Status:** Aprovada (usuário, 2026-10-21)
**Data:** 2026-10-21
**Fontes usadas:** `docs/product/0001-fundacao-e-pessoas.md` (citado como **P**, com o ID do item: `P FR-006`, `P §7`); OIK-ADR-0001 a OIK-ADR-0011 (citados como `ADR-000N §i` = OIK-ADR-000N, item *i* da Decisão); respostas do usuário nesta conversa (citadas como `U-NN`, lista abaixo).

### Respostas do usuário (2026-10-21)

| ID | Resposta |
|---|---|
| U-01 | O documento de produto 0001 vira **4 Specs**: **0001** = bootstrap do monorepo + CI + Fundação + Pessoas no painel + avisos por e-mail, WhatsApp e caixa; **0002** = área do membro web + formulários públicos + LGPD; **0003** = app nas lojas; **0004** = migração do inChurch. |
| U-02 | Finalidades de consentimento: **mensagens automáticas**, **uso de imagem**, **dados de saúde**, **comunicação da igreja**. |
| U-03 | Os parabéns automáticos (aniversário, batismo, casamento) **não** entram na 0001; ela só mostra e exporta as datas. |
| U-04 | O limite mensal de WhatsApp por organização fica **em aberto**: a Spec cria o contador, o campo configurável e os avisos de 80%/100%; sem valor preenchido = sem limite. |
| U-05 | OPEN-003 continua **em aberto**: a Spec cria a regra configurável por vínculo e a barra de completude, e a lista é preenchida quando for levantada. |
| U-06 | Os campos obrigatórios por vínculo são configurados pelo **administrador, por organização**, numa tela do painel com permissão própria e auditoria. |
| U-07 | OPEN-016 não foi conferido; o **formato da matrícula automática fica em aberto**. |
| U-08 | Termos e consentimentos entram na **0002** (com a LGPD), não na 0001. |
| U-09 | "Como chegou", motivos (entrada, saída, inativação), tipos de membro e funções são **listas mantidas pelo administrador por organização**; os valores iniciais ficam em aberto. |
| U-10 | A exportação sai em **XLSX ou CSV**, à escolha de quem exporta. |
| U-11 | A autorização temporária de suporte da plataforma fica para uma **Spec posterior**; a 0001 só prevê as colunas de auditoria. |
| U-12 | Hospedagem e telemetria externa ficam numa **Spec própria de infraestrutura**. A 0001 entrega o ambiente local (Docker Compose com PG 17), o CI completo e a instrumentação no código. |
| U-13 | Servidor PHP: **PHP-FPM** atrás do Caddy. |
| U-14 | PHPStan/Larastan **nível 8**. |
| U-15 | Versões: a task de bootstrap fixa a **última versão estável** de cada item, dentro do mínimo dos ADRs, num arquivo único de versões, e atualiza o `plan.md`. |
| U-16 | A única ação em massa da lista é **exportar selecionados**. |
| U-17 | Filtros da lista: igreja, vínculo e situação; tipo de membro e função; ficha incompleta; aniversariantes do mês (P FR-014). |
| U-18 | O modo da matrícula (automática ou manual) é uma **configuração da organização**. Enquanto o formato automático estiver em aberto, só o modo manual funciona: a secretaria informa a matrícula ao passar a pessoa para Membro. |
| U-19 | Arquivos no desenvolvimento e no CI: **MinIO** no Docker Compose (compatível com S3). |
| U-20 | Cadastro rápido de alguém que está na lixeira: o detector de duplicidade também olha a lixeira e oferece **"Recuperar ficha"** em vez de criar outra pessoa. |
| U-21 | Transferência: **só um pedido pendente por pessoa**; outro pedido para a mesma pessoa é recusado com mensagem enquanto o primeiro estiver pendente. |
| U-22 | Limites de tentativas de login: **mecanismo configurável**, valores em aberto (Spec de infraestrutura/operação). |
| U-23 | Preenchimento de endereço pelo CEP **fora da 0001**: o endereço é digitado; o provedor de CEP vira ADR antes da 0002. |
| U-24 | Tipos de relacionamento: **lista fixa no código** — cônjuge; pai/mãe ↔ filho(a); irmão(ã); avô/avó ↔ neto(a); responsável legal ↔ dependente; outro (com descrição). Cada relação grava a inversa automaticamente. |
| U-25 | O arquivo de exportação fica disponível por **24 horas**; depois um job o apaga e a exportação continua na auditoria. |
| U-26 | Igrejas: **só criar e editar** na 0001; excluir/desativar vira pergunta ao Product Owner. |
| U-27 | Mesclar duas pessoas em que **ambas têm conta**: recusado (`mesclagem_contas_conflitantes`); o administrador revoga um dos acessos antes. (Revisão do plano.) |
| U-28 | Motivos em três listas (`motivo_vinculo`, `motivo_inativacao`, `motivo_reativacao`); "como chegou" é o motivo do evento de entrada. (Revisão do plano.) |
| U-29 | Bibliotecas locais aprovadas: OpenSpout, `libphonenumber-for-php`, adapters Meta/Twilio pelo cliente HTTP do Laravel sem SDK, `Password::uncompromised()` (HIBP, só prefixo de hash). (Revisão do plano.) |
| U-30 | Ajustes da análise de consistência aprovados (2026-10-23): `acessos` nasce com as contas; a mesclagem limpa CPF, `cpf_hash` e matrícula da absorvida (o snapshot guarda os valores); task própria para consultar a auditoria; `worker-geral` consome `avisos,webhooks,default`; contagens informam o critério; sessão base de 30 dias renovada com o uso. |
| U-31 | Eventos de autenticação vão para `eventos_autenticacao`, tabela de identidade fora da RLS por organização, só inclusão, retenção de 1 ano; a elevação e o que ocorre dentro de uma organização também vão para a auditoria dela. |
| U-32 | O login feito pela entrada `painel` já abre a elevação (2h/12h); o diálogo de elevação só aparece quando ela expira. |
| U-33 | Quando a operação de mesclagem expira (90 dias), o job apaga a ficha absorvida junto com o snapshot; até lá, a purga da lixeira a ignora. |
| U-34 | Branches por task: `t-NN-<slug>`, curtas, a partir da `main`. |

## Objetivo

Entregar a base da plataforma e o cadastro de Pessoas operado pelo painel, para que a secretaria e o administrador da denominação (P §5, ACTOR-001/002) mantenham uma ficha única por pessoa na denominação (P §3 obj. 1 e 3), com acesso seguro e granular sem senha definida por terceiros (P §3 obj. 2) e com tudo auditado (P BR-013). Resolve, no painel, os problemas do inChurch de pessoa fragmentada, importação e mesclagem sem volta, permissões sem restrição e baixa produtividade da lista (P §2).

**Critério de pronto desta Spec:** todos os critérios de aceite abaixo passam no CI. A homologação de uso pelas secretarias (P §4, P BR-017) depende do ambiente de homologação, que vem na Spec de infraestrutura (U-12), e das Specs 0002–0004 (U-01).

## Escopo (o que será construído)

- **E-01 Bootstrap do monorepo.** Workspace pnpm na raiz do `Oikora-One-Project` com `api/` (Laravel), `web/` (projeto Vite, só com a entrada `painel` nesta Spec) e `packages/api-contrato/` — ADR-0006 §1, ADR-0001 §6, U-01. Ambiente local em Docker Compose com PostgreSQL 17 (`pg_trgm`, `unaccent`), Caddy + PHP-FPM e MinIO — ADR-0011 §4, ADR-0002 §1, U-13, U-19. Arquivo único de versões — ADR-0011 §3, U-15.
- **E-02 CI e Pre-Review Gate.** GitHub Actions no PR, rodando só as partes alteradas: Pint, PHPStan/Larastan nível 8, Pest com PostgreSQL real, Deptrac, contrato OpenAPI gerado = versionado + `oasdiff`, `tsc`, ESLint, Vitest e build com `size-limit` do `painel` ≤ 350 KB gzip. Também cobertura ≥ 80% nas linhas alteradas, gitleaks, `composer audit`/`pnpm audit` e Dependabot. Execução noturna: Playwright + axe. Scripts locais `composer gate` e `pnpm gate` — ADR-0010 §1, §4, §5, §7, §8, U-14.
- **E-03 Isolamento por organização.** `organizacao_id` com RLS habilitada e forçada, contexto por transação (`set_config(..., true)`), contexto ausente = erro, papéis de banco (dono, aplicação, plataforma), FKs compostas, chave `bigint` + UUID público e teste de catálogo da RLS — ADR-0002 §2–§9, §12; ADR-0011 §3.
- **E-04 Organização e igrejas.** Provisionamento da organização por comando de plataforma — ADR-0002 §5 — com estado `ativa` ou `em_migracao` (ADR-0009 §5). Cadastro e manutenção das igrejas locais — P FR-001. Configuração da organização: nome, logo, cor primária (recusada sem contraste AA) e fuso — ADR-0006 §7, ADR-0008 §14. Regionais existem só no schema, sem tela — ADR-0002 §8, P §7.
- **E-05 Identidade e acesso ao painel.** Conta global, login por e-mail ou celular + senha, verificação de contato, recuperação de senha — ADR-0003 §1, §4, §8. Convite ao painel com expiração e aceite com senha própria — P FR-002, FR-003, BR-010, ADR-0003 §5. Revogação imediata — P FR-004, ADR-0003 §11. 2FA — P FR-029, BR-016, ADR-0003 §7. Elevação do painel — ADR-0003 §9. Organização ativa guardada no servidor, com 409 quando diverge — ADR-0003 §12, ADR-0006 §10.
- **E-06 Autorização e dados sensíveis.** Catálogo de permissões `recurso.acao`; papéis prontos Administrador e Secretaria, editáveis; criação de papéis — P FR-030, BR-018, ADR-0004 §1–§3. Alcance por requisição, filtro global por igreja, busca restrita na denominação, projeção de sensíveis e cifra de CPF/RG/saúde com `cpf_hash` — P BR-006, BR-007, ADR-0004 §4–§9, §13.
- **E-07 Auditoria.** Catálogo de eventos, gravação na mesma transação, só inclusão, partição mensal e consulta pelo administrador — P FR-005, BR-013, ADR-0005 §1–§3, §5, §10.
- **E-08 Avisos (sem push e sem automáticas).** Módulo Avisos com catálogo de tipos das categorias `codigo`, `convite` e `servico` — ADR-0008 §1, U-03. Também: caixa de saída transacional, filas `database` com worker dedicado a `codigos`, idempotência, middleware de job com contexto, agendador, caixa de avisos no painel, e-mail (Postmark), WhatsApp (Meta: número por organização ligado pelo administrador e número da plataforma só para códigos), SMS (Twilio, só reserva de código), preferências e descadastro, janela diurna, webhooks, uso e limite com valor em aberto, registros e retenção — P FR-036, ADR-0008 §2–§7, §9–§17, U-04.
- **E-09 Cadastro de Pessoas.** Cadastro rápido com alerta de duplicidade — P FR-006, FR-007. Ficha por seções com completude — P FR-008. Vínculo e situação com motivo, avançando passo a passo — P FR-009, BR-005, BR-020. Matrícula — P FR-010, BR-004, U-07, U-18. Linha do tempo — P FR-011, ADR-0005 §1. Família e relacionamentos (tipos fixos), incluindo criança ligada a responsável — P FR-012, ASSUMPTION-005, U-24. Funções com período e tipo de membro — P FR-013. Listas de valores — U-09. Obrigatórios por vínculo — P BR-011, U-05, U-06. Foto da ficha — P §14.
- **E-10 Lista, busca e exportação.** Lista com 25/50/100 por página e filtros na URL — P FR-014, ADR-0006 §3, U-17. Busca na denominação — P FR-015, BR-006, ADR-0004 §6. Exportação XLSX/CSV por job, com download no painel por 24h — P FR-014, U-10, U-25, ADR-0004 §9. Exportar selecionados — U-16.
- **E-11 Duplicados e mesclagem.** Lista de possíveis duplicados, mesclagem campo a campo e desfazer — P FR-016, FR-017, BR-008, ADR-0005 §6.
- **E-12 Transferência entre igrejas.** P FR-031, BR-019, FLOW-009.
- **E-13 Lixeira.** 30 dias, recuperar, purga pelo removedor de Pessoas — P FR-028, BR-022, ADR-0005 §7, §8.
- **E-14 Novos convertidos e datas comemorativas.** Na tela e em exportação — P FR-032, FR-033, FR-034, BR-021, U-03.
- **E-15 Instrumentação no código.** `X-Request-Id`, logs JSON com redação, SDK OpenTelemetry, `X-Cliente` e testes de log seguro — ADR-0010 §1, §9, ADR-0006 §14, U-12.

## Non-goals (o que fica de fora, explicitamente)

- Área do membro web, auto-cadastro e pedido de vínculo, configuração de campos da área do membro, aprovação de alterações, responsável por pessoa sem contato, formulários públicos e LGPD do titular (P FR-018–FR-024, FR-035, termos e consentimentos) — vão para a Spec **0002** (U-01, U-08). A ficha da 0001 já tem os campos de conversão (aceitou Jesus, data, canal) que o formulário "aceitei Jesus" vai usar (P §14).
- App nas lojas e push — vão para a Spec **0003** (U-01; ADR-0007).
- Migração do inChurch (P FR-025–FR-027) — vai para a Spec **0004** (U-01; ADR-0009). A 0001 só cria o estado `em_migracao` e a supressão de avisos (ADR-0009 §5, §10), porque o módulo Avisos nasce aqui.
- Hospedagem, homologação, produção, deploy, Alloy/Grafana Cloud/Sentry, alertas, runbooks e ferramenta de chat — vão para a **Spec de infraestrutura** (U-12; ADR-0010 §9, §11, §12; ADR-0011).
- Parabéns automáticos e o distribuidor das 9h (U-03; ADR-0008 §6, §12) — pedido futuro ao Product Owner.
- Autorização temporária de suporte e operadores da plataforma (U-11; ADR-0003 §13; ADR-0004 §12) — Spec posterior.
- Ver e encerrar as próprias sessões e dispositivos, e alterar e-mail ou celular da conta (ADR-0003 §11) — vão para a 0002, com a conta do membro (U-01).
- Campos personalizados, importação por planilha, regionais (telas), pastores e recepção no painel — P §7.
- Painel de operação da plataforma (ADR-0010 §11) — Spec de infraestrutura (U-12).
- Preenchimento de endereço pelo CEP (P §15) — sem ADR de provedor; ADR antes da 0002 (U-23).
- Excluir ou desativar igreja — pergunta ao Product Owner (U-26).

## Regras de negócio

| Regra | Descrição | Fonte |
|---|---|---|
| RN-01 | Uma pessoa existe uma única vez na organização (denominação). | P BR-001 |
| RN-02 | Uma conta liga no máximo uma Pessoa por organização, e cada Pessoa no máximo uma conta. Na 0001, a ligação nasce só do convite emitido pela organização, nunca de e-mail igual. | P BR-002; ADR-0003 §2, §5 |
| RN-03 | Vínculos: visitante, frequentador, membro. Só membro tem matrícula. | P BR-003 |
| RN-04 | A matrícula é única na organização e acompanha o membro na transferência. | P BR-004; ADR-0002 §7 |
| RN-05 | O modo da matrícula (automática ou manual) é configuração da organização. Enquanto o formato automático estiver em aberto, só o modo manual funciona, e passar para Membro exige informar uma matrícula livre. | P FR-010; U-07; U-18 |
| RN-06 | Mudança de vínculo, inativação e reativação exigem motivo (da lista da organização) e geram evento na linha do tempo. | P BR-005; U-09 |
| RN-07 | O vínculo só avança Visitante → Frequentador → Membro; não pula e não volta; quem deixa de participar é inativado com motivo; a reativação volta ao mesmo vínculo. | P BR-020; P §12 |
| RN-08 | Uma pessoa tem vínculo com uma só igreja por vez; mudar de igreja é transferência: a secretaria de destino pede, a de origem aprova ou recusa com justificativa. A matrícula é mantida e o fato entra na linha do tempo. Só um pedido pendente por pessoa. | P BR-015; BR-019; FLOW-009; U-21 |
| RN-09 | A secretaria só abre a ficha de pessoas da sua igreja. Na busca na denominação, de pessoas fora do seu alcance vê só identificador público, nome, igreja e vínculo, escolhidos no `SELECT`. | P BR-006; ADR-0004 §6 |
| RN-10 | Saúde, documentos, endereço e contatos, e fé/conversão só aparecem (ficha, lista, busca, exportação) a quem tem `…ver` da categoria; escrever exige `…editar`. O administrador sempre tem as quatro. A secretaria pronta tem documentos, contatos e fé; saúde só se o administrador marcar. | P BR-007; ASSUMPTION-002; ADR-0004 §3, §7 |
| RN-11 | A mesclagem é sempre manual (campo a campo) e pode ser desfeita por 90 dias. Desfazer só restaura um campo se o valor atual ainda é o que a mesclagem gravou; senão mantém o atual e mostra o conflito. | P BR-008; ADR-0005 §6 |
| RN-12 | Ninguém define a senha de outra pessoa. O acesso ao painel é por convite de uso único, válido por 72h e revogável. | P BR-010; ADR-0003 §5 |
| RN-13 | As regras de obrigatoriedade são por vínculo, configuradas pelo administrador da organização, e são as mesmas em todos os canais. Não impedem o cadastro rápido; definem a completude da ficha. | P BR-011; ASSUMPTION-006; U-05; U-06 |
| RN-14 | Todo número exibido informa o critério. A contagem oficial é "pessoas ativas por vínculo em cada igreja", e o total da organização é a soma das igrejas. | P BR-012 |
| RN-15 | Ações administrativas, mudanças de acesso e exportações são auditadas na mesma transação; se a auditoria falha, a ação falha. A auditoria guarda nomes de campos, nunca valores nem texto livre. | P BR-013; ADR-0005 §2 |
| RN-16 | 2FA obrigatório para o papel Administrador e opcional para os demais. | P BR-016; ADR-0003 §7 |
| RN-17 | Os papéis Administrador e Secretaria vêm prontos e são editáveis; o administrador cria papéis novos combinando permissões do catálogo. Sempre existe ao menos um acesso ativo com o papel Administrador no escopo da organização, e esse papel não perde `papeis.gerir`/`acessos.gerir`. Ninguém concede permissão ou escopo que não tem. | P BR-018; ADR-0004 §1–§3 |
| RN-18 | Quem aceitou Jesus fica na lista de novos convertidos até a secretaria registrar o batismo ou mudar o vínculo, sem prazo fixo; o registro de conversão continua na ficha. | P BR-021 |
| RN-19 | Pessoa excluída fica 30 dias na lixeira, escondida de telas, buscas e exportações, e pode ser recuperada com todos os dados. Depois, o job diário a remove pelo removedor de Pessoas, no modo configurado. Em produção, o removedor recusa executar sem o modo definido. | P BR-022; ADR-0005 §7, §8; OPEN-013 |
| RN-20 | A exportação usa a mesma consulta e a mesma projeção de quem exporta, exige `pessoas.exportar` e recalcula o alcance quando o job roda. | ADR-0004 §9 |
| RN-21 | Convites saem por e-mail e pelo WhatsApp da organização (nos dois, se houver os dois contatos), na hora, e são obrigatórios. Avisos de serviço vão para a caixa de avisos mais um canal externo, com reenvio pelo próximo canal em falha definitiva. Códigos vão pelo canal escolhido, com SMS de reserva. | P FR-036; ADR-0008 §12 |
| RN-22 | Com a organização `em_migracao`, nenhum envio é criado: registra `envio.suprimido` com motivo `migracao`. | ADR-0009 §5, §10 |
| RN-23 | Duplicidade: (nome completo E nascimento) OU CPF OU e-mail iguais na organização. No cadastro rápido, o detector também considera pessoas na lixeira e oferece recuperar a ficha. | P ASSUMPTION-003; AC-001; U-20 |

## Casos de borda considerados

- Consulta ou job sem contexto de organização: gera erro `tenant.context.missing`, nunca lista vazia — ADR-0002 §4, ADR-0008 §5.
- Consulta de modelo com escopo de igreja sem alcance definido: gera erro, não lista vazia — ADR-0004 §5.
- Acesso revogado ou convite revogado com sessão aberta: a próxima requisição já é negada — ADR-0003 §11, ADR-0004 §4.
- Acesso revogado depois de pedir uma exportação: o job não exporta — ADR-0004 §9.
- Tentativa de remover o último Administrador, ou de tirar `papeis.gerir`/`acessos.gerir` dele: recusada, com `authz.last_admin_blocked` — ADR-0004 §3, ADR-0005 §5.
- Conceder permissão ou escopo que o próprio usuário não tem: recusada, com `authz.escalation_blocked` — ADR-0004 §3.
- Duplicado de outra igreja no cadastro rápido: a secretaria vê só nome, igreja e vínculo, e pode pedir transferência — P FLOW-001.
- CPF igual a outro da organização: o `cpf_hash` é único na organização (ignorando a lixeira), então o cadastro é recusado e oferece abrir a ficha existente — ADR-0002 §7.
- "É outra pessoa" no alerta de duplicidade (nome + nascimento ou e-mail): o cadastro segue — P FR-007.
- Desfazer mesclagem depois de alguém editar a ficha mantida: os campos alterados ficam com o valor atual e aparecem como conflito — ADR-0005 §6.
- Desfazer mesclagem depois de 90 dias: indisponível, e o snapshot já foi apagado — ADR-0005 §6.
- Mudar Visitante direto para Membro, ou voltar etapa: recusado, com mensagem que aponta o passo seguinte — P AC-008, BR-020.
- Passar para Membro no modo manual sem matrícula, ou com matrícula já usada: 422 no campo — U-18, ADR-0002 §7.
- Transferência recusada: o vínculo não muda e a secretaria de destino é avisada — P FLOW-009.
- Transferência aprovada: a secretaria de destino passa a abrir a ficha e a de origem deixa de abrir, sem outra ação, porque o alcance segue a igreja atual — ADR-0004 §6.
- Convite expirado (72h) ou já usado: o aceite é recusado, e o administrador pode emitir outro — ADR-0003 §5.
- Convite para Pessoa sem e-mail e sem celular: recusado (não há contato para enviar) — ADR-0003 §5.
- Organização sem número de WhatsApp ligado: convites e avisos seguem sem WhatsApp, sem reserva pelo número da plataforma — ADR-0008 §10.
- Primeiro login de um Administrador: precisa cadastrar o 2FA antes de entrar no painel — ADR-0003 §5.
- Código de 2FA de quem tem 2FA obrigatório, quando houve recuperação de senha nas últimas 24h: não vai pelo mesmo canal da recuperação — ADR-0003 §7.
- Elevação do painel vencida (2h sem uso ou 12h no total): 403 `elevacao_necessaria`, e a tela pede senha + 2FA sem perder o formulário — ADR-0003 §9, ADR-0006 §10.
- Organização ativa trocada em outra aba: 409 `organizacao_trocada`, sem executar nada — ADR-0006 §10.
- Login com conta inexistente: a mensagem não revela que a conta não existe — ADR-0003 §4.
- Cor primária sem contraste AA: recusada — ADR-0006 §7.
- Novos convertidos e aniversários de batismo exibem dado de fé/conversão: só aparecem a quem tem `pessoas.sensiveis.fe.ver` — P BR-007, P §14 (batismo e conversão são "sensível: fé/conversão"), ADR-0004 §7.
- Pessoa na lixeira: não aparece em telas, buscas, exportações nem na lista de possíveis duplicados — ADR-0005 §7. No cadastro rápido, o alerta de duplicidade a encontra e oferece "Recuperar ficha" (U-20). Fora do alcance de quem cadastra, mostra só nome, igreja e vínculo (RN-09), e recuperar exige permissão no alcance da igreja dela.
- Segundo pedido de transferência para uma pessoa com pedido pendente: recusado com mensagem (U-21).
- Webhook com assinatura inválida: 401, sem processar, com `webhook.assinatura_invalida` — ADR-0008 §15, ADR-0010 §2.
- Valor sensível (CPF, telefone, e-mail, token, código): nunca aparece em log — ADR-0004 §8, ADR-0010 §1.

## Critérios de aceite

Cada critério vira pelo menos um teste em `tasks.md`.

- [ ] **CA-01** (P AC-001) Com uma pessoa já cadastrada na organização, o cadastro rápido com nome + nascimento, CPF ou e-mail iguais avisa a possível duplicidade antes de salvar.
- [ ] **CA-02** (P AC-002) Quando o administrador dá acesso com o papel Secretaria e escopo de uma igreja, a pessoa recebe o convite, define a própria senha e só abre fichas daquela igreja.
- [ ] **CA-03** (P AC-003) Uma secretaria sem permissão de sensíveis não vê saúde, documentos, endereço/contatos nem fé/conversão na ficha, na lista, na busca e na exportação.
- [ ] **CA-04** (P AC-004) Desfazer uma mesclagem faz as duas fichas voltarem a existir com os dados originais.
- [ ] **CA-05** (P AC-007) Concluída a transferência, a matrícula do membro permanece a mesma.
- [ ] **CA-06** (P AC-008) Tentar mudar uma pessoa de Visitante direto para Membro não é permitido, e a resposta indica que ela precisa passar por Frequentador.
- [ ] **CA-07** (P AC-010) Uma pessoa excluída há menos de 30 dias pode ser recuperada da lixeira com todos os dados.
- [ ] **CA-08** (ADR-0002 §4, §12; ADR-0010 §1) Com o papel da aplicação, leitura, escrita, atualização e exclusão cruzadas entre duas organizações falham em toda tabela com `organizacao_id`. Consulta sem contexto dá erro. Tabela com `organizacao_id` sem RLS forçada e política quebra o teste de catálogo.
- [ ] **CA-09** (P FR-004; ADR-0003 §11) Revogar um acesso faz a próxima requisição daquela conta ao painel ser negada.
- [ ] **CA-10** (P FR-029; BR-016) Um Administrador sem 2FA cadastrado não entra no painel. Uma Secretaria entra sem 2FA, mas, se o ativar, passa a precisar dele.
- [ ] **CA-11** (P FR-030; ADR-0004 §3) O administrador cria um papel com permissões do catálogo. O sistema recusa remover o último Administrador e recusa conceder permissão ou escopo que quem concede não tem.
- [ ] **CA-12** (P FR-005; BR-013; ADR-0005 §2, §3) Cada ação administrativa, mudança de acesso e exportação gera uma linha de auditoria na mesma transação, sem valores de campo. Uma falha na gravação da auditoria desfaz a ação. O papel da aplicação não consegue `UPDATE`/`DELETE` em `auditoria`. Só quem tem `auditoria.ver` consulta.
- [ ] **CA-13** (P FR-006; ASSUMPTION-006) O cadastro rápido salva com nome, celular ou e-mail, vínculo, igreja e "como chegou", gera o evento de entrada na linha do tempo e oferece "salvar e cadastrar outro".
- [ ] **CA-14** (P FR-008; U-05; U-06) A ficha mostra o percentual de completude calculado sobre os obrigatórios do vínculo da pessoa, configurados pelo administrador.
- [ ] **CA-15** (P FR-009; BR-005) Mudar vínculo, inativar e reativar sem motivo é recusado. Com motivo, gera evento na linha do tempo, e a reativação volta ao mesmo vínculo.
- [ ] **CA-16** (P FR-010; RN-05) Com o modo manual, passar para Membro exige uma matrícula única na organização.
- [ ] **CA-17** (P FR-011) A linha do tempo mostra os eventos automáticos com motivo e aceita registro manual com data retroativa.
- [ ] **CA-18** (P FR-012, FR-013) A secretaria registra relacionamentos entre pessoas da organização, atribui funções com início e fim e define o tipo de membro.
- [ ] **CA-19** (P FR-014; U-16; U-17) A lista pagina em 25/50/100. Filtra por igreja, vínculo, situação, tipo de membro, função, ficha incompleta e aniversariantes do mês, com os filtros na URL. Exporta o filtro ou só os selecionados, em XLSX ou CSV, com a projeção de quem exporta.
- [ ] **CA-20** (P FR-015; BR-006) A busca na denominação devolve, para pessoas de outra igreja, só identificador público, nome, igreja e vínculo. Abrir a ficha dela é negado.
- [ ] **CA-21** (P FR-016) A lista de possíveis duplicados mostra os pares que atendem à RN-23, e a mesclagem grava o valor escolhido em cada campo divergente.
- [ ] **CA-22** (P FR-031; FLOW-009) A secretaria de destino pede a transferência e a de origem aprova ou recusa (com justificativa). Os dois resultados geram aviso e evento na linha do tempo.
- [ ] **CA-23** (P FR-032; BR-021) Uma pessoa com conversão registrada aparece em novos convertidos da sua igreja até o batismo registrado ou a mudança de vínculo.
- [ ] **CA-24** (P FR-033, FR-034) Aniversariantes, aniversários de batismo e de casamento do dia, da semana e do mês aparecem na tela e podem ser exportados.
- [ ] **CA-25** (P FR-036; ADR-0008 §2, §4) Convites e avisos de serviço gravam o aviso e os envios na mesma transação do fato. Nada é enviado antes do commit, e repetir o job não duplica envio já `enviado`.
- [ ] **CA-26** (ADR-0009 §10) Organização `em_migracao` não cria envio; registra `envio.suprimido`.
- [ ] **CA-27** (ADR-0004 §8) CPF, RG e saúde ficam cifrados no banco com a chave de campos, separada da `APP_KEY`. A busca por CPF exato funciona pelo `cpf_hash`.
- [ ] **CA-28** (ADR-0010 §1) Dados-sentinela (CPF, telefone, e-mail, token, código) nunca aparecem nos logs.
- [ ] **CA-29** (ADR-0010 §4, §7) O CI bloqueia o PR quando o contrato OpenAPI gerado difere do versionado, quando o `oasdiff` aponta quebra na `/api/v1` ou quando o JS inicial do `painel` passa de 350 KB gzip.
- [ ] **CA-30** (U-09) O administrador mantém as listas "como chegou", motivos, tipos de membro e funções da organização, e só valores ativos dessas listas são aceitos nos cadastros.

## Restrições arquiteturais aplicáveis (ADRs)

| ADR | Decisão | Como impacta esta spec |
|---|---|---|
| OIK-ADR-0001 | Laravel como API `/api/v1` + React/TS; Action única por caso de uso; monólito modular; sem Filament/Livewire/Inertia | Toda tela do painel consome a API; cada caso de uso é uma Action. |
| OIK-ADR-0002 | PostgreSQL compartilhado, `organizacao_id` + RLS forçada, FKs compostas, UUID público, `nome_busca` + trigram | Todo schema da Spec segue o padrão, e o teste de catálogo é bloqueante. |
| OIK-ADR-0003 | Conta global + `acessos` sob RLS, Sanctum cookie + CSRF na web, Fortify headless, 2FA, elevação do painel | Base do login, do convite e do acesso ao painel. |
| OIK-ADR-0004 | Policies próprias, catálogo `recurso.acao`, alcance por requisição, projeção de sensíveis, cifra de CPF/RG/saúde | Toda Action declara permissão; Resources montam a projeção. |
| OIK-ADR-0005 | Auditoria só de inclusão, linha do tempo separada, desfazer por snapshot de 90 dias, lixeira de 30 dias, removedor por módulo | Mesclagem, lixeira e auditoria. |
| OIK-ADR-0006 | SPA Vite, TanStack Router/Query, RHF + Zod, MUI 7 + DataGrid MIT, contrato tipado, `meta.acoes`, tratamento de 401/409/419 | Entrada `painel` e `packages/api-contrato`. |
| OIK-ADR-0008 | Módulo Avisos, caixa de saída transacional, fila `database`, Postmark/Meta/Twilio, janela diurna | Convites, códigos e avisos de serviço da 0001. |
| OIK-ADR-0009 | Estado `em_migracao` e supressão de avisos | Só o estado e a supressão (o resto na 0004). |
| OIK-ADR-0010 | Pest com PG real, suíte RLS, matriz de autorização, Deptrac, contrato, Vitest/Playwright/axe, CI no Actions, `composer gate`/`pnpm gate`, instrumentação | E-02 e E-15. |
| OIK-ADR-0011 | PG 17 com `pg_trgm`/`unaccent`, contexto por `set_config(..., true)`, sessão/cache/fila no PG, S3 privado com URL assinada, segredos fora do repositório | Ambiente local e convenções do código; a hospedagem fica na Spec de infraestrutura (U-12). |
| OIK-ADR-0007 | — | Fora (0003); a 0001 não quebra a `/api/v1` que o app vai usar (ADR-0001, consequências). |

## Pontos em aberto

- ❓ **OPEN-003** — lista exata dos campos obrigatórios por vínculo. A Spec entrega o mecanismo; a lista fica vazia até ser levantada no inChurch (U-05).
- ❓ **OPEN-016 / formato da matrícula automática** — só o modo manual funciona até a definição (U-07, U-18).
- ❓ **Limite mensal de WhatsApp dos planos** — campo sem valor = sem limite (U-04; ADR-0008, lacunas).
- ❓ **Valores iniciais das listas** ("como chegou", motivos, tipos de membro, funções) — a organização começa com listas vazias até a definição (U-09).
- ❓ **OPEN-013 — modo da exclusão (anonimizar ou apagar)** — sem o modo, a purga da lixeira não roda em produção (RN-19). Não bloqueia esta Spec, que não vai a produção antes da virada (P BR-017).
- ❓ **Valores dos limites de tentativas de login** (por identificador e por IP, com bloqueio progressivo) — o mecanismo é configurável nesta Spec; os números ficam para a Spec de infraestrutura/operação (ADR-0003 §4; U-22).
- ❓ **Dependências externas (ação do usuário)** — conta Postmark, Meta Business + *Tech Provider* com número da plataforma, Twilio. Os testes usam Adapters falsos (ADR-0010 §2); a validação contra os provedores reais fica para quando as contas existirem.
