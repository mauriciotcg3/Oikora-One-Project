# OIK-PLAN-0001: Fundação + Pessoas no painel

**Spec relacionada:** OIK-SPEC-0001 (`spec.md`, aprovada em 2026-10-21)
**Status:** Aprovado (usuário, 2026-10-21)
**Data:** 2026-10-21

Convenções de citação iguais às da `spec.md` (`P …`, `ADR-000N §i` = OIK-ADR-000N item *i*, `U-NN`). Escolhas locais que os ADRs deixam para a Spec foram aprovadas com o plano: U-27 (mesclagem de duas pessoas com conta é recusada), U-28 (motivos em três listas; "como chegou" é o motivo da entrada), U-29 (bibliotecas locais da §9 e `uncompromised()`). A análise de consistência (2026-10-23) acrescentou U-30 a U-34.

## 1. Estrutura do repositório e dos módulos

```text
Oikora-One-Project/
├── package.json, pnpm-workspace.yaml           # workspace pnpm (ADR-0006 §1)
├── versoes.env                                  # arquivo único de versões (ADR-0011 §3, U-15) (nome aprovado com o plano)
├── compose.yaml                                 # dev local: postgres 17, php-fpm, caddy, minio, worker-codigos (`codigos`), worker-geral (`avisos,webhooks,default`; U-30), worker-pesadas (`pesadas`), scheduler (ADR-0011 §2, §4; U-13; U-19)
├── .github/workflows/                           # CI (ADR-0010 §7)
├── api/                                         # Laravel (ADR-0001 §1)
│   └── app/
│       ├── Shared/Tenancy/                      # contexto de organização + RLS (sem dono de negócio)
│       ├── Shared/Observabilidade/              # request_id, redação de log, OTel (ADR-0010 §9)
│       └── Modules/
│           ├── Organizacao/                     # organizações, regionais (schema), igrejas, configuração, provisionamento
│           ├── Identidade/                      # contas, login, códigos, 2FA, convites, acessos, elevação, organização ativa
│           ├── Autorizacao/                     # catálogo de permissões, papéis, atribuições, alcance, projeção
│           ├── Auditoria/                       # auditoria, auditoria_plataforma, operações desfazíveis
│           ├── Pessoas/                         # ficha, vínculo, matrícula, linha do tempo, relacionamentos, funções, listas, obrigatórios, duplicados, mesclagem, transferência, lixeira, exportação, datas
│           └── Avisos/                          # catálogo de tipos, caixa de saída, canais, caixa de avisos, webhooks, uso
├── web/                                         # Vite; nesta Spec só a entrada `painel` (ADR-0006 §1; U-01)
│   └── src/{app/painel, features/<Modulo>/, shared/{http,ui,i18n,forms}}
└── packages/api-contrato/                       # openapi.json → tipos + cliente openapi-fetch (ADR-0006 §8)
```

- Cada módulo usa só as camadas de que precisa (MOD-004, citado pela ADR-0010 §1 via Deptrac). Entre módulos, comunicação só por `Contracts/` ou por eventos `afterCommit` (ADR-0008 §2; DB-001, COMM-*). As FKs entre tabelas de módulos diferentes são integridade do banco, não acesso a Model.
- Cada caso de uso é uma Action única (ADR-0001 §1). Cada Action declara a permissão exigida (ADR-0004 §5) e grava a auditoria na mesma transação (ADR-0005 §2).
- `features/<Modulo>/` no `web/` espelha os módulos Laravel, com Public API em `index.ts` (ADR-0006 §1).

## 2. Isolamento por organização (E-03)

- **Papéis de banco** (ADR-0002 §5):
  - `oikora_dono` — migrations e purga de partição;
  - `oikora_app` — API e workers, sem `BYPASSRLS` e sem ser dono;
  - `oikora_plataforma` — `BYPASSRLS`, só no comando de provisionamento.
  No Laravel são três conexões: `pgsql` (app, padrão), `pgsql_dono` e `pgsql_plataforma`.
- **Contexto:** a função SQL `app_organizacao_atual()` lê `current_setting('app.organizacao_id', true)`. Se o valor está vazio, ela levanta uma exceção com a mensagem `tenant.context.missing` (ADR-0002 §4: erro, não lista vazia).
- **Política padrão** de toda tabela com `organizacao_id`: `ENABLE` + `FORCE ROW LEVEL SECURITY`; `USING (organizacao_id = app_organizacao_atual())` e `WITH CHECK` igual (ADR-0002 §4).
- **Middleware HTTP** `ContextoOrganizacao`:
  1. abre a transação da requisição;
  2. resolve a organização ativa guardada na sessão;
  3. revalida contra os acessos ativos da conta (ADR-0003 §12);
  4. confere `X-Organizacao-Esperada` → 409 `organizacao_trocada` (ADR-0006 §10);
  5. executa `select set_config('app.organizacao_id', $id, true)` e `set_config('app.conta_id', …, true)` (ADR-0011 §3);
  6. calcula o alcance (§5).
  Rotas sem organização (login, convite, saúde, webhooks) não usam esse middleware.
