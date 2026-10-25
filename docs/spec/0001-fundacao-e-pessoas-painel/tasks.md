# OIK-TASKS-0001: Fundação + Pessoas no painel

**Plano relacionado:** OIK-PLAN-0001 (`plan.md`, aprovado em 2026-10-21)
**Data:** 2026-10-21
**Análise de consistência:** Aprovada em 2026-10-23 (ver "Análise de consistência" no fim)

Convenções: caminhos relativos à raiz do `Oikora-One-Project`. Testes de backend em Pest com PostgreSQL real e o papel `oikora_app` (ADR-0010 §1). Testes de frontend em Vitest + RTL + MSW (ADR-0010 §5). Cada task tem um Context Pack em `context/T-NN.md`. Os comandos de teste são registrados pela T-01 em `project.md` da governança (CTX-011).

---

## Fase A — Base

## T-01 — Bootstrap do monorepo e ambiente local
- **Objetivo:** criar o workspace pnpm (`api/`, `web/` com a entrada `painel`, `packages/api-contrato/`), o arquivo único de versões e o Docker Compose local (PG 17 + `pg_trgm`/`unaccent`, Caddy + PHP-FPM, MinIO), com `GET /api/v1/saude`.
- **Context Pack:** `context/T-01.md`
- **Arquivos de entrada:** `plan.md` §1, §9, §10; `spec.md` E-01.
- **Arquivos de saída:** `package.json`, `pnpm-workspace.yaml`, `versoes.env`, `compose.yaml`, `api/**` (Laravel), `web/**` (Vite, entrada `painel`), `packages/api-contrato/package.json`, `api/routes/api.php`, `plan.md` §9 (versões fixadas).

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Feature/Saude/SaudeTest.php`; `web/src/app/painel/__tests__/raiz.test.tsx`
- **Casos cobertos:**
  1. Dado o ambiente no ar, quando `GET /api/v1/saude` sem autenticação, então 200 com `vivo: true`, `banco: "ok"`, `versao` e `filas` (idade do job mais antigo por fila; 0 sem jobs).
  2. A resposta de `/saude` não contém nenhum dado de organização ou pessoa.
  3. O banco tem as extensões `pg_trgm` e `unaccent` instaladas e a versão maior 17.
  4. A raiz da entrada `painel` renderiza sem erro.
- **Resultado esperado antes do código existir:** o teste deve FALHAR (Red). Só prosseguir para a implementação depois que o usuário aprovar o teste e confirmar essa falha.
- **Critério de aceite final:** testes passando (Green); `docker compose up` sobe todos os serviços; versões registradas no `plan.md` §9 (U-15); comandos de teste registrados no `project.md` (CTX-011).
- **Regra de negócio associada:** nenhuma.
- **Status:** Pendente

## T-02 — CI no GitHub Actions e Pre-Review Gate
- **Objetivo:** criar `composer gate`, `pnpm gate`, o workflow de PR com filtro por caminho, o workflow noturno (vazio até a T-45) e o Dependabot, com PHPStan nível 8, Pint, Deptrac, cobertura de linhas alteradas, gitleaks e auditoria de dependências.
- **Context Pack:** `context/T-02.md`
- **Arquivos de entrada:** `plan.md` §1, §9; `spec.md` E-02.
- **Arquivos de saída:** `.github/workflows/ci.yml`, `.github/workflows/noturno.yml`, `.github/dependabot.yml`, `api/composer.json` (script `gate`), `api/phpstan.neon`, `api/deptrac.yaml`, `api/pint.json`, `package.json` (script `gate`), `web/eslint.config.js`, `web/.size-limit.json`.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Arquitetura/GateTest.php`
- **Casos cobertos:**
  1. O Deptrac com `deptrac.yaml` termina com código 0 no código atual.
  2. O Deptrac com a fixture `tests/Fixtures/Deptrac/violacao` (um Domain que importa `Illuminate\…`) termina com código ≠ 0.
  3. `phpstan.neon` declara o nível 8 e não tem baseline (U-14).
  4. `.size-limit.json` limita o JS inicial do `painel` a 350 KB gzip.
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green; `composer gate` e `pnpm gate` passam localmente; um PR de teste executa o workflow (CA-29, parte do CI).
- **Regra de negócio associada:** nenhuma.
- **Status:** Pendente

## T-03 — Isolamento por organização no banco (RLS)
- **Objetivo:** criar os papéis `oikora_dono`/`oikora_app`/`oikora_plataforma`, a função `app_organizacao_atual()`, a tabela `organizacoes`, as macros de migration `organizacao()` e `foreignComposta()` e o teste de catálogo da RLS.
- **Context Pack:** `context/T-03.md`
- **Arquivos de entrada:** `plan.md` §2, §3.1.
- **Arquivos de saída:** `compose.yaml` (script de init dos papéis), `api/config/database.php` (conexões `pgsql`, `pgsql_dono`, `pgsql_plataforma`), `api/app/Shared/Tenancy/**`, `api/database/migrations/*_tenancy_*.php`, `api/database/migrations/*_create_organizacoes_table.php`.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Rls/CatalogoRlsTest.php`; `api/tests/Rls/SondaRlsTest.php`
- **Casos cobertos:**
  1. Toda tabela com `organizacao_id` tem RLS habilitada e forçada e política de leitura e escrita.
  2. Toda tabela sem `organizacao_id` está na lista fechada do `plan.md` §2.
  3. Com a tabela `rls_sonda` (só de teste, criada pela macro), a organização A não lê, insere, atualiza nem exclui linhas da organização B.
  4. Consulta sem contexto lança exceção com `tenant.context.missing`, nunca lista vazia.
  5. `oikora_app` não tem `BYPASSRLS` e não é dono de nenhuma tabela.
  6. FK composta recusa referenciar linha de outra organização.
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green; a suíte roda com a conexão `oikora_app` (CA-08).
- **Regra de negócio associada:** nenhuma.
- **Status:** Pendente

## T-04 — Contexto de organização por requisição e por job
- **Objetivo:** implementar o middleware HTTP `ContextoOrganizacao` (transação + `set_config(..., true)` + `X-Organizacao-Esperada`) e o middleware de job `ContextoOrganizacaoJob`, com o contrato `ResolvedorOrganizacaoAtiva` (a implementação real é da T-16).
- **Context Pack:** `context/T-04.md`
- **Arquivos de entrada:** `plan.md` §2; T-03 concluída.
- **Arquivos de saída:** `api/app/Shared/Tenancy/Http/ContextoOrganizacao.php`, `api/app/Shared/Tenancy/Jobs/ContextoOrganizacaoJob.php`, `api/app/Shared/Tenancy/Contracts/ResolvedorOrganizacaoAtiva.php`, `api/app/Shared/Tenancy/Jobs/JobSemOrganizacao.php`.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Feature/Tenancy/ContextoOrganizacaoTest.php`; `api/tests/Feature/Tenancy/ContextoJobTest.php`
- **Casos cobertos:**
  1. Uma rota com o middleware, com a organização A resolvida, lê só linhas de A em `rls_sonda`.
  2. Na requisição seguinte, na mesma conexão, sem middleware, o contexto não vaza (consulta → `tenant.context.missing`).
  3. `X-Organizacao-Esperada` diferente da organização resolvida → 409 `organizacao_trocada`, sem gravar nada.
  4. Job sem `organizacao_id` falha sem nova tentativa, com `tenant.context.missing`.
  5. Job com organização ativa grava e limpa o contexto; com organização inexistente, falha sem nova tentativa.
  6. Job marcado com `JobSemOrganizacao` roda sem contexto.
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green.
- **Regra de negócio associada:** nenhuma.
- **Status:** Pendente