- **Middleware de job** `ContextoOrganizacaoJob` (ADR-0008 §5): confere se a organização existe e está ativa (ou `em_migracao`), abre a transação, grava o contexto e o limpa. Sem `organizacao_id`, o job falha sem nova tentativa. A lista fechada de jobs sem organização é marcada pela interface `JobSemOrganizacao`.
- **Helper de migration** `$table->organizacao()`: cria `organizacao_id`, a FK, a chave única `(organizacao_id, id)`, a policy e o `FORCE`. A macro `foreignComposta()` cria a FK `(organizacao_id, x_id)` (ADR-0002 §6).
- **Chaves:** `id bigint generated always as identity` + `uuid` único, que é o único identificador exposto na `/api/v1` (ADR-0002 §9).
- **Tabelas fora da RLS por organização (lista fechada, ADR-0002 §2, ADR-0003 §3):** `organizacoes`, `contas`, `sessions`, `personal_access_tokens`, `codigos_verificacao`, `eventos_autenticacao` (U-31), `jobs`, `job_batches`, `failed_jobs`, `cache`, `cache_locks`, `auditoria_plataforma`, `migrations`. `contas` tem a RLS de identidade da §4. O teste de catálogo (ADR-0002 §12, ADR-0010 §1) conhece esta lista.

## 3. Modelo de dados

Todas as tabelas abaixo, exceto as da lista fechada da §2, têm `organizacao_id`, RLS forçada, `id` + `uuid`, `created_at`/`updated_at` e FKs compostas.

### 3.1 Organizacao (E-04)

| Tabela | Campos principais | Fonte |
|---|---|---|
| `organizacoes` | `nome`, `estado` (`ativa`, `em_migracao`), `fuso` (padrão `America/Sao_Paulo`), `logo_path`, `cor_primaria`, `modo_matricula` (`manual`, `automatica`; padrão `manual`), `limite_whatsapp_mensal` (nulo = sem limite) | ADR-0002 §2, ADR-0009 §5, ADR-0008 §14, §16, ADR-0006 §7, U-04, U-18 |
| `regionais` | `nome` (sem tela nesta Spec) | ADR-0002 §8, U-26 |
| `igrejas` | `nome`, `regional_id` (anulável, FK composta) | P FR-001, ADR-0002 §8 |

### 3.2 Identidade (E-05)

| Tabela | Campos principais | Fonte |
|---|---|---|
| `contas` | `email` (minúsculo, único), `email_verificado_em`, `celular` (E.164, único), `celular_verificado_em`, `password`, `estado` (`ativa`, `bloqueada`), `dois_fatores_canal` (`email`, `whatsapp`, `totp`, nulo), colunas TOTP/recuperação do Fortify (`two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`) | ADR-0003 §1, §7, §14 |
| `sessions` | tabela de sessão do Laravel (`user_id` = conta), para listar e encerrar sessões | ADR-0003 §9, ADR-0011 §3 |
| `codigos_verificacao` | `conta_id` (ou `identificador_hash` antes da conta), `finalidade` (`verificar_email`, `verificar_celular`, `dois_fatores`, `recuperar_senha`), `codigo_hash`, `canal`, `expira_em`, `usado_em`, `tentativas`, `envio_estado`, `envio_id_externo`, `envio_erro` | ADR-0003 §4, §7, §8; ADR-0008 §5, §12 |
| `acessos` | `conta_id`, `pessoa_id`, `estado` (`ativo`, `revogado`), `origem` (`convite`; `pedido_vinculo` na 0002), `revogado_em`, `revogado_por_acesso_id`; únicos parciais (org, conta) e (org, pessoa) com `estado = ativo` | ADR-0003 §2, P BR-002 |
| `convites` | `pessoa_id`, `papel_id`, `escopo_tipo`, `escopo_id`, `token_hash`, `expira_em` (+72h), `estado` (`enviado`, `aceito`, `expirado`, `revogado`), `emitido_por_acesso_id`, `aceito_em`, `revogado_em` | P FR-002, §12; ADR-0003 §5 |
| `eventos_autenticacao` | `conta_id` (nulo se o identificador não existe), `identificador_hash`, `evento` (`auth.*`), `ip_hash`, `canal`, `request_id`, `ocorrido_em`; só inclusão (`oikora_app` com `INSERT`/`SELECT`); purga diária do que passa de 1 ano | ADR-0005 §5, §10; U-31 |

- **RLS de identidade em `contas`** (ADR-0003 §3): visível se `id = app.conta_id` ou se existe acesso ativo na organização do contexto. Login, recuperação e unicidade usam as funções `SECURITY DEFINER` `identidade_localizar_conta(identificador)` (retorno mínimo: id, hash da senha, estado, canal de 2FA) e `identidade_identificador_em_uso(identificador)` (booleano).
- **Organização ativa** e **elevação** (`elevada_em`, `ultimo_uso_elevacao_em`) ficam na sessão (ADR-0003 §9, §12).
- **Sessão base:** 30 dias, renovada com o uso (ADR-0003 §9; U-30). `acessos` é criada junto com `contas` (T-10), porque `atribuicoes_papel` depende dela (U-30).

### 3.3 Autorizacao (E-06)

| Tabela | Campos principais | Fonte |
|---|---|---|
| `papeis` | `nome`, `sistema` (`administrador`, `secretaria`, nulo) | ADR-0004 §2, §3; P BR-018 |
| `papel_permissoes` | `papel_id`, `permissao` (valor do enum) | ADR-0004 §1, §2 |
| `atribuicoes_papel` | `acesso_id`, `papel_id`, `escopo_tipo` (`organizacao`, `regional`, `igreja`), `escopo_id` | ADR-0004 §2 |

**Catálogo `Permissao` (enum PHP, ADR-0004 §1)** — lista fechada desta Spec:

- **Organização e acesso:** `organizacao.configurar`, `igrejas.gerir`, `acessos.gerir`, `papeis.gerir`, `auditoria.ver`, `avisos.whatsapp.gerir`, `pessoas.configurar` (obrigatórios, listas de valores e modo de matrícula: U-06, U-09, U-18).
- **Pessoas:** `pessoas.ver`, `pessoas.criar`, `pessoas.editar` (ficha, vínculo, situação, funções, relacionamentos, linha do tempo manual), `pessoas.excluir` (lixeira e recuperar), `pessoas.exportar`, `pessoas.mesclar`, `pessoas.transferencias.solicitar`, `pessoas.transferencias.aprovar`.
- **Sensíveis:** `pessoas.sensiveis.{saude,documentos,contatos,fe}.{ver,editar}`.

**Papéis prontos** (criados no provisionamento, ADR-0004 §3):

- **Administrador:** todas as permissões, escopo `organizacao`.
- **Secretaria:** todas as `pessoas.*` exceto `pessoas.configurar`, mais sensíveis `documentos`, `contatos` e `fe` (`ver` e `editar`); `saude` só se o administrador marcar. Escopo atribuído por igreja.

**Serviço `Alcance`** (ADR-0004 §4): calculado por requisição a partir das atribuições, como `permissao → conjunto de igreja_id` (organização e regional expandem). Sem cache entre requisições.

### 3.4 Auditoria (E-07, operações desfazíveis)

| Tabela | Campos principais | Fonte |
|---|---|---|
| `auditoria` | particionada por mês em `ocorrido_em`; `evento`, `acesso_id`, `conta_id`, `operador_id` (nulo), `autorizacao_suporte_id` (nulo; U-11), `em_nome_de_pessoa_id` (nulo), `alvo_tipo`, `alvo_id`, `igreja_id`, `campos` (nomes), `metadados` (jsonb codificado), `canal`, `request_id`. `oikora_app` só tem `INSERT`/`SELECT` | ADR-0005 §2, §3 |
| `auditoria_plataforma` | `comando`, `operador`, `motivo`, `organizacoes` | ADR-0005 §4 |
| `operacoes_desfaziveis` | `tipo` (`mesclagem`; `importacao`/`migracao` depois), `autor_acesso_id`, `estado` (`executada`, `desfeita`, `expirada`), `executada_em`, `expira_em` (+90 dias), `snapshot` (jsonb cifrado com a chave de campos), `afetados` (jsonb) | ADR-0005 §6 |

- **Catálogo `EventoAuditoria`** (enum PHP): um evento por Action desta Spec, mais `auth.elevacao` e os demais `auth.*` ocorridos dentro de uma organização (os eventos de autenticação em si vão para `eventos_autenticacao`, §3.2; U-31), `authz.escalation_blocked`, `authz.last_admin_blocked`, `export.requested|completed|denied` e os eventos de canal (ligar/desligar WhatsApp, mudar limite) — ADR-0005 §5, ADR-0008 §17.
- **Retenção:** ações 5 anos; eventos de autenticação 1 ano; snapshot 90 dias — ADR-0005 §10. A purga é `DROP` de partição pela conexão `pgsql_dono` num comando agendado.

### 3.5 Pessoas (E-09 a E-14)