## T-05 — Contrato OpenAPI e pacote `api-contrato`
- **Objetivo:** gerar o OpenAPI com Scramble, versioná-lo, gerar tipos e cliente em `packages/api-contrato` e incluir a verificação "gerado = versionado" + `oasdiff` no CI.
- **Context Pack:** `context/T-05.md`
- **Arquivos de entrada:** `plan.md` §7, §9; T-01, T-02 concluídas.
- **Arquivos de saída:** `api/openapi.json`, `api/config/scramble.php`, `packages/api-contrato/src/**`, `.github/workflows/ci.yml` (passo de contrato).

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Contrato/OpenApiTest.php`; `packages/api-contrato/src/__tests__/cliente.test.ts`
- **Casos cobertos:**
  1. A exportação do Scramble é igual a `api/openapi.json`.
  2. `GET /api/v1/saude` está documentado com o schema da resposta.
  3. A fixture de Resource com campo condicional (projeção) aparece como propriedade opcional (valida o risco do `plan.md` §13).
  4. O cliente tipado chama `/saude` e o TypeScript recusa um campo inexistente (teste de tipo).
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green; o CI falha quando o contrato diverge ou quando o `oasdiff` aponta quebra (CA-29).
- **Regra de negócio associada:** nenhuma.
- **Status:** Pendente

## T-06 — Instrumentação no código e log seguro
- **Objetivo:** implementar `X-Request-Id`, logs JSON no stdout com redação, o SDK OpenTelemetry (exportação desligável no dev), `X-Cliente` e a herança do `request_id` em jobs.
- **Context Pack:** `context/T-06.md`
- **Arquivos de entrada:** `plan.md` §1, §9; `spec.md` E-15.
- **Arquivos de saída:** `api/app/Shared/Observabilidade/**`, `api/config/logging.php`, `api/bootstrap/app.php`.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/LogSeguro/RedacaoLogTest.php`; `api/tests/Feature/Observabilidade/RequestIdTest.php`
- **Casos cobertos:**
  1. Requisição sem `X-Request-Id` recebe um UUID novo no cabeçalho de resposta e no corpo de qualquer erro.
  2. `X-Request-Id` recebido é devolvido e gravado no log.
  3. Cada linha de log é JSON com `request_id`, `trace_id`, `ambiente`, `module`, `action`.
  4. Dados-sentinela (CPF, telefone, e-mail, token, código de 6 dígitos) na mensagem ou no contexto saem redigidos.
  5. Um job despachado na requisição registra o mesmo `request_id` como correlation id.
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green (CA-28).
- **Regra de negócio associada:** nenhuma.
- **Status:** Pendente

---

## Fase B — Núcleo: auditoria, organização, identidade, autorização, avisos

## T-07 — Auditoria: registro na transação e imutabilidade
- **Objetivo:** criar `auditoria` (particionada por mês), `auditoria_plataforma`, o enum `EventoAuditoria`, o serviço `Auditoria::registrar` na transação, o comando de partições e retenção (conexão `pgsql_dono`) e o teste de arquitetura das Actions de escrita.
- **Context Pack:** `context/T-07.md`
- **Arquivos de entrada:** `plan.md` §3.4; T-03, T-04 concluídas.
- **Arquivos de saída:** `api/app/Modules/Auditoria/**`, migrations de `auditoria` e `auditoria_plataforma`, `api/tests/Arquitetura/AuditoriaArquiteturaTest.php`.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Feature/Auditoria/RegistroAuditoriaTest.php`; `api/tests/Rls/AuditoriaImutavelTest.php`; `api/tests/Arquitetura/AuditoriaArquiteturaTest.php`
- **Casos cobertos:**
  1. `registrar` grava evento, ator, alvo, igreja, nomes dos campos, metadados codificados, canal e `request_id`, sem nenhum valor de campo.
  2. Metadado fora do catálogo do evento ou texto livre → exceção.
  3. Falha simulada na gravação da auditoria desfaz a transação da ação que a chamou.
  4. Com `oikora_app`, `UPDATE` e `DELETE` em `auditoria` falham por permissão.
  5. O comando de partições cria a partição do mês seguinte; o de retenção remove (com `pgsql_dono`) as partições com mais de 5 anos.
  6. Teste de arquitetura: toda Action de escrita declara o seu evento de auditoria.
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green (CA-12, parte do registro).
- **Regra de negócio associada:** RN-15 — atualizar `docs/modules/auditoria.md`.
- **Status:** Pendente

## T-08 — Organização: schema de regionais e igrejas
- **Objetivo:** completar `organizacoes` (estado, fuso, logo, cor, modo de matrícula, limite de WhatsApp) e criar `regionais` e `igrejas` com FK composta.
- **Context Pack:** `context/T-08.md`
- **Arquivos de entrada:** `plan.md` §3.1; T-03 concluída.
- **Arquivos de saída:** `api/app/Modules/Organizacao/**` (Models, enums), migrations.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Rls/OrganizacaoRlsTest.php`; `api/tests/Unit/Organizacao/OrganizacaoTest.php`
- **Casos cobertos:**
  1. `igrejas` e `regionais` entram no catálogo da RLS, e o teste cruzado entre duas organizações falha nas quatro operações.
  2. Igreja com `regional_id` de outra organização é recusada pela FK composta.
  3. Organização nova tem `estado = ativa`, `fuso = America/Sao_Paulo`, `modo_matricula = manual` e `limite_whatsapp_mensal` nulo.
  4. `estado` só aceita `ativa` e `em_migracao`.
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green.
- **Regra de negócio associada:** nenhuma.
- **Status:** Pendente

## T-09 — Pessoas: schema, catálogo de campos e cifra de sensíveis
- **Objetivo:** criar `pessoas`, `pessoa_contatos` e `pessoa_enderecos`, o catálogo de campos da ficha (categoria e seção), o `CampoSensivelEncrypter` com rotação, o `cpf_hash` (HMAC) e o `nome_busca` com índice trigram.
- **Context Pack:** `context/T-09.md`
- **Arquivos de entrada:** `plan.md` §3.5, §5 (Cifra); T-08 concluída.
- **Arquivos de saída:** `api/app/Modules/Pessoas/Domain/CampoFicha.php`, `api/app/Modules/Pessoas/Infrastructure/**`, `api/app/Shared/Cifra/**`, comando `oikora:chaves:recifrar`, migrations.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Feature/Pessoas/CifraCamposTest.php`; `api/tests/Unit/Pessoas/CatalogoCamposTest.php`
- **Casos cobertos:**
  1. CPF, RG, país do documento, necessidades especiais e tipo sanguíneo ficam gravados cifrados (a leitura crua difere do valor) e não decifram com a `APP_KEY`.
  2. Valor cifrado com a chave anterior continua legível, e `oikora:chaves:recifrar` regrava com a chave atual.
  3. `cpf_hash` = HMAC do CPF normalizado; é único na organização, mesmo com a pessoa na lixeira.
  4. `nome_busca` = nome em minúsculas e sem acento.
  5. Todo campo do catálogo tem categoria e seção, e as categorias sensíveis batem com P §14.
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green (CA-27).
- **Regra de negócio associada:** RN-10 — atualizar `docs/modules/pessoas.md`.
- **Status:** Pendente

## T-10 — Contas, RLS de identidade e login
- **Objetivo:** criar `contas` com a RLS de identidade e as funções estreitas, `acessos` (U-30), `eventos_autenticacao` (U-31), o login e logout com Fortify headless (e-mail ou celular + senha), a sessão de 30 dias, a política de senha e o limite de tentativas configurável.
- **Context Pack:** `context/T-10.md`
- **Arquivos de entrada:** `plan.md` §3.2, §4; T-03, T-06, T-09 concluídas.
- **Arquivos de saída:** `api/app/Modules/Identidade/**`, `api/config/fortify.php`, `api/config/oikora.php`, `api/config/session.php`, migrations de `contas`/`sessions`/`codigos_verificacao`/`acessos`/`eventos_autenticacao`.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Feature/Identidade/LoginTest.php`; `api/tests/Rls/ContasRlsTest.php`
- **Casos cobertos:**
  1. Login com e-mail + senha correta → 200 e sessão criada; com celular em E.164, idem.
  2. Senha errada e conta inexistente recebem a mesma resposta e a mesma mensagem.
  3. Ao passar o limite por identificador ou por IP (valores de `config/oikora.php`) → 429 `muitas_tentativas`, com bloqueio que cresce a cada nova violação.
  4. Senha com menos de 8 caracteres ou vazada (HIBP fake) é recusada com 422.
  5. RLS de identidade: no contexto da organização A, uma conta sem acesso em A não aparece; a própria conta aparece.
  6. `identidade_localizar_conta` devolve só id, hash, estado e canal de 2FA.
  7. Login com sucesso e com falha (inclusive de identificador inexistente) grava `eventos_autenticacao`, sem senha e sem identificador em claro; `oikora_app` não consegue `UPDATE`/`DELETE` nela.
  8. A sessão dura 30 dias e é renovada com o uso.
  9. `acessos` recusa uma segunda linha ativa para a mesma conta ou para a mesma Pessoa na organização.
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green.
- **Regra de negócio associada:** RN-12 — atualizar `docs/modules/identidade.md`.
- **Status:** Pendente

## T-11 — Envio de códigos de identidade (fila `codigos`)
- **Objetivo:** enviar códigos de verificação, 2FA e recuperação pela fila dedicada `codigos`. Canais: e-mail (Postmark), WhatsApp da plataforma (template `authentication`) e SMS Twilio de reserva só para +55. Usa adapters falsos nos testes.
- **Context Pack:** `context/T-11.md`
- **Arquivos de entrada:** `plan.md` §3.2, §3.6, §4; T-10 concluída.
- **Arquivos de saída:** `api/app/Modules/Avisos/Contracts/EnvioCodigo.php`, `api/app/Modules/Avisos/Infrastructure/Canais/{Postmark,MetaWhatsApp,Twilio}/**`, `api/app/Modules/Avisos/Application/Jobs/EnviarCodigo.php`.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Feature/Avisos/EnvioCodigoTest.php`
- **Casos cobertos:**
  1. Código por e-mail: o job vai para a fila `codigos` e o adapter Postmark falso recebe o envio.
  2. Código por WhatsApp da plataforma usa o template `authentication`.
  3. Falha definitiva do WhatsApp (número sem WhatsApp) → SMS pelo Twilio falso; número fora de +55 → sem SMS.
  4. Limite de SMS de 3/h e 10/dia por número.
  5. Erro temporário → até 2 tentativas em 2 minutos.
  6. O payload do job e os logs não contêm o código nem o destino em claro; o estado do envio fica em `codigos_verificacao`.
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green; nenhuma chamada de rede real (`preventStrayRequests`).
- **Regra de negócio associada:** RN-21 (códigos) — atualizar `docs/modules/avisos.md`.
- **Status:** Pendente

## T-12 — Verificação de contato, 2FA e recuperação de senha
- **Objetivo:** implementar a verificação de e-mail e celular, o 2FA (código por mensagem, TOTP, códigos de recuperação, regra das 24h), a confirmação de senha de 15 min e a recuperação de senha que encerra todas as sessões.
- **Context Pack:** `context/T-12.md`
- **Arquivos de entrada:** `plan.md` §4; T-10, T-11 concluídas.
- **Arquivos de saída:** `api/app/Modules/Identidade/Application/**`, `api/app/Modules/Identidade/Presentation/**`.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Feature/Identidade/DoisFatoresTest.php`; `api/tests/Feature/Identidade/RecuperacaoSenhaTest.php`; `api/tests/Feature/Identidade/VerificacaoContatoTest.php`
- **Casos cobertos:**
  1. O código de 2FA tem 6 dígitos, vale 10 minutos e é de uso único (o segundo uso falha).
  2. TOTP válido completa o desafio; código de recuperação funciona uma vez; são gerados 10.
  3. Com 2FA obrigatório e recuperação de senha nas últimas 24h pelo canal X, o código não vai pelo canal X.
  4. Ativar ou desativar 2FA ou trocar a senha sem confirmar a senha nos últimos 15 minutos → recusado.
  5. A recuperação vale 60 minutos; redefinir a senha encerra todas as sessões da conta.
  6. Confirmar o código de verificação marca `email_verificado_em` ou `celular_verificado_em`.
  7. Desafio de 2FA (sucesso e falha) e recuperação de senha gravam `eventos_autenticacao` (U-31).
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green.
- **Regra de negócio associada:** RN-16 — atualizar `docs/modules/identidade.md`.
- **Status:** Pendente

## T-13 — Catálogo de permissões, papéis, atribuições e alcance
- **Objetivo:** criar o enum `Permissao`, as tabelas `papeis`, `papel_permissoes` e `atribuicoes_papel`, o serviço `Alcance` por requisição, o atributo `#[RequerPermissao]` com a Policy e o teste de arquitetura.
- **Context Pack:** `context/T-13.md`
- **Arquivos de entrada:** `plan.md` §3.3, §5; T-04, T-08 concluídas.
- **Arquivos de saída:** `api/app/Modules/Autorizacao/**`, migrations, `api/tests/Autorizacao/MatrizPermissoesTest.php`.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Unit/Autorizacao/AlcanceTest.php`; `api/tests/Autorizacao/MatrizPermissoesTest.php`; `api/tests/Arquitetura/PermissaoDeclaradaTest.php`
- **Casos cobertos:**
  1. Atribuição de escopo `organizacao` dá alcance em todas as igrejas; `regional`, nas igrejas da regional; `igreja`, só nela; várias atribuições somam.
  2. Remover uma atribuição muda o alcance já na requisição seguinte (sem cache).
  3. `papel_permissoes` só aceita valores do enum.
  4. A matriz gerada do enum confirma, para cada permissão, que a Policy nega recurso de igreja fora do alcance e permite dentro.
  5. Teste de arquitetura: Action sem `#[RequerPermissao]` faz o teste falhar.
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green.
- **Regra de negócio associada:** RN-17 (catálogo) — atualizar `docs/modules/autorizacao.md`.
- **Status:** Pendente

## T-14 — Escopo de consulta de Pessoas, busca na denominação e projeção de sensíveis
- **Objetivo:** aplicar o filtro global `PessoaEscopoAlcance`, a classe única `BuscaDenominacao` e o `PessoaResource` com a projeção por categoria e `meta.acoes`, expostos em `GET /painel/pessoas/{id}` e `GET /painel/busca`.
- **Context Pack:** `context/T-14.md`
- **Arquivos de entrada:** `plan.md` §5, §7; T-09, T-13 concluídas.
- **Arquivos de saída:** `api/app/Modules/Pessoas/Infrastructure/Escopos/PessoaEscopoAlcance.php`, `api/app/Modules/Pessoas/Application/Consultas/BuscaDenominacao.php`, `api/app/Modules/Pessoas/Presentation/**`.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Autorizacao/PessoaEscopoTest.php`; `api/tests/Feature/Pessoas/BuscaDenominacaoTest.php`; `api/tests/Feature/Pessoas/ProjecaoSensiveisTest.php`; `api/tests/Arquitetura/EscopoAlcanceTest.php`
- **Casos cobertos:**
  1. Consulta de Pessoa sem alcance no contexto → `authz.context_missing`.
  2. A secretaria da igreja X abre a ficha de uma pessoa de X; a de uma pessoa da igreja Y → 403.
  3. A busca por nome, CPF exato ou contato devolve, para pessoas de Y, só `uuid`, `nome`, igreja e vínculo, e o SQL gerado não seleciona outras colunas.
  4. Sem `pessoas.sensiveis.<cat>.ver`, os campos da categoria não vêm no JSON e não estão no `SELECT`, para cada uma das quatro categorias.
  5. O administrador vê as quatro categorias.
  6. Teste de arquitetura: só `BuscaDenominacao` remove o `PessoaEscopoAlcance`.
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green (CA-03 para ficha e busca; CA-20).
- **Regra de negócio associada:** RN-09, RN-10 — atualizar `docs/modules/pessoas.md`.
- **Status:** Pendente

## T-15 — Gestão de papéis e atribuições com travas
- **Objetivo:** implementar criar e editar papel, atribuir e remover atribuição, com a trava do último administrador e a anti-escalada, auditadas.
- **Context Pack:** `context/T-15.md`
- **Arquivos de entrada:** `plan.md` §3.3, §7; T-07, T-13 concluídas.
- **Arquivos de saída:** `api/app/Modules/Autorizacao/Application/Actions/**`, `api/app/Modules/Autorizacao/Presentation/**`.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Feature/Autorizacao/GestaoPapeisTest.php`
- **Casos cobertos:**
  1. Com `papeis.gerir`, o administrador cria um papel com permissões do catálogo; uma permissão fora do catálogo → 422.
  2. Os papéis prontos são editáveis.
  3. Remover a última atribuição ativa do papel Administrador no escopo da organização → 422 `ultimo_administrador` + `authz.last_admin_blocked` na auditoria.
  4. Tirar `papeis.gerir` ou `acessos.gerir` do papel Administrador → recusado.
  5. Conceder permissão ou escopo que quem concede não tem → 403 `escalada_bloqueada` + `authz.escalation_blocked`.
  6. A auditoria guarda as permissões e o escopo antes e depois.
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green (CA-11).
- **Regra de negócio associada:** RN-17 — atualizar `docs/modules/autorizacao.md`.
- **Status:** Pendente

## T-16 — Organização ativa, `/me` e elevação do painel
- **Objetivo:** implementar o `ResolvedorOrganizacaoAtiva` real (sessão + revalidação contra acessos ativos), `GET /me` com capacidades, `PUT /me/organizacao-ativa` e a elevação do painel (2h/12h, 2FA quando exigido, cadastro obrigatório do 2FA do administrador).
- **Context Pack:** `context/T-16.md`
- **Arquivos de entrada:** `plan.md` §3.2, §4; T-04, T-12, T-13 concluídas.
- **Arquivos de saída:** `api/app/Modules/Identidade/Infrastructure/ResolvedorOrganizacaoSessao.php`, `api/app/Modules/Identidade/Presentation/Http/Middleware/ElevacaoPainel.php`, rotas.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Feature/Identidade/OrganizacaoAtivaTest.php`; `api/tests/Feature/Identidade/ElevacaoPainelTest.php`
- **Casos cobertos:**
  1. Conta com um único acesso ativo → organização ativa automática; com dois, `PUT /me/organizacao-ativa` escolhe; organização sem acesso → 403.
  2. Acesso revogado depois do login → a próxima requisição não grava contexto e responde 403.
  3. `GET /me` devolve a conta, a organização ativa e as capacidades calculadas do alcance.
  4. Rota `/painel/*` sem elevação → 403 `elevacao_necessaria`; elevação expira com 2h sem uso ou 12h no total.
  5. Administrador sem 2FA cadastrado → `dois_fatores_cadastro_necessario`; com 2FA, a elevação exige o código.
  6. Secretaria sem 2FA eleva só com senha; se ativou o 2FA, precisa dele.
  7. Rota do painel exige ao menos uma atribuição de papel.
  8. Login feito pela entrada `painel` (senha + 2FA quando exigido) já abre a elevação, e a primeira rota `/painel/*` responde sem pedir a senha de novo (U-32).
  9. A elevação gera `auth.elevacao` na auditoria da organização e em `eventos_autenticacao` (U-31).
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green (CA-10; CA-09, parte do efeito).
- **Regra de negócio associada:** RN-16 — atualizar `docs/modules/identidade.md`.
- **Status:** Pendente

## T-17 — Avisos: caixa de saída transacional e idempotência
- **Objetivo:** criar `avisos`, `envios` (particionada) e o catálogo `TipoAviso`; implementar `Avisos::registrar` na transação do fato, o job de envio com `FOR UPDATE SKIP LOCKED`, a classificação temporário/definitivo, as tentativas, a varredura `envios:varrer` e a supressão `em_migracao`.
- **Context Pack:** `context/T-17.md`
- **Arquivos de entrada:** `plan.md` §3.6; T-04, T-07, T-08 concluídas.
- **Arquivos de saída:** `api/app/Modules/Avisos/{Contracts,Domain,Application,Infrastructure}/**`, migrations, agendamento em `api/routes/console.php`.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Feature/Avisos/CaixaDeSaidaTest.php`
- **Casos cobertos:**
  1. `registrar` dentro de uma transação que faz rollback → nenhum aviso, envio ou job.
  2. Com o commit, o aviso e um envio `pendente` por canal entram juntos, e nenhuma chamada ao provedor ocorre antes do commit.
  3. Registrar duas vezes com a mesma (tipo, referência, destinatário, chave) → um aviso só.
  4. Job repetido sobre envio já `enviado` não chama o provedor de novo.
  5. Erro temporário → novas tentativas com espera de 1 min, 5 min, 30 min e 2 h; erro definitivo → `falhou_definitivo` na hora.
  6. Organização `em_migracao` → aviso gravado, `envio.suprimido` com motivo `migracao` e nenhum envio criado.
  7. `envios:varrer` reenfileira pendentes sem job há mais de 5 minutos.
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green (CA-25, CA-26); no `compose.yaml`, `worker-codigos` consome só `codigos`, `worker-geral` consome `avisos,webhooks,default` e `worker-pesadas` consome `pesadas` (U-30).
- **Regra de negócio associada:** RN-21, RN-22 — atualizar `docs/modules/avisos.md`.
- **Status:** Pendente

## T-18 — Avisos: canais e-mail e WhatsApp, regras por categoria, janela e preferências
- **Objetivo:** ligar os adapters Postmark e Meta (número da organização) ao envio, aplicar as regras de canal de `convite` e `servico`, o reenvio pelo próximo canal, a espera de 30 minutos pela entrega no WhatsApp, a janela diurna 8h–21h, as preferências e o descadastro (one-click e "SAIR").
- **Context Pack:** `context/T-18.md`
- **Arquivos de entrada:** `plan.md` §3.6; T-11, T-17 concluídas.
- **Arquivos de saída:** `api/app/Modules/Avisos/Application/**`, `api/app/Modules/Avisos/Infrastructure/**`, `api/resources/views/emails/**`, migration de `preferencias_aviso`.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Feature/Avisos/RegrasCanalTest.php`; `api/tests/Feature/Avisos/DescadastroTest.php`
- **Casos cobertos:**
  1. Convite com e-mail e celular WhatsApp e organização com número ativo → envio por e-mail e por WhatsApp, na hora, mesmo às 23h.
  2. Organização sem número ativo → convite só por e-mail, sem usar o número da plataforma.
  3. Aviso de serviço → um único canal externo (preferido ou, por padrão, WhatsApp), com reenvio pelo e-mail em falha definitiva ou sem confirmação de entrega em 30 minutos.
  4. Aviso de serviço às 22h no fuso da organização → `agendado` para as 8h.
  5. Tipo obrigatório não pode ser desligado; descadastro de e-mail por link assinado (RFC 8058) grava `descadastrado_em`.
  6. O e-mail sai como "Nome da Igreja via Oikora" com responder-para da organização; o template não tem dado sensível.
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green.
- **Regra de negócio associada:** RN-21 — atualizar `docs/modules/avisos.md`.
- **Status:** Pendente

## T-19 — Avisos: webhooks de status
- **Objetivo:** criar `POST /webhooks/{meta|postmark|twilio}` (e o `GET` de verificação da Meta) com assinatura validada, resposta rápida, processamento na fila `webhooks` e localização da organização por função estreita.
- **Context Pack:** `context/T-19.md`
- **Arquivos de entrada:** `plan.md` §3.6, §7; T-17, T-18 concluídas.
- **Arquivos de saída:** `api/app/Modules/Avisos/Presentation/Webhooks/**`, `api/tests/Fixtures/Webhooks/**` (payloads anonimizados).

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Contrato/WebhooksTest.php`
- **Casos cobertos:**
  1. Assinatura válida de cada provedor → 200 e job na fila `webhooks`.
  2. Assinatura inválida → 401, sem processar, com `webhook.assinatura_invalida` no log.
  3. Envio desconhecido → 200, sem efeito.
  4. Eventos fora de ordem: `entregue` não volta para `enviado`.
  5. Bounce definitivo ou "número sem WhatsApp" marca `entrega_falhou_em` no contato; reclamação de spam descadastra dos não obrigatórios.
  6. O payload bruto não é gravado.
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green.
- **Regra de negócio associada:** nenhuma nova.
- **Status:** Pendente

## T-20 — Avisos: caixa de avisos, uso e limite de WhatsApp
- **Objetivo:** expor a caixa de avisos (listar, não lidos, marcar lido, marcar todos), com texto montado na leitura pelo alcance de quem lê, e as preferências; criar `uso_mensageria` e o limite mensal configurável (avisos de 80% e 100%, valor em aberto).
- **Context Pack:** `context/T-20.md`
- **Arquivos de entrada:** `plan.md` §3.6, §7; T-17, T-18 concluídas.
- **Arquivos de saída:** `api/app/Modules/Avisos/Presentation/**`, `api/app/Modules/Avisos/Application/**`, migration de `uso_mensageria`.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Feature/Avisos/CaixaAvisosTest.php`; `api/tests/Feature/Avisos/LimiteWhatsappTest.php`
- **Casos cobertos:**
  1. A conta vê só os próprios avisos da organização ativa, paginados; o contador de não lidos bate.
  2. O texto do aviso é montado na leitura, e o banco não guarda conteúdo.
  3. Marcar lido e marcar todos atualizam `lido_em`.
  4. `uso_mensageria` soma por mês, canal e categoria a cada envio.
  5. Com `limite_whatsapp_mensal` nulo, não há corte; com limite, 80% avisa o administrador; com 100%, serviço deixa de usar WhatsApp e convite continua.
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green.
- **Regra de negócio associada:** nenhuma nova (limite em aberto, U-04).
- **Status:** Pendente

## T-21 — Avisos: ligar o número de WhatsApp da organização
- **Objetivo:** implementar o "ligar número" pelo fluxo OAuth por redirecionamento (sem o SDK JavaScript da Meta), guardar o número com o token cifrado, criar os templates do catálogo na conta e desligar o número, com `avisos.whatsapp.gerir`.
- **Context Pack:** `context/T-21.md`
- **Arquivos de entrada:** `plan.md` §3.6, §13 (risco CSP); T-17 concluída.
- **Arquivos de saída:** `api/app/Modules/Avisos/**` (Actions e adapter Meta), migration de `whatsapp_numeros`.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Feature/Avisos/WhatsappNumeroTest.php`
- **Casos cobertos:**
  1. Sem `avisos.whatsapp.gerir` → 403.
  2. O retorno do OAuth com código válido (Meta falsa) grava `waba_id`, `phone_number_id`, nome exibido e estado, com o token cifrado (leitura crua ≠ token).
  3. Ao ligar, os templates do catálogo são criados na conta (chamadas à Meta falsa conferidas).
  4. Desligar → estado desconectado, e convites seguem só por e-mail.
  5. Ligar e desligar geram auditoria sem o token.
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green; validação real com a Meta registrada como pendente até o cadastro de *Tech Provider*.
- **Regra de negócio associada:** nenhuma nova.
- **Status:** Pendente

## T-22 — Convite ao painel e revogação de acesso
- **Objetivo:** implementar emitir convite (Pessoa + papel + escopo, com anti-escalada), aceitar (login ou conta nova com senha própria → acesso + atribuição), revogar convite, expirar (comando diário) e revogar acesso.
- **Context Pack:** `context/T-22.md`
- **Arquivos de entrada:** `plan.md` §3.2, §4, §7; T-14, T-15, T-16, T-18 concluídas.
- **Arquivos de saída:** `api/app/Modules/Identidade/Application/Actions/{Convidar,AceitarConvite,RevogarConvite,RevogarAcesso}.php`, migration de `convites`, rotas.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Feature/Identidade/ConviteTest.php`
- **Casos cobertos:**
  1. Administrador convida uma Pessoa com papel Secretaria e escopo da igreja X → convite `enviado` e aviso `convite.painel` registrado.
  2. A pessoa aceita com senha própria → conta e acesso ligados àquela Pessoa e atribuição criada; depois, abre ficha de X e não de Y.
  3. Token usado, revogado ou com mais de 72h → `convite_invalido`; o comando diário marca `expirado`.
  4. Pessoa sem e-mail e sem celular → 422 no convite.
  5. Quem já tem conta faz login para aceitar; nenhum vínculo é deduzido por e-mail igual.
  6. Revogar o acesso → a próxima requisição daquela conta ao painel é negada.
  7. Emitir, aceitar e revogar são auditados.
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green (CA-02, CA-09).
- **Regra de negócio associada:** RN-02, RN-12 — atualizar `docs/modules/identidade.md`.
- **Status:** Pendente

## T-23 — Provisionamento da organização
- **Objetivo:** criar o comando `oikora:organizacao:provisionar` (conexão `pgsql_plataforma`), que cria a organização, os papéis prontos, a primeira igreja, a Pessoa do primeiro administrador e o convite dele, registrando em `auditoria_plataforma`.
- **Context Pack:** `context/T-23.md`
- **Arquivos de entrada:** `plan.md` §3.3, §4 (Provisionamento); T-22 concluída.
- **Arquivos de saída:** `api/app/Modules/Organizacao/Presentation/Console/ProvisionarOrganizacao.php`, `api/database/seeders/**` (dados fictícios).

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Feature/Organizacao/ProvisionarOrganizacaoTest.php`
- **Casos cobertos:**
  1. O comando cria a organização `ativa` (ou `em_migracao` com a opção), os papéis Administrador e Secretaria com as permissões do `plan.md` §3.3, a igreja e a Pessoa do administrador.
  2. O convite do administrador tem papel Administrador e escopo `organizacao`, e o aviso fica registrado (suprimido se `em_migracao`).
  3. Uma linha em `auditoria_plataforma` com comando, operador e motivo.
  4. Rodar de novo com o mesmo identificador não duplica a organização.
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green; seeders de dados fictícios usam o comando.
- **Regra de negócio associada:** RN-17 (papéis prontos) — atualizar `docs/modules/organizacao.md`.
- **Status:** Pendente

## T-24 — Configuração da organização e igrejas (API)
- **Objetivo:** expor `GET|PUT /painel/organizacao` (nome, fuso, cor com contraste AA, modo de matrícula, limite de WhatsApp), o upload do logo por URL assinada (MinIO/S3) e o cadastro e edição de igrejas.
- **Context Pack:** `context/T-24.md`
- **Arquivos de entrada:** `plan.md` §3.1, §7; T-15, T-16 concluídas.
- **Arquivos de saída:** `api/app/Modules/Organizacao/{Application,Presentation}/**`.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Feature/Organizacao/IgrejasTest.php`; `api/tests/Feature/Organizacao/ConfiguracaoOrganizacaoTest.php`
- **Casos cobertos:**
  1. Com `igrejas.gerir`, criar e editar igreja → 201/200 e auditoria; sem a permissão → 403; não existe rota de exclusão.
  2. Cor primária sem contraste AA com o texto do tema → 422 no campo.
  3. O modo de matrícula `automatica` é aceito na configuração, mas o cadastro de membro continua só manual (T-28).
  4. O upload do logo devolve URL assinada de curta duração; a leitura também é por URL assinada.
  5. Fuso inválido → 422.
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green.
- **Regra de negócio associada:** nenhuma nova.
- **Status:** Pendente

---

## Fase C — Pessoas no painel (API)

## T-25 — Listas de valores e campos obrigatórios por vínculo
- **Objetivo:** criar `listas_valores` e `campos_obrigatorios`, com as rotas de manutenção protegidas por `pessoas.configurar` e auditadas.
- **Context Pack:** `context/T-25.md`
- **Arquivos de entrada:** `plan.md` §3.5, §7; T-09, T-15 concluídas.
- **Arquivos de saída:** `api/app/Modules/Pessoas/{Application,Presentation,Infrastructure}/Configuracao/**`, migrations.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Feature/Pessoas/ListasValoresTest.php`; `api/tests/Feature/Pessoas/CamposObrigatoriosTest.php`
- **Casos cobertos:**
  1. O administrador cria, renomeia, ordena e desativa itens de cada uma das seis listas; a secretaria → 403.
  2. Item desativado não é aceito em cadastro novo, mas continua exibido onde já foi usado.
  3. Os obrigatórios por vínculo só aceitam chaves do catálogo de campos.
  4. A organização nova começa com listas e obrigatórios vazios.
  5. Cada mudança gera auditoria.
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green (CA-30).
- **Regra de negócio associada:** RN-06, RN-13 — atualizar `docs/modules/pessoas.md`.
- **Status:** Pendente

## T-26 — Cadastro rápido com alerta de duplicidade
- **Objetivo:** implementar `GET /painel/pessoas/duplicidade` (enquanto digita) e `POST /painel/pessoas` (cadastro rápido), com o evento de entrada, "é outra pessoa" e a oferta de recuperar quem está na lixeira.
- **Context Pack:** `context/T-26.md`
- **Arquivos de entrada:** `plan.md` §6 (Duplicidade), §3.5; T-14, T-25 concluídas.
- **Arquivos de saída:** `api/app/Modules/Pessoas/Application/{Actions/CadastrarPessoa.php,Consultas/VerificarDuplicidade.php}`, migrations de `eventos_linha_do_tempo` e `pares_nao_duplicados`.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Feature/Pessoas/CadastroRapidoTest.php`; `api/tests/Feature/Pessoas/DuplicidadeTest.php`
- **Casos cobertos:**
  1. Nome + nascimento iguais (ignorando acento e caixa), CPF igual ou e-mail igual → a verificação aponta a pessoa existente antes de salvar.
  2. Duplicado em outra igreja → só nome, igreja e vínculo.
  3. Duplicado na lixeira → aponta com a ação "recuperar" (U-20).
  4. "É outra pessoa" grava o par e o cadastro segue; CPF igual → 422 `cpf_em_uso`.
  5. O cadastro salva com nome, celular ou e-mail, vínculo, igreja e "como chegou", e gera o evento `entrada` com "como chegou" como motivo.
  6. Sem celular e sem e-mail → 422; obrigatórios de BR-011 ausentes não impedem o cadastro.
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green (CA-01, CA-13).
- **Regra de negócio associada:** RN-01, RN-23 — atualizar `docs/modules/pessoas.md`.
- **Status:** Pendente

## T-27 — Ficha por seções, completude e foto
- **Objetivo:** implementar `PATCH /painel/pessoas/{id}` por seção, com validação por campo, permissão de categoria sensível, auditoria só com nomes de campos, completude pelo vínculo e upload da foto por URL assinada.
- **Context Pack:** `context/T-27.md`
- **Arquivos de entrada:** `plan.md` §3.5, §5, §7; T-14, T-25, T-26 concluídas.
- **Arquivos de saída:** `api/app/Modules/Pessoas/Application/Actions/EditarFicha.php`, `api/app/Modules/Pessoas/Domain/Completude.php`, `api/app/Modules/Pessoas/Presentation/**`.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Feature/Pessoas/FichaTest.php`; `api/tests/Unit/Pessoas/CompletudeTest.php`
- **Casos cobertos:**
  1. Com obrigatórios de Membro {nascimento, CEP}, um membro com só nascimento → 50%; com lista vazia → 100%.
  2. Editar a seção "documentos" sem `pessoas.sensiveis.documentos.editar` → 422 por campo.
  3. A auditoria da edição lista os nomes dos campos alterados, sem valores.
  4. Contato salvo em E.164 com "é WhatsApp"; e-mail em minúsculas.
  5. O upload da foto devolve URL assinada; a ficha devolve a foto por URL assinada.
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green (CA-14).
- **Regra de negócio associada:** RN-10, RN-13 — atualizar `docs/modules/pessoas.md`.
- **Status:** Pendente

## T-28 — Vínculo, situação e matrícula
- **Objetivo:** implementar a máquina de estados do vínculo (V→F→M), a inativação e reativação com motivo, a matrícula manual única e a recusa do modo automático, com os eventos na linha do tempo.
- **Context Pack:** `context/T-28.md`
- **Arquivos de entrada:** `plan.md` §6 (Transições), §3.5; T-26 concluída.
- **Arquivos de saída:** `api/app/Modules/Pessoas/Domain/Vinculo.php`, `api/app/Modules/Pessoas/Application/Actions/{AlterarVinculo,AlterarSituacao}.php`.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Unit/Pessoas/VinculoTest.php`; `api/tests/Feature/Pessoas/VinculoSituacaoTest.php`
- **Casos cobertos:**
  1. Visitante → Membro → 422 `transicao_vinculo_invalida`, com a mensagem de que precisa passar por Frequentador; voltar de etapa também é recusado.
  2. Mudança de vínculo, inativação ou reativação sem motivo (da lista correspondente) → 422.
  3. Com motivo → evento na linha do tempo com o motivo; a reativação volta ao mesmo vínculo.
  4. Frequentador → Membro no modo manual sem matrícula → 422 `matricula_obrigatoria`; com matrícula repetida → 422 `matricula_em_uso`.
  5. Com o modo `automatica` → 422 `matricula_automatica_indisponivel` (U-07).
  6. A mudança para Membro desliga `novo_convertido` (RN-18).
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green (CA-06, CA-15, CA-16).
- **Regra de negócio associada:** RN-03, RN-05, RN-06, RN-07 — atualizar `docs/modules/pessoas.md`.
- **Status:** Pendente

## T-29 — Linha do tempo manual, relacionamentos, funções e tipo de membro
- **Objetivo:** implementar o registro manual com data retroativa, os relacionamentos com tipos fixos e a inversa automática, as funções com período e o tipo de membro.
- **Context Pack:** `context/T-29.md`
- **Arquivos de entrada:** `plan.md` §3.5, §7; T-26, T-28 concluídas.
- **Arquivos de saída:** `api/app/Modules/Pessoas/Application/Actions/{RegistrarEventoManual,Relacionar,RemoverRelacionamento,AtribuirFuncao,EncerrarFuncao,DefinirTipoMembro}.php`, migrations de `relacionamentos` e `pessoa_funcoes`.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Feature/Pessoas/LinhaDoTempoTest.php`; `api/tests/Feature/Pessoas/RelacionamentosTest.php`; `api/tests/Feature/Pessoas/FuncoesTest.php`
- **Casos cobertos:**
  1. Registro manual com data retroativa aparece na ordem de `ocorrido_em`, com autor.
  2. "Pai/mãe" de A para B grava "filho(a)" de B para A; remover apaga as duas; "outro" exige descrição.
  3. Responsável legal ↔ dependente entre um adulto e uma criança (ASSUMPTION-005).
  4. Função com `desde` e `ate`; `ate` anterior a `desde` → 422; só funções ativas da lista.
  5. Mudar o tipo de membro gera o evento `tipo_membro_alterado`.
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green (CA-17, CA-18).
- **Regra de negócio associada:** nenhuma RN numerada (FR-011–013) — atualizar `docs/modules/pessoas.md`.
- **Status:** Pendente

## T-30 — Lista de pessoas com filtros e paginação
- **Objetivo:** implementar `GET /painel/pessoas` com 25/50/100 por página, ordenação e os filtros da U-17 (igreja, vínculo, situação, tipo de membro, função, ficha incompleta, aniversariantes do mês), com a projeção do alcance e a contagem com critério.
- **Context Pack:** `context/T-30.md`
- **Arquivos de entrada:** `plan.md` §6, §7; T-27, T-29 concluídas.
- **Arquivos de saída:** `api/app/Modules/Pessoas/Application/Consultas/ListarPessoas.php`, `api/app/Modules/Pessoas/Presentation/**`.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Feature/Pessoas/ListaPessoasTest.php`
- **Casos cobertos:**
  1. `per_page` aceita só 25, 50 e 100.
  2. Cada filtro restringe o resultado como esperado, inclusive "ficha incompleta" e "aniversariantes do mês" no fuso da organização.
  3. A secretaria só lista pessoas do seu alcance; pessoas na lixeira não aparecem.
  4. Sem permissão de sensíveis, as colunas sensíveis não vêm.
  5. O total informa o critério aplicado (`meta.criterio`), e "ativas por vínculo e igreja" bate com a definição oficial (RN-14).
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green (CA-19, parte da lista; CA-03, parte da lista).
- **Regra de negócio associada:** RN-14 — atualizar `docs/modules/pessoas.md`.
- **Status:** Pendente

## T-31 — Exportação XLSX/CSV por job
- **Objetivo:** implementar `POST /painel/exportacoes` (filtro ou selecionados; pessoas, aniversários e novos convertidos) e `GET /painel/exportacoes/{id}`, com o job na fila `pesadas` que recalcula o alcance, gera com OpenSpout, grava no S3/MinIO, avisa `servico.exportacao_pronta` e apaga em 24h.
- **Context Pack:** `context/T-31.md`
- **Arquivos de entrada:** `plan.md` §3.5, §6 (Exportação); T-17, T-30 concluídas.
- **Arquivos de saída:** `api/app/Modules/Pessoas/Application/{Actions/SolicitarExportacao.php,Jobs/GerarExportacao.php}`, migration de `exportacoes`.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Feature/Pessoas/ExportacaoTest.php`
- **Casos cobertos:**
  1. Sem `pessoas.exportar` → 403 + `export.denied` na auditoria.
  2. Com o filtro, o arquivo XLSX (e o CSV) tem só as linhas e as colunas que quem exportou vê; sem `fe.ver`, nenhuma coluna de fé.
  3. Exportar selecionados exporta só os ids informados que estão no alcance.
  4. Acesso revogado entre o pedido e o job → `negada`, sem arquivo.
  5. Concluída: URL assinada válida até `expira_em` (+24h); depois o job apaga o arquivo e o `GET` informa expirada.
  6. A auditoria registra pedida e concluída com filtro, categorias e contagem.
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green (CA-19, parte da exportação; CA-03, parte da exportação).
- **Regra de negócio associada:** RN-20 — atualizar `docs/modules/pessoas.md`.
- **Status:** Pendente

## T-32 — Possíveis duplicados e mesclagem
- **Objetivo:** implementar `GET /painel/duplicados`, `POST /painel/duplicados/nao-duplicados` e `POST /painel/mesclagens`, com a escolha campo a campo, o snapshot em `operacoes_desfaziveis`, a movimentação dos filhos e a recusa quando as duas pessoas têm conta.
- **Context Pack:** `context/T-32.md`
- **Arquivos de entrada:** `plan.md` §3.4, §6 (Mesclagem); T-26, T-29 concluídas.
- **Arquivos de saída:** `api/app/Modules/Pessoas/Application/Actions/MesclarPessoas.php`, `api/app/Modules/Auditoria/Contracts/OperacoesDesfaziveis.php`, migration de `operacoes_desfaziveis`.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Feature/Pessoas/MesclagemTest.php`
- **Casos cobertos:**
  1. A lista de duplicados mostra pares pela RN-23, sem a lixeira e sem pares "não duplicados".
  2. Mesclar com valores escolhidos: a mantida fica com os valores escolhidos, a absorvida é marcada com o motivo `mesclagem`, e contatos, relacionamentos, funções e eventos são movidos.
  3. A operação grava o snapshot cifrado e `expira_em` = +90 dias.
  4. As duas pessoas com conta → 422 `mesclagem_contas_conflitantes` (U-27).
  5. Sem `pessoas.mesclar` na igreja de uma das duas → 403.
  6. Eventos `mesclagem` nas duas e auditoria com os nomes dos campos.
  7. A absorvida fica com `cpf`, `cpf_hash` e `matricula` limpos, e os valores ficam só no snapshot; mesclar duas pessoas com o mesmo CPF não viola a unicidade (U-30).
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green (CA-21).
- **Regra de negócio associada:** RN-11 — atualizar `docs/modules/pessoas.md`.
- **Status:** Pendente

## T-33 — Desfazer mesclagem
- **Objetivo:** implementar `POST /painel/mesclagens/{id}/desfazer` como Action de compensação, com o conflito campo a campo, e a expiração da operação em 90 dias.
- **Context Pack:** `context/T-33.md`
- **Arquivos de entrada:** `plan.md` §6 (Mesclagem); T-32 concluída.
- **Arquivos de saída:** `api/app/Modules/Pessoas/Application/Actions/DesfazerMesclagem.php`, job de expiração.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Feature/Pessoas/DesfazerMesclagemTest.php`
- **Casos cobertos:**
  1. Desfazer logo após mesclar → as duas fichas voltam com os dados originais (inclusive CPF, `cpf_hash` e matrícula da absorvida) e os filhos voltam a cada uma.
  2. Campo da mantida alterado depois da mesclagem → fica o valor atual, e a resposta lista o conflito.
  3. Operação com mais de 90 dias → 422 `operacao_expirada`; o job de expiração apaga o snapshot **e a ficha absorvida** e marca `expirada` (U-33).
  4. Desfazer duas vezes → recusado.
  5. Evento `mesclagem_desfeita` e auditoria.
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green (CA-04).
- **Regra de negócio associada:** RN-11 — atualizar `docs/modules/pessoas.md`.
- **Status:** Pendente

## T-34 — Transferência entre igrejas
- **Objetivo:** implementar solicitar (destino), aprovar e recusar (origem, com justificativa), com um único pedido pendente por pessoa, os avisos de serviço e a mudança de igreja que mantém vínculo e matrícula.
- **Context Pack:** `context/T-34.md`
- **Arquivos de entrada:** `plan.md` §3.5, §6 (Transferência); T-17, T-28 concluídas.
- **Arquivos de saída:** `api/app/Modules/Pessoas/Application/Actions/{SolicitarTransferencia,AprovarTransferencia,RecusarTransferencia}.php`, migration de `transferencias`.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Feature/Pessoas/TransferenciaTest.php`
- **Casos cobertos:**
  1. A secretaria de Y solicita a transferência de uma pessoa de X → `solicitada`, com aviso às contas que têm `pessoas.transferencias.aprovar` em X.
  2. Segundo pedido para a mesma pessoa com um pendente → 422 `transferencia_pendente`.
  3. Aprovar (secretaria de X) → `igreja_id` = Y, mesmo vínculo e mesma matrícula, evento `transferencia`; Y passa a abrir a ficha e X deixa de abrir.
  4. Recusar sem justificativa → 422; com justificativa → vínculo inalterado e aviso a Y.
  5. A secretaria de Y não aprova o próprio pedido (sem permissão em X) → 403.
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green (CA-05, CA-22).
- **Regra de negócio associada:** RN-04, RN-08 — atualizar `docs/modules/pessoas.md`.
- **Status:** Pendente

## T-35 — Lixeira, removedor de Pessoas e purga
- **Objetivo:** implementar excluir (motivo), listar a lixeira, recuperar em até 30 dias, o removedor com os modos `anonimizar` e `apagar` (recusa em produção sem modo), `exclusoes_executadas` e o job diário de purga por organização.
- **Context Pack:** `context/T-35.md`
- **Arquivos de entrada:** `plan.md` §3.5 (Removedor), §6 (Lixeira); T-26, T-29 concluídas.
- **Arquivos de saída:** `api/app/Modules/Pessoas/Application/Actions/{ExcluirPessoa,RecuperarPessoa}.php`, `api/app/Modules/Pessoas/Infrastructure/RemovedorPessoas.php`, `api/app/Modules/Pessoas/Application/Jobs/PurgarLixeira.php`, migration de `exclusoes_executadas`.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Feature/Pessoas/LixeiraTest.php`; `api/tests/Feature/Pessoas/RemovedorPessoasTest.php`
- **Casos cobertos:**
  1. Excluir → some da lista, da busca, das exportações e dos duplicados, e aparece na lixeira.
  2. Recuperar com menos de 30 dias → volta com todos os dados.
  3. A purga diária, por organização e com contexto, remove as vencidas no modo configurado.
  4. `anonimizar`: apaga contatos, documentos, saúde, endereço, foto e textos livres; o nome vira "Pessoa removida"; vínculo e datas ficam.
  5. `apagar`: remove a pessoa e o que só existe por ela; nos dois modos, apaga snapshots e grava `exclusoes_executadas`.
  6. Em `production` sem `lgpd.modo_exclusao` → recusa com `lgpd.erasure.blocked_no_policy`.
  7. A purga ignora a ficha absorvida por mesclagem enquanto a operação estiver `executada`, mesmo depois de 30 dias (U-33).
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green (CA-07).
- **Regra de negócio associada:** RN-19 — atualizar `docs/modules/pessoas.md`.
- **Status:** Pendente

## T-36 — Novos convertidos e datas comemorativas
- **Objetivo:** implementar o registro de conversão e de batismo, `GET /painel/novos-convertidos` e `GET /painel/aniversarios` (nascimento, batismo, casamento; dia, semana, mês, no fuso da organização).
- **Context Pack:** `context/T-36.md`
- **Arquivos de entrada:** `plan.md` §6, §7; T-28, T-30 concluídas.
- **Arquivos de saída:** `api/app/Modules/Pessoas/Application/{Actions/RegistrarConversao.php,Actions/RegistrarBatismo.php,Consultas/NovosConvertidos.php,Consultas/Aniversarios.php}`.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Feature/Pessoas/NovosConvertidosTest.php`; `api/tests/Feature/Pessoas/AniversariosTest.php`
- **Casos cobertos:**
  1. Registrar a conversão (data, canal) → a pessoa entra em novos convertidos da sua igreja.
  2. Registrar o batismo ou mudar o vínculo → sai da lista; a conversão continua na ficha.
  3. Sem `pessoas.sensiveis.fe.ver` → novos convertidos e aniversários de batismo → 403.
  4. Aniversários do dia, da semana e do mês respeitam o fuso da organização (inclusive a virada do ano) e o alcance.
  5. Pessoas na lixeira não aparecem.
  6. As contagens de novos convertidos e de aniversários vêm com `meta.criterio` (RN-14; U-30).
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green (CA-23, CA-24; a exportação vem da T-31).
- **Regra de negócio associada:** RN-18 — atualizar `docs/modules/pessoas.md`.
- **Status:** Pendente

---

## Fase D — Painel web (`web/`, entrada `painel`)

## T-37 — Base do painel: http, formulários, i18n, tema e observabilidade
- **Objetivo:** montar `app/painel` (TanStack Router e Query, tema MUI com o logo, Error Boundary com `trace_id`, i18next `pt-BR`), `shared/http` (cabeçalhos e tratamento de 401/419/403/409), `shared/forms` (422 → campo) e OTel no navegador.
- **Context Pack:** `context/T-37.md`
- **Arquivos de entrada:** `plan.md` §8; T-05 concluída.
- **Arquivos de saída:** `web/src/app/painel/**`, `web/src/shared/{http,forms,ui,i18n}/**`, `web/eslint.config.js` (boundaries).

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `web/src/shared/http/__tests__/cliente.test.ts`; `web/src/shared/forms/__tests__/erros422.test.tsx`; `web/src/app/painel/__tests__/errorBoundary.test.tsx`
- **Casos cobertos:**
  1. Todo pedido envia `credentials: include`, `X-XSRF-TOKEN`, `X-Organizacao-Esperada`, `X-Cliente: painel@<build>` e `traceparent`.
  2. 419 → renova o CSRF e repete uma vez; 401 → limpa o cache e vai ao login com a URL de volta.
  3. 403 `elevacao_necessaria` → abre o diálogo e repete o pedido após elevar, sem perder o formulário.
  4. 409 `organizacao_trocada` → recarrega e publica no `BroadcastChannel`.
  5. 422 com `contatos.0.valor` → erro no campo, foco no primeiro erro e resumo com os erros de campos fora da tela.
  6. O Error Boundary mostra o `trace_id`.
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green; `pnpm gate` verde, com `size-limit` dentro de 350 KB.
- **Regra de negócio associada:** nenhuma.
- **Status:** Pendente

## T-38 — Telas de identidade: login, 2FA, elevação, convite e recuperação
- **Objetivo:** criar a feature `Identidade` com login, desafio e cadastro de 2FA, códigos de recuperação, diálogo de elevação, aceite de convite (com definição de senha), recuperação de senha e seletor de organização.
- **Context Pack:** `context/T-38.md`
- **Arquivos de entrada:** `plan.md` §7, §8; T-12, T-16, T-22, T-37 concluídas.
- **Arquivos de saída:** `web/src/features/Identidade/**`.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `web/src/features/Identidade/__tests__/*.test.tsx`
- **Casos cobertos:**
  1. Login com credencial inválida mostra a mensagem genérica; com 2FA pede o código.
  2. Administrador sem 2FA é levado ao cadastro do 2FA e vê os 10 códigos de recuperação uma vez.
  3. O aceite de convite com token inválido mostra "convite inválido ou expirado"; com token válido, pede senha (conta nova) ou login.
  4. O diálogo de elevação aceita senha + código e fecha.
  5. Com dois acessos, o seletor de organização lista e troca.
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green; textos só no namespace i18n da feature.
- **Regra de negócio associada:** nenhuma nova.
- **Status:** Pendente

## T-39 — Telas de organização, igrejas, listas, obrigatórios e WhatsApp
- **Objetivo:** criar as telas de configuração da organização (logo, cor, fuso, modo de matrícula), igrejas, listas de valores, obrigatórios por vínculo e ligar/desligar WhatsApp.
- **Context Pack:** `context/T-39.md`
- **Arquivos de entrada:** `plan.md` §8; T-21, T-24, T-25, T-37 concluídas.
- **Arquivos de saída:** `web/src/features/{Organizacao,Pessoas/configuracao,Avisos/whatsapp}/**`.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `web/src/features/Organizacao/__tests__/*.test.tsx`; `web/src/features/Pessoas/__tests__/configuracao.test.tsx`; `web/src/features/Avisos/__tests__/whatsapp.test.tsx`
- **Casos cobertos:**
  1. A cor recusada pelo servidor (422) mostra o erro no campo.
  2. A lista de igrejas permite criar e editar e não oferece excluir.
  3. Listas: adicionar, reordenar e desativar um item; a desativação reflete na lista.
  4. Obrigatórios: marcar campos por vínculo e salvar.
  5. Sem `meta.acoes`/capacidade, os botões não aparecem, e o servidor continua sendo a autoridade.
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green.
- **Regra de negócio associada:** nenhuma nova.
- **Status:** Pendente

## T-40 — Telas de acessos, convites, papéis e auditoria
- **Objetivo:** criar as telas de acessos (convidar Pessoa com papel e escopo, revogar), papéis (criar e editar permissões do catálogo, com marcação de sensíveis) e consulta de auditoria com filtros.
- **Context Pack:** `context/T-40.md`
- **Arquivos de entrada:** `plan.md` §8; T-15, T-22, T-37, T-46 concluídas.
- **Arquivos de saída:** `web/src/features/{Autorizacao,Auditoria}/**`.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `web/src/features/Autorizacao/__tests__/*.test.tsx`; `web/src/features/Auditoria/__tests__/*.test.tsx`
- **Casos cobertos:**
  1. Convidar: escolher a Pessoa pela busca, o papel e o escopo; o 422 de pessoa sem contato aparece no campo.
  2. Revogar um acesso pede confirmação e atualiza a lista.
  3. Papel: as permissões vêm do catálogo, agrupadas, e as sensíveis por categoria; `ultimo_administrador` e `escalada_bloqueada` mostram mensagens próprias.
  4. Auditoria: filtrar por período e evento; a tela não mostra valores de campo (só nomes).
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green.
- **Regra de negócio associada:** nenhuma nova.
- **Status:** Pendente

## T-41 — Telas de lista de pessoas, cadastro rápido e busca
- **Objetivo:** criar a lista com DataGrid MIT (25/50/100, filtros na URL, seleção e "exportar selecionados"), o cadastro rápido com alerta de duplicidade e "salvar e cadastrar outro", e a busca na denominação.
- **Context Pack:** `context/T-41.md`
- **Arquivos de entrada:** `plan.md` §8; T-26, T-30, T-31, T-37 concluídas.
- **Arquivos de saída:** `web/src/features/Pessoas/{lista,cadastro,busca}/**`.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `web/src/features/Pessoas/__tests__/{lista,cadastroRapido,busca}.test.tsx`
- **Casos cobertos:**
  1. Mudar filtro, página ou tamanho atualiza a URL, e voltar preserva a lista.
  2. Selecionar linhas e "exportar selecionados" envia os ids e o formato escolhido.
  3. Digitar nome + nascimento iguais mostra o alerta com "Abrir ficha" e "É outra pessoa"; na lixeira, "Recuperar ficha".
  4. "Salvar e cadastrar outro" limpa o formulário e mantém a igreja.
  5. Na busca, resultado de outra igreja mostra só nome, igreja e vínculo, com "Pedir transferência".
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green.
- **Regra de negócio associada:** nenhuma nova.
- **Status:** Pendente

## T-42 — Tela da ficha por seções
- **Objetivo:** criar a ficha com seções (pessoais, documentos, contatos, endereço, saúde, eclesiásticos, adicionais), barra de completude, foto, diálogos de vínculo e situação com motivo, linha do tempo (com registro manual), relacionamentos e funções.
- **Context Pack:** `context/T-42.md`
- **Arquivos de entrada:** `plan.md` §8; T-27, T-28, T-29, T-36, T-37 concluídas.
- **Arquivos de saída:** `web/src/features/Pessoas/ficha/**`.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `web/src/features/Pessoas/__tests__/ficha.test.tsx`
- **Casos cobertos:**
  1. Seção sensível ausente na resposta não é renderizada (sem placeholder com dado).
  2. A barra mostra o percentual que vem da API, com o critério.
  3. O diálogo de vínculo oferece só o próximo passo e exige motivo da lista; Membro no modo manual pede matrícula.
  4. A linha do tempo lista eventos com motivo e permite registro manual com data passada.
  5. Adicionar relacionamento "pai/mãe" mostra a relação nas duas fichas (após recarregar).
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green.
- **Regra de negócio associada:** nenhuma nova.
- **Status:** Pendente

## T-43 — Telas de duplicados, mesclagem, transferências e lixeira
- **Objetivo:** criar a lista de duplicados, a comparação campo a campo com escolha, a tela da operação com "desfazer" e os conflitos, as transferências (enviadas e recebidas, aprovar e recusar com justificativa) e a lixeira com recuperar.
- **Context Pack:** `context/T-43.md`
- **Arquivos de entrada:** `plan.md` §8; T-32, T-33, T-34, T-35, T-37 concluídas.
- **Arquivos de saída:** `web/src/features/Pessoas/{duplicados,transferencias,lixeira}/**`.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `web/src/features/Pessoas/__tests__/{mesclagem,transferencias,lixeira}.test.tsx`
- **Casos cobertos:**
  1. A comparação mostra só os campos divergentes, e a escolha de cada um vai no pedido.
  2. `mesclagem_contas_conflitantes` mostra a mensagem que orienta revogar um acesso.
  3. Desfazer com conflitos lista os campos que ficaram com o valor atual.
  4. Recusar transferência exige justificativa.
  5. A lixeira mostra os dias restantes, e "recuperar" pede confirmação.
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green.
- **Regra de negócio associada:** nenhuma nova.
- **Status:** Pendente

## T-44 — Telas de novos convertidos, datas, exportações e caixa de avisos
- **Objetivo:** criar as listas de novos convertidos e de aniversários (dia, semana, mês) com exportação, a tela de exportações (estado e download), a caixa de avisos com contador e as preferências de aviso.
- **Context Pack:** `context/T-44.md`
- **Arquivos de entrada:** `plan.md` §8; T-20, T-31, T-36, T-37 concluídas.
- **Arquivos de saída:** `web/src/features/{Pessoas/datas,Pessoas/convertidos,Pessoas/exportacoes,Avisos/caixa}/**`.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `web/src/features/Pessoas/__tests__/{datas,convertidos,exportacoes}.test.tsx`; `web/src/features/Avisos/__tests__/caixa.test.tsx`
- **Casos cobertos:**
  1. Trocar dia, semana ou mês recarrega a lista; exportar pede o formato (XLSX/CSV).
  2. A exportação `processando` mostra o estado; `concluida` mostra o download até expirar; `negada` explica.
  3. O contador de não lidos atualiza ao focar a aba e a cada ~60 s com a aba visível (timers falsos).
  4. Marcar todos como lidos zera o contador.
  5. Nas preferências, tipos obrigatórios não podem ser desligados.
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green.
- **Regra de negócio associada:** nenhuma nova.
- **Status:** Pendente

## T-45 — Jornadas críticas no Playwright + axe (noturno)
- **Objetivo:** cobrir no Playwright, contra a API real com PostgreSQL e dados fictícios, as jornadas login com 2FA, troca de organização, ficha com categoria sensível e elevação no painel, com axe falhando em violação séria ou crítica.
- **Context Pack:** `context/T-45.md`
- **Arquivos de entrada:** `plan.md` §8, §9; T-23, T-38, T-42 concluídas.
- **Arquivos de saída:** `web/e2e/**`, `web/playwright.config.ts`, `.github/workflows/noturno.yml`.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `web/e2e/{login2fa,trocaOrganizacao,fichaSensivel,elevacao}.spec.ts`
- **Casos cobertos:**
  1. O administrador (seed) entra com senha + código de 2FA (adapter falso) e chega ao painel.
  2. Uma conta com dois acessos troca de organização, e outra aba recebe o 409 e recarrega.
  3. Uma secretaria sem `saude.ver` abre uma ficha e não vê a seção saúde; o administrador vê.
  4. A elevação expirada (relógio avançado) abre o diálogo e preserva o formulário.
  5. O axe não aponta violação séria ou crítica em nenhuma tela das jornadas.
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green no workflow noturno.
- **Regra de negócio associada:** nenhuma nova.
- **Status:** Pendente

---

## Complemento da Fase B (análise de consistência, U-30)

## T-46 — Consulta da auditoria (API)
- **Objetivo:** expor `GET /painel/auditoria`, paginado, com filtros de período, evento, ator e alvo, só para quem tem `auditoria.ver`, respeitando o alcance e sem valores de campo.
- **Context Pack:** `context/T-46.md`
- **Arquivos de entrada:** `plan.md` §3.4, §7; T-07, T-13, T-16 concluídas. Executar antes da T-40.
- **Arquivos de saída:** `api/app/Modules/Auditoria/{Application/Consultas/ConsultarAuditoria.php,Presentation/**}`.

### Teste que define esta tarefa (ciclo TDD)
- **Arquivo de teste:** `api/tests/Feature/Auditoria/ConsultaAuditoriaTest.php`
- **Casos cobertos:**
  1. Sem `auditoria.ver` (ex.: Secretaria pronta) → 403.
  2. Com `auditoria.ver`, a lista vem paginada e ordenada por `ocorrido_em` decrescente.
  3. Os filtros de período, evento, ator e alvo restringem o resultado.
  4. Com `auditoria.ver` só no escopo de uma igreja, eventos de outra igreja não aparecem (alcance, ADR-0005 §3).
  5. A resposta traz nomes de campos e metadados codificados, nunca valores.
  6. Só eventos da organização ativa aparecem (RLS).
- **Resultado esperado antes do código existir:** FALHAR (Red), com aprovação do usuário antes da implementação.
- **Critério de aceite final:** testes Green (CA-12, parte da consulta).
- **Regra de negócio associada:** RN-15 — atualizar `docs/modules/auditoria.md`.
- **Status:** Pendente

---

## Cobertura dos critérios de aceite da spec

| CA | Tasks |
|---|---|
| CA-01 | T-26, T-41 |
| CA-02 | T-22 |
| CA-03 | T-14 (ficha, busca), T-30 (lista), T-31 (exportação), T-45 |
| CA-04 | T-33 |
| CA-05 | T-34 |
| CA-06 | T-28 |
| CA-07 | T-35 |
| CA-08 | T-03, T-08 e toda task com tabela nova (teste de catálogo) |
| CA-09 | T-16, T-22 |
| CA-10 | T-16, T-12 |
| CA-11 | T-15 |
| CA-12 | T-07 + a auditoria de cada Action (teste de arquitetura da T-07), T-46 (consulta) |
| CA-13 | T-26 |
| CA-14 | T-27 |
| CA-15 | T-28 |
| CA-16 | T-28 |
| CA-17 | T-29 |
| CA-18 | T-29 |
| CA-19 | T-30, T-31, T-41 |
| CA-20 | T-14 |
| CA-21 | T-32 |
| CA-22 | T-34 |
| CA-23 | T-36 |
| CA-24 | T-36, T-31 |
| CA-25 | T-17, T-18 |
| CA-26 | T-17 |
| CA-27 | T-09 |
| CA-28 | T-06 |
| CA-29 | T-02, T-05 |
| CA-30 | T-25 |

## Notas de execução
- Nenhuma tarefa começa a implementação sem o teste correspondente escrito, aprovado e confirmado como falho (Red) primeiro.
- Implementar uma tarefa por vez, uma task por sessão (CTX-001). Revisar o diff antes de iniciar a próxima.
- Ordem: Fase A → B → C → D. A T-46 (complemento da Fase B) roda depois da T-16 e antes da T-40. Dentro de cada fase, respeitar as dependências citadas em "Arquivos de entrada".
- Branch por task, curta, a partir da `main` (ADR-0010 §7), com o nome `t-NN-<slug>` (ex.: `t-03-rls-banco`; U-34).
- Se surgir necessidade de decisão arquitetural não prevista no plano, PARAR e acionar a skill `adr-arquitetura` antes de continuar.
- Ao concluir uma tarefa que envolve regra de negócio, acionar a skill `docs-modulo` para manter `docs/modules/<modulo>.md` atualizado.
- A matrícula automática não tem task: fica bloqueada pelo formato em aberto (U-07). Quando o formato for definido, abrir uma task nova nesta Spec.

## Análise de consistência (Etapa 7, 2026-10-23)

Releitura de `spec.md`, `plan.md`, `tasks.md`, dos itens de ADR citados e do `AGENTS.md`. Foram encontrados 10 pontos, todos resolvidos pelo usuário (U-30 a U-34):

| # | Tipo | Ponto | Resolução |
|---|---|---|---|
| 1 | Contradição (ordem) | `atribuicoes_papel` (T-13) dependia de `acessos`, criada só na T-16 | `acessos` passa para a T-10 (U-30) |
| 2 | Contradição | Ficha absorvida na lixeira manteria o CPF, o `cpf_hash` e a matrícula, violando as unicidades | A mesclagem limpa esses campos na absorvida, o snapshot guarda os valores e o desfazer os devolve (U-30; T-32, T-33) |
| 3 | Lacuna | Eventos de autenticação (ADR-0005 §5, §10) sem destino antes do contexto de organização | Tabela `eventos_autenticacao` fora da RLS por organização, retenção de 1 ano (U-31; T-10, T-12, T-16) |
| 4 | Lacuna | CA-12 ("só quem tem `auditoria.ver` consulta") sem task de backend | Nova T-46 (U-30) |
| 5 | Lacuna | Fila `webhooks` sem worker (ADR-0011 §2) | `worker-geral` consome `avisos,webhooks,default` (U-30; T-17) |
| 6 | Lacuna | RN-14 testada só na lista | Caso de `meta.criterio` na T-36 (U-30) |
| 7 | Lacuna | Duração da sessão base sem task | 30 dias renovada com o uso, na T-10 (U-30) |
| 8 | Ambiguidade | O login no painel conta como elevação? | Sim (U-32; T-16) |
| 9 | Ambiguidade | Destino da ficha absorvida após 90 dias e conflito com a purga de 30 dias | A purga a ignora enquanto a operação está `executada`; na expiração, o job a apaga com o snapshot (U-33; T-33, T-35) |
| 10 | Ambiguidade | Nome das branches | `t-NN-<slug>` (U-34) |

**Testabilidade:** todas as tasks têm teste com resultado objetivo. A validação da T-21 contra a Meta real depende do cadastro de *Tech Provider* (ação do usuário) e fica registrada como pendência externa.