| Tabela | Campos principais | Fonte |
|---|---|---|
| `pessoas` | `nome`, `nome_busca` (minúsculo, sem acento, calculado ao gravar; índice GIN trigram), `nascimento`, `sexo`, `foto_path`, `estado_civil`, `data_casamento`; `cpf`, `rg`, `rg_pais`, `necessidades_especiais`, `tipo_sanguineo` (cifrados); `cpf_hash` (HMAC; único na organização, incluindo a lixeira); eclesiásticos (`aceitou_jesus`, `conversao_data`, `conversao_canal`, `batismo_data`, `batizado_por`, `igreja_origem`, `e_lider`, `e_pastor`, `novo_convertido`); adicionais (`escolaridade`, `profissao`, `nacionalidade`, `naturalidade`); vínculo atual (`igreja_id`, `vinculo`, `situacao`, `tipo_membro_id`, `matricula` — única na organização, incluindo a lixeira —, `entrada_em`, `tipo_entrada`, `como_chegou_id`); lixeira (`excluida_em`, `excluida_por_acesso_id`, `exclusao_motivo`) | P §14, FR-010, BR-004, BR-015, ASSUMPTION-008; ADR-0002 §7, §10; ADR-0004 §8; ADR-0005 §7; U-20 |
| `pessoa_contatos` | `tipo` (`celular`, `telefone`, `email`), `valor` (E.164 / minúsculo), `e_whatsapp`, `principal`, `entrega_falhou_em`, `descadastrado_em` | P §14; ADR-0008 §13, §15 |
| `pessoa_enderecos` | `cep`, `logradouro`, `numero`, `complemento`, `bairro`, `cidade`, `estado`, `pais` (digitados; U-23) | P §14 |
| `eventos_linha_do_tempo` | `pessoa_id`, `tipo` (enum: `entrada`, `vinculo_alterado`, `situacao_alterada`, `tipo_membro_alterado`, `transferencia`, `mesclagem`, `mesclagem_desfeita`, `excluida`, `recuperada`, `registro_manual`), `ocorrido_em`, `motivo_id`, `motivo_texto`, `autor_acesso_id`, `origem`, `dados` (jsonb codificado) | P FR-011; ADR-0005 §1 |
| `relacionamentos` | `pessoa_id`, `relacionada_id`, `tipo` (enum fixo com inverso), `descricao` (só `outro`); a inversa é gravada na mesma Action | P FR-012; U-24 |
| `listas_valores` | `lista` (`como_chegou`, `motivo_vinculo`, `motivo_inativacao`, `motivo_reativacao`, `tipo_membro`, `funcao`), `nome`, `ativo`, `ordem` | U-09; U-28 |
| `pessoa_funcoes` | `pessoa_id`, `funcao_id` (lista `funcao`), `desde`, `ate` | P FR-013 |
| `campos_obrigatorios` | `vinculo`, `campo` (chave do catálogo de campos da ficha) | P BR-011; U-05; U-06 |
| `pares_nao_duplicados` | `pessoa_a_id`, `pessoa_b_id` (a < b), `confirmado_por_acesso_id` | P FR-007 ("é outra pessoa") |
| `transferencias` | `pessoa_id`, `igreja_origem_id`, `igreja_destino_id`, `estado` (`solicitada`, `aprovada`, `recusada`), `solicitada_por_acesso_id`, `decidida_por_acesso_id`, `justificativa`; único parcial (org, pessoa) com `estado = solicitada` | P FR-031, §12; U-21 |
| `exportacoes` | `acesso_id`, `tipo` (`pessoas`, `aniversarios_nascimento`, `aniversarios_batismo`, `aniversarios_casamento`, `novos_convertidos`), `filtro` (jsonb), `ids_selecionados`, `formato` (`xlsx`, `csv`), `estado` (`pendente`, `processando`, `concluida`, `negada`, `falhou`), `arquivo_path`, `expira_em` (+24h), `contagem` | P FR-014, FR-033, FR-034; ADR-0004 §9; U-10; U-16; U-25 |

- **Catálogo de campos da ficha** (enum PHP, ADR-0004 §10): cada campo tem categoria (`geral`, `saude`, `documentos`, `contatos`, `fe`) e seção (`pessoais`, `documentos`, `contatos`, `endereco`, `saude`, `eclesiasticos`, `adicionais`, de P §14). Ele alimenta projeção, obrigatórios, completude e mesclagem.
- **Completude** = obrigatórios do vínculo preenchidos ÷ obrigatórios do vínculo; lista vazia = 100% (U-05).
- **Removedor de Pessoas** (contrato do ADR-0005 §8): modos `anonimizar` e `apagar`, lidos da configuração `lgpd.modo_exclusao` (sem padrão). Em `APP_ENV=production` sem modo, recusa com `lgpd.erasure.blocked_no_policy`. Grava `exclusoes_executadas` (pessoa, data, modo; tabela sob RLS desta Spec). O registro no S3 `lgpd/` fica para a Spec de infraestrutura (ADR-0011 §9; U-12).

### 3.6 Avisos (E-08)

| Tabela | Campos principais | Fonte |
|---|---|---|
| `avisos` | `tipo`, `conta_id` (nulo para pessoa sem conta), `pessoa_id`, `referencia_tipo`, `referencia_id`, `chave`, `destino`, `lido_em`; único (org, tipo, referência, destinatário, chave); retenção 12 meses | ADR-0008 §4, §7, §17 |
| `envios` | particionada por mês; `aviso_id`, `canal` (`email`, `whatsapp`), `provedor`, `destino_mascarado`, `destino_hash`, `id_externo`, `estado` (os 10 do ADR-0008 §2), horários por estado, `tentativas`, `erro_codigo`, `agendado_para`, `request_id`; único (`aviso_id`, `canal`); retenção 12 meses | ADR-0008 §2, §4, §17 |
| `preferencias_aviso` | `conta_id`, `tipo`, `canal`, `ligado` | ADR-0008 §13 |
| `whatsapp_numeros` | `waba_id`, `phone_number_id`, `nome_exibido`, `estado`, `qualidade`, `nivel_limite`, `token_cifrado`, `templates_versao` | ADR-0008 §10; ADR-0011 §8 |
| `uso_mensageria` | `mes`, `canal`, `categoria`, `quantidade`, `custo_estimado`; retenção 5 anos | ADR-0008 §16, §17 |

- **Catálogo `TipoAviso`** desta Spec (ADR-0008 §1, §12):
  - códigos: `codigo.verificacao`, `codigo.dois_fatores`, `codigo.recuperacao`;
  - convite: `convite.painel`;
  - serviço: `servico.transferencia_solicitada` (secretarias de origem), `servico.transferencia_decidida` (secretaria de destino), `servico.exportacao_pronta` (quem pediu).
  Cada tipo define categoria, obrigatoriedade, canais permitidos e ordem, se espera a janela diurna, templates e categoria de template do WhatsApp.
- **Códigos** não gravam `avisos`/`envios`. O job de identidade na fila `codigos` usa a via estreita (ADR-0008 §5) e guarda o estado do envio no próprio `codigos_verificacao`.
- **Supressão:** com `organizacoes.estado = em_migracao`, `Avisos::registrar` grava o aviso e marca `envio.suprimido` com motivo `migracao`, sem criar envio (ADR-0009 §10).

## 4. Identidade — fluxos (E-05)

- **Login** (`POST /api/v1/auth/login`): identificador (e-mail ou celular) + senha, via Fortify headless (ADR-0003 §14). A sessão dura 30 dias, renovada com o uso (U-30). Cada tentativa grava `eventos_autenticacao` (U-31). O limite por identificador e por IP, com bloqueio progressivo, usa `RateLimiter` com valores em `config/oikora.php` (U-22). A mensagem não revela se a conta existe (ADR-0003 §4).
- **Senha:** mínimo de 8 caracteres e recusa de senhas vazadas por `Password::min(8)->uncompromised()` (U-29): a consulta k-anonymity ao HIBP envia só um prefixo de hash, e o teste usa fake (ADR-0010 §2).
- **2FA:**
  - código de 6 dígitos, 10 min, uso único, por e-mail ou WhatsApp (número da plataforma), com SMS Twilio de reserva;
  - TOTP pelo Fortify;
  - 10 códigos de recuperação;
  - regra das 24h após recuperação de senha;
  - administrador obrigatório, com cadastro do 2FA antes do primeiro acesso ao painel.
  Fonte: ADR-0003 §5, §7; ADR-0008 §11, §12.
- **Elevação do painel:** `POST /api/v1/painel/elevacao` (senha + 2FA quando exigido). O middleware `ElevacaoPainel` recusa com 403 `elevacao_necessaria` depois de 2h sem uso ou 12h no total. Rotas `/api/v1/painel/*` exigem elevação e ao menos uma atribuição (ADR-0003 §9; ADR-0004 §13). O login feito pela entrada `painel` (senha + 2FA quando exigido) já abre a elevação; o diálogo só aparece quando ela expira (U-32).
- **Convite:** `Convidar` (permissão `acessos.gerir`, anti-escalada no papel e escopo) cria o convite e chama `Avisos::registrar(convite.painel)`. `AceitarConvite` (rota sem organização, token em hash) faz o login ou cria a conta com senha própria, cria o `acesso` ligado à Pessoa e a atribuição (ADR-0003 §5; P FR-002, FR-003). Expirar: comando agendado diário marca `expirado`.
- **Revogar:** `RevogarAcesso` e `RevogarConvite` valem na próxima requisição, porque o acesso é conferido a cada requisição (ADR-0003 §11).
- **Reautenticação de 15 min** para alterar senha e 2FA (ADR-0003 §11). Alterar e-mail ou celular fica na 0002.
- **Provisionamento:** `php artisan oikora:organizacao:provisionar` (conexão `pgsql_plataforma`, grava `auditoria_plataforma`) cria a organização, os papéis prontos, a primeira igreja, a Pessoa do primeiro administrador e o convite dele, com papel Administrador e escopo `organizacao`. Isso garante desde o início o invariante do ADR-0004 §3 (sempre existe um administrador).

## 5. Autorização — aplicação (E-06)

- **Action/Policy:** o atributo `#[RequerPermissao(Permissao::X)]` em toda Action. A Policy confere a igreja do recurso no alcance. O teste de arquitetura falha se uma Action não declarar permissão (ADR-0004 §5).
- **Consulta:** `PessoaEscopoAlcance` (global scope) filtra por `igreja_id` no alcance da permissão em uso. Sem alcance no contexto, gera erro `authz.context_missing` (ADR-0004 §5). As tabelas filhas são lidas só pela Pessoa.
- **Busca na denominação:** a classe única `BuscaDenominacao` sai do filtro de alcance e seleciona só `uuid`, `nome`, igreja e vínculo para quem está fora do alcance. Um teste de arquitetura garante que nenhuma outra classe chama `withoutGlobalScope(PessoaEscopoAlcance)` (ADR-0004 §6).
- **Resposta:** `PessoaResource` monta a projeção pelas categorias permitidas e devolve `meta.acoes`. A consulta não seleciona colunas de categoria não permitida (ADR-0004 §5, §7; ADR-0006 §9). Escrita em campo sem `…editar` → 422 por campo.
- **Cifra:** cast `encrypted` com o encrypter próprio `CampoSensivelEncrypter` (chave `OIKORA_CHAVE_CAMPOS`, separada da `APP_KEY`, com chaves anteriores aceitas na leitura) e o comando `oikora:chaves:recifrar`. O `cpf_hash` é HMAC-SHA256 com `OIKORA_CHAVE_HMAC_CPF` (ADR-0004 §8). Localmente, as chaves vêm do `.env`; o SSM fica na Spec de infraestrutura (U-12).

## 6. Pessoas — regras de aplicação

- **Duplicidade** (`VerificarDuplicidade`, RN-23, U-20):
  - nome normalizado + nascimento iguais;
  - ou `cpf_hash` igual;
  - ou e-mail igual em `pessoa_contatos`;
  - inclui a lixeira; ignora pares em `pares_nao_duplicados`.
  O resultado vem na projeção do alcance (fora do alcance: nome, igreja, vínculo). A lista de possíveis duplicados (FR-016) é a mesma consulta aplicada à base, sem a lixeira.
- **Transições de vínculo:** máquina de estados no Domain `Vinculo::avancar()` — só V→F→M; reativar volta ao mesmo vínculo (RN-07). Membro em modo manual exige matrícula (RN-05). O modo `automatica` lança `MatriculaAutomaticaIndisponivel` enquanto o formato estiver em aberto (U-07).
- **Mesclagem** (`MesclarPessoas`): exige `pessoas.mesclar` nas igrejas das duas pessoas.
  1. Grava o snapshot das duas fichas e a lista do que moveu: contatos, endereço, relacionamentos, funções, eventos e acesso — este só se apenas uma das duas tiver acesso; se as duas tiverem, a mesclagem é recusada (BR-002, uma Pessoa por conta).
  2. Aplica os valores escolhidos.
  3. Marca a absorvida como excluída com o motivo `mesclagem` e limpa nela `cpf`, `cpf_hash` e `matricula` (os valores ficam no snapshot), para não violar as unicidades (U-30). A purga da lixeira ignora a absorvida enquanto a operação estiver `executada`; quando a operação expira, o job apaga a absorvida junto com o snapshot (U-33).
  4. Grava eventos nas duas e a auditoria.
  `DesfazerMesclagem` aplica a regra de conflito campo a campo do ADR-0005 §6. A recusa quando as duas têm conta é a U-27.
- **Transferência:** `SolicitarTransferencia` (destino; pessoa fora do alcance, encontrada pela busca), `AprovarTransferencia` e `RecusarTransferencia` (origem). A aprovação muda `pessoas.igreja_id`, mantém vínculo e matrícula e grava o evento `transferencia`.
- **Lixeira:** `ExcluirPessoa` (confirmação no front, motivo) e `RecuperarPessoa` (≤ 30 dias). O job diário `PurgarLixeira` é distribuído por organização com contexto (ADR-0005 §7) e chama o removedor.
- **Novos convertidos:** `RegistrarConversao` liga `novo_convertido`. `RegistrarBatismo` e as mudanças de vínculo desligam (RN-18).
- **Datas comemorativas:** consultas por dia, semana e mês no fuso da organização. Batismo exige `pessoas.sensiveis.fe.ver`.
- **Exportação:** `SolicitarExportacao` grava a exportação e o job na fila `pesadas`. O job recalcula o alcance, gera o arquivo com a mesma projeção, grava no S3 privado (`exportacoes/`) e avisa `servico.exportacao_pronta`. O download é por URL assinada curta, até `expira_em`, e um job apaga o arquivo (U-25).

## 7. Contratos de API (`/api/v1`, JSON)

O OpenAPI é gerado pelo Scramble e versionado em `api/openapi.json`; tipos e cliente em `packages/api-contrato` (ADR-0006 §8). Identificadores nas rotas = `uuid`.

| Grupo | Endpoints |
|---|---|
| Sessão | `GET /sanctum/csrf-cookie` · `POST /auth/login` · `POST /auth/2fa/enviar` · `POST /auth/2fa/confirmar` · `POST /auth/logout` · `POST /auth/senha/esqueci` · `POST /auth/senha/redefinir` |
| Conta | `GET /me` (conta, organização ativa, capacidades) · `PUT /me/organizacao-ativa` · `POST /conta/confirmar-senha` · `POST /conta/verificacao/{email\|celular}` + `/confirmar` · `GET\|POST\|DELETE /conta/2fa` · `PUT /conta/senha` · `GET\|PUT /conta/preferencias-aviso` |
| Convite | `GET /convites/{token}` (dados mínimos) · `POST /convites/{token}/aceitar` |
| Painel — elevação | `POST /painel/elevacao` |
| Organização | `GET\|PUT /painel/organizacao` · `POST /painel/organizacao/logo` · `GET\|POST /painel/igrejas` · `PUT /painel/igrejas/{id}` |
| Acessos e papéis | `GET /painel/acessos` · `POST /painel/convites` · `DELETE /painel/convites/{id}` · `DELETE /painel/acessos/{id}` · `POST\|DELETE /painel/acessos/{id}/atribuicoes[/{id}]` · `GET /painel/permissoes` · `GET\|POST /painel/papeis` · `PUT /painel/papeis/{id}` |
| Auditoria | `GET /painel/auditoria` (filtros: período, evento, ator, alvo) |
| Configuração de Pessoas | `GET\|PUT /painel/configuracao/obrigatorios` · `GET\|POST /painel/listas/{lista}` · `PUT /painel/listas/{lista}/{id}` |
| Pessoas | `GET /painel/pessoas` (página 25/50/100, filtros U-17, ordenação) · `POST /painel/pessoas` · `GET /painel/pessoas/duplicidade` · `GET\|PATCH /painel/pessoas/{id}` (por seção) · `POST /painel/pessoas/{id}/vinculo` · `POST /painel/pessoas/{id}/situacao` · `GET\|POST /painel/pessoas/{id}/linha-do-tempo` · `POST\|DELETE /painel/pessoas/{id}/relacionamentos[/{id}]` · `POST\|PATCH /painel/pessoas/{id}/funcoes[/{id}]` · `POST /painel/pessoas/{id}/foto` · `POST /painel/pessoas/{id}/conversao` · `DELETE /painel/pessoas/{id}` |
| Busca | `GET /painel/busca?q=` |
| Lixeira | `GET /painel/lixeira` · `POST /painel/lixeira/{id}/recuperar` |
| Duplicados | `GET /painel/duplicados` · `POST /painel/duplicados/nao-duplicados` · `POST /painel/mesclagens` · `GET /painel/mesclagens/{id}` · `POST /painel/mesclagens/{id}/desfazer` |
| Transferências | `GET /painel/transferencias` · `POST /painel/transferencias` · `POST /painel/transferencias/{id}/aprovar` · `POST /painel/transferencias/{id}/recusar` |
| Listas especiais | `GET /painel/novos-convertidos` · `GET /painel/aniversarios?tipo=nascimento\|batismo\|casamento&periodo=dia\|semana\|mes` |
| Exportação | `POST /painel/exportacoes` · `GET /painel/exportacoes/{id}` (estado + URL assinada) |
| Avisos | `GET /avisos` · `GET /avisos/nao-lidos` · `POST /avisos/{id}/lido` · `POST /avisos/lidos` · `GET\|POST /descadastro/{token}` · `GET\|POST\|DELETE /painel/whatsapp` |
| Webhooks | `GET\|POST /webhooks/meta` · `POST /webhooks/postmark` · `POST /webhooks/twilio` |
| Saúde | `GET /saude` (ADR-0011 §12) |

- **Erros** (ADR-0006 §11):
  - 422 no formato padrão do Laravel;
  - 401, 419 (CSRF), 403 `elevacao_necessaria`, 409 `organizacao_trocada` (ADR-0006 §10);
  - `dois_fatores_necessario`, `dois_fatores_cadastro_necessario`, `convite_invalido`, `conta_bloqueada`, `muitas_tentativas`, `sem_permissao`;
  - `transicao_vinculo_invalida`, `matricula_obrigatoria`, `matricula_em_uso`, `matricula_automatica_indisponivel`, `cpf_em_uso`;
  - `transferencia_pendente`, `ultimo_administrador`, `escalada_bloqueada`, `mesclagem_contas_conflitantes`, `operacao_expirada`.
  Todo erro devolve `request_id` (ADR-0010 §9).

## 8. Frontend `web/` — entrada `painel` (ADR-0006)

- **Raiz:** `app/painel` (roteador TanStack Router, QueryClient, tema MUI Oikora com o logo da organização, Error Boundary com `trace_id`, i18next `pt-BR`).
- **`shared/http`:** cliente sobre `packages/api-contrato` com:
  - `credentials: include`, `X-XSRF-TOKEN`, `X-Organizacao-Esperada`, `X-Cliente: painel@<build>`, `traceparent`;
  - 419 → renova o CSRF e repete;
  - 401 → login com a URL de volta;
  - 403 `elevacao_necessaria` → diálogo sem perder o formulário;
  - 409 → recarrega, avisando as outras abas pelo `BroadcastChannel`.
- **`shared/forms`:** conversão do 422 em erro no campo, foco no primeiro erro e resumo (ADR-0006 §5).
- **Features:**
  - `Identidade`: login, 2FA, cadastro de 2FA, elevação, aceite de convite, recuperação;
  - `Organizacao`: configuração, igrejas;
  - `Autorizacao`: acessos, convites, papéis;
  - `Auditoria`: consulta;
  - `Pessoas`: lista com DataGrid MIT, cadastro rápido, ficha por seções, linha do tempo, relacionamentos, funções, duplicados e mesclagem, transferências, lixeira, novos convertidos, datas, exportações, configuração de obrigatórios e listas;
  - `Avisos`: caixa com contador a cada ~60 s com a aba visível, preferências, WhatsApp.
- **Regras:** filtros, página e ordenação na URL (ADR-0006 §3). Telas seguem os padrões da MUI, sem designer (ADR-0006, lacunas). Exclusão só no menu de ações, com confirmação (ADR-0006 §6).

## 9. Bibliotecas e frameworks selecionados

| Escolha | Motivo | ADR relacionado |
|---|---|---|
| PHP ≥ 8.4, Laravel ≥ 12 (última estável no bootstrap), PHP-FPM + Caddy | Stack e servidor | ADR-0001 §1; ADR-0011 §2; U-13; U-15 |
| PostgreSQL 17.x com `pg_trgm`, `unaccent` | Banco, busca e duplicidade | ADR-0002 §1, §10; ADR-0011 §3 |
| Laravel Sanctum (SPA) + Fortify headless | Sessão, senha, TOTP | ADR-0003 §9, §14 |
| Pest (+ Architecture), Larastan nível 8, Pint, Deptrac | Testes e análise | ADR-0010 §1, §7; U-14 |
| Scramble + `oasdiff`; `openapi-typescript` + `openapi-fetch` | Contrato tipado | ADR-0006 §8; ADR-0010 §4 |
| React ≥ 19 + TS strict, Vite, TanStack Router/Query, RHF + Zod, MUI 7 + DataGrid MIT, i18next, `libphonenumber-js` | Front do painel | ADR-0006 §1–§6, §13 |
| Vitest + RTL + MSW; Playwright + `@axe-core/playwright`; `size-limit`; ESLint (typescript-eslint, jsx-a11y) + Prettier | Testes e qualidade do front | ADR-0010 §5 |
| OpenTelemetry PHP SDK; logs JSON no stdout com redação | Instrumentação | ADR-0010 §9; U-12 |
| Mailer `postmark` do Laravel | E-mail | ADR-0008 §1, §9 |
| Adapters Meta (Graph API), Twilio e Postmark (webhooks) pelo cliente HTTP do Laravel, sem SDK de terceiros (U-29) | Uma dependência a menos; `Http::fake` e `preventStrayRequests` nos testes | ADR-0008 §1; ADR-0010 §2 — decisão local |
| `openspout/openspout` para XLSX e CSV em streaming (U-29) | Exportação sem carregar tudo na memória da VM de 2 GB | ADR-0011 §2 — decisão local |
| `giggsey/libphonenumber-for-php` para E.164 no backend (U-29) | Mesma regra de telefone do front | ADR-0003 §1; ADR-0006 §13 — decisão local |
| MinIO no Compose (dev/CI) | S3 local com URL assinada | U-19; ADR-0011 §7 |
| Fila, sessão e cache `database` (PG) | Sem Redis | ADR-0008 §3; ADR-0011 §3 |

## 10. Estratégia de migração

Não há dado existente: o repositório só tem documentação. Migrations criam o schema do zero, em ordem de dependência (Organizacao → Identidade → Autorizacao → Pessoas → Auditoria → Avisos, com as FKs cruzadas adicionadas depois das tabelas). Seeders criam dados **fictícios** para desenvolvimento e Playwright: organização "Demonstração", 3 igrejas, administrador, secretarias e pessoas (ADR-0011 §4, §13). A carga do inChurch fica na 0004. O padrão expandir → contrair passa a valer a partir do primeiro deploy (ADR-0011 §11; Spec de infraestrutura). Rollback local = `migrate:fresh` no banco descartável do Compose.

## 11. Avaliação nos 5 pilares do projeto

| Pilar | Como este plano atende |
|---|---|
| Desempenho | Busca e duplicidade por `nome_busca` com trigram e `cpf_hash` indexados (ADR-0002 §10). Projeção que nem seleciona colunas não permitidas. Exportação em streaming na fila `pesadas`. Lista paginada no servidor. `size-limit` no painel. |
| Simplicidade de manutenção | Seis módulos com camadas só quando necessárias (MOD-004). Uma Action por caso de uso para todos os canais. Catálogos (permissões, eventos, campos, avisos) como enums versionados. Uma tabela genérica de listas de valores. |
| Boas práticas | RLS forçada + filtro de alcance + Policy (defesa em camadas). Auditoria na transação. Caixa de saída transacional. TDD com PostgreSQL real. Contrato OpenAPI verificado no CI. |
| Escalabilidade | Banco compartilhado por `organizacao_id` e jobs distribuídos por organização (ADR-0002, ADR-0008). Contexto por transação, compatível com pooler futuro (ADR-0011 §3). Redis só por gatilho. |
| Observabilidade / logs | `request_id` + `trace_id` em todo log, job e envio. Logs JSON com redação e testes de dados-sentinela. Eventos de auditoria e de segurança catalogados. O destino da telemetria fica na Spec de infraestrutura (U-12). |

## 12. Restrições de ADRs aplicadas

- **OIK-ADR-0001:** toda tela consome a `/api/v1`; Action única; nenhuma tela renderizada no servidor.
- **OIK-ADR-0002:** §2 (helper de migration, lista fechada), §3 (contexto e fail-closed), §5 (três papéis), §6 (FK composta), §7 (unicidades por organização), §9 (UUID), §12 (teste de catálogo).
- **OIK-ADR-0003:** §1–§5, §7–§9, §11, §12, §14 nesta Spec; §6 (auto-cadastro), §10 (app), §11 (sessões/dispositivos e troca de identificador) e §13 (suporte) ficam para depois (U-01, U-11).
- **OIK-ADR-0004:** §1–§9, §13; §10–§11 (campos da área do membro, responsável) na 0002; §12 (suporte) depois.
- **OIK-ADR-0005:** §1–§3, §5–§8 (removedor sem o registro no S3), §10; §9 (consentimento) na 0002 (U-08); §4 só pelo provisionamento.
- **OIK-ADR-0006:** entrada `painel`; a entrada `membro`, a casca `og:` e o tema por organização na área do membro ficam na 0002.
- **OIK-ADR-0008:** tudo menos push (0003) e automáticas (U-03).
- **OIK-ADR-0009:** só §5 (estado) e §10 (supressão).
- **OIK-ADR-0010:** §1–§5, §7, §8, §9 (no código); o Maestro e o app ficam na 0003; painéis, alertas e retenção da telemetria na Spec de infraestrutura.
- **OIK-ADR-0011:** §3 (PG, contexto, drivers `database`), §4 (dev local), §7 (convenções de arquivo), §12 (`/saude`); o resto na Spec de infraestrutura (U-12).

## 13. Riscos e pontos em aberto

- ❓ **Embedded Signup da Meta × CSP `script-src 'self'`** (ADR-0008 §10 × ADR-0011 §6): a 0001 implementa o "ligar número" pelo fluxo OAuth por redirecionamento, sem o SDK JavaScript da Meta. Se a Meta exigir o SDK, a exceção de CSP vira ADR antes de ir a produção. A validação real depende do cadastro como *Tech Provider* (ação do usuário).
- ❓ **Fidelidade do Scramble às projeções condicionais** (ADR-0006, lacunas): validar na T-05; se falhar, anotar os Resources.
- ❓ Herdados da spec: OPEN-003, formato da matrícula automática (OPEN-016), limite de WhatsApp, valores iniciais das listas, modo de exclusão (OPEN-013), valores dos limites de login.
- **Risco — tamanho:** são 6 módulos e 46 tasks. A ordem das tasks (`tasks.md`) entrega primeiro o isolamento e a identidade, porque todo o resto depende deles.
