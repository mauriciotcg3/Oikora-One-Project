# ADR-0010: Qualidade, CI e observabilidade — testes com PostgreSQL real, GitHub Actions e Grafana Cloud + Sentry

**Status:** Aceito — após confirmação do usuário (2026-10-21)
**Data:** 2026-10-21
**Contexto do projeto:** Oikora One — plataforma da denominação (3 igrejas, ~520 membros) que substitui o inChurch módulo a módulo, com plano de atender outras igrejas.
**Identificação:** Produto `OIK-ADR-0010` · Governança `ADR-0032`
**Fonte da verdade:** `oikora-one:docs/adr/0010-qualidade-ci-e-observabilidade.md`

## Contexto

Os ADRs OIK-0001 a 0009 deixaram para este ADR como o Oikora prova que está certo antes de entrar em produção (testes, CI, Pre-Review Gate, metas de desempenho) e como avisa quando algo dá errado depois (logs, métricas, erros, painéis, alertas e limiares). Cada um listou eventos, métricas e alertas na sua seção de Observabilidade e terminou com "Ferramentas e limiares no OIK-ADR-0010".

Restrições já aceitas:

- **Governança:** Pest, Pint, PHPStan/Larastan, Pest Architecture e Deptrac (Architecture Standard §24–§26, `TEST-001/002`, `QUALITY-001`); logs estruturados + OpenTelemetry com correlação (`OBS-001/002`, ADR-0003 global); no front, `FE-OBS-001..003` e `FE-ARCH-003` (runner de teste, linter e error tracking exigem ADR); Pre-Review Gate e política de bloqueio do CI (`enforcement.md` §16 e §19).
- **OIK-ADR-0001:** só API `/api/v1`, Action única por caso de uso; log de toda requisição com `request_id`, sem dado pessoal; alerta de 5xx por rota, erros de front pós-deploy, versão de API obsoleta.
- **OIK-ADR-0002:** RLS forçada por `organizacao_id`, contexto ausente = erro, papel da aplicação sem `BYPASSRLS`, `application_name` por processo; `tenant.rls_violation` e `tenant.context.missing` devem ser zero.
- **OIK-ADR-0003/0004/0005:** eventos de autenticação, autorização, decifra e auditoria; log técnico sem dado pessoal e com retenção curta (auditoria e eventos de autenticação ficam no PostgreSQL, não no log).
- **OIK-ADR-0006:** pnpm, `web/` Vite com entradas `painel` e `membro`, `packages/api-contrato` gerado do OpenAPI (Scramble), OpenTelemetry no navegador, `X-Cliente`, Error Boundary com `trace_id`; ficaram aqui runner de teste, lint/format, enforcement de boundaries, orçamento de bundle, ferramenta de acessibilidade, metas de LCP e coletor.
- **OIK-ADR-0007:** app Expo em `app/`, OTA com envio gradual, 426 por versão mínima; ficaram aqui testes do app, ferramenta de travamentos, metas de abertura e tamanho, esteira do EAS e conferência de que o contrato não quebra a versão mínima.
- **OIK-ADR-0008 (item 18):** Adapters falsos, contrato dos webhooks, painel de filas próprio (sem Horizon), limiares das filas e envios.
- **OIK-ADR-0009 (item 14):** métricas e alertas da migração, fixtures anonimizadas das duas fontes (API e XLSX), testes de idempotência e de reaplicação de decisões; prazo de cada fase só conhecido no 1º ensaio.

Contexto operacional informado pelo usuário (2026-10-19 e 2026-10-21): equipe de 2–4 devs + IA; **sem plantão 24h** (horário comercial + cultos); alertas para os devs por **e-mail e chat**; fora do horário, alerta crítico **notifica sem obrigação** de resposta; orçamento de ferramentas de observabilidade **quase zero**; repositório **privado no GitHub, plano Free** (2.000 min/mês de Actions); merge por **PR + CI verde + 1 aprovação humana**; **trunk-based** (`main` + branches curtas; `Fase-1` aposentado); cobertura mínima **só nas linhas alteradas**. Sobre telemetria fora do Brasil, o usuário delegou a decisão.

## Opções consideradas

Todas usam a mesma instrumentação (OpenTelemetry + logs JSON); muda onde os dados ficam e quem os opera.

- **A — Grafana Cloud (plano gratuito, região Brasil) + Sentry (plano gratuito)** — Grafana Alloy coleta logs, métricas e traces para o Grafana Cloud; Sentry recebe erros de web, app e API; verificação externa de disponibilidade pelo Synthetic Monitoring.
- **B — Auto-hospedado** — Grafana, Loki, Tempo, Prometheus e GlitchTip numa VM da hospedagem (OIK-ADR-0011).
- **C — Mínimo caseiro** — logs JSON no stdout do provedor, painéis e alertas próprios por jobs que leem o PostgreSQL, Sentry gratuito e um serviço de uptime gratuito.

## Comparação

| Pilar | A — Grafana Cloud + Sentry | B — Auto-hospedado | C — Caseiro |
|---|---|---|---|
| Desempenho | Bom: o Alloy agrupa e amostra (100% dos erros, ~10% dos traces de sucesso); nenhum custo de armazenamento na aplicação. | Aceitável: Loki, Prometheus e Tempo disputam CPU e disco com a aplicação se dividirem a VM. | Aceitável: jobs de alerta consultam o mesmo PostgreSQL da aplicação a cada minuto. |
| Simplicidade de manutenção | Ótimo: nada para atualizar, dimensionar ou fazer backup; painéis e alertas como código no repositório. | Ruim: cinco componentes para manter e vigiar com 2–4 devs, além do próprio monitor. | Ruim: a equipe passa a construir e manter um produto de observabilidade. |
| Boas práticas | Ótimo: OpenTelemetry neutro (trocar de fornecedor não exige reinstrumentar); cumpre `OBS-001/002`, `FE-OBS-001..003` e ADR-0003. | Bom: mesma instrumentação; dados no Brasil. | Aceitável: sem traces, não cumpre a correlação ponta a ponta do ADR-0003. |
| Escalabilidade | Bom: cabe nas cotas gratuitas no início (10 mil séries, 50 GB de logs e traces, 5 mil erros/mês); gatilhos de plano pago claros e baratos. | Aceitável: cresce com disco e VM maiores e com mais operação. | Ruim: limiares e painéis manuais não acompanham centenas de organizações. |
| Observabilidade / logs | Ótimo: log ↔ trace ↔ erro pelo `trace_id`/`request_id`; o monitor fica fora da infraestrutura que vigia. | Bom: completo, mas cai junto com a VM/provedor que deveria vigiar. | Ruim: se o sistema cair, o job que alertaria cai junto (sobra o uptime). |

## Decisão

Adotar **A — Grafana Cloud (região Brasil) + Sentry, nos planos gratuitos**, com a estratégia de testes, o CI e as regras abaixo.

1. **Testes do backend (Pest, sempre com PostgreSQL real — nunca SQLite, porque RLS, `pg_trgm` e cifra dependem do PostgreSQL).**
   - **Unidade:** regras de Domain sem banco, HTTP ou integração (`TEST-001`).
   - **Feature:** cada Action pela rota `/api/v1` (status, corpo, `meta.acoes`/`meta.campos`, erros por campo), com o contexto de organização montado como em produção.
   - **RLS:** suíte própria que conecta com o **papel da aplicação** (sem `BYPASSRLS` e sem ser dono das tabelas). Um teste de catálogo percorre o `information_schema`: toda tabela com `organizacao_id` precisa ter RLS habilitada **e forçada** e política de leitura e escrita — tabela nova sem isso quebra o CI. Para cada tabela, dados de duas organizações: leitura, escrita, atualização e exclusão cruzadas falham; consulta sem contexto dá erro (`tenant.context.missing`).
   - **Autorização:** matriz permissão × alcance × categoria sensível gerada a partir do catálogo de permissões (OIK-ADR-0004), incluindo último administrador e suporte proibido.
   - **Arquitetura:** Pest Architecture e Deptrac para `MOD-002`, `DEP-*`, `DB-001`, `COMM-*`; teste de que toda Action de escrita grava auditoria na transação (OIK-ADR-0005).
   - **Log seguro:** testes com dados-sentinela (CPF, telefone, e-mail, token, código) que falham se algum aparecer no log ou no evento enviado ao Sentry (`OBS-002`).
2. **Integrações externas.** Toda integração tem Adapter falso usado em todos os testes (Meta WhatsApp, Postmark, Twilio, Expo push, API do inChurch); o CI não faz chamada de rede (`Http::preventStrayRequests`). **Contrato dos webhooks:** payloads reais anonimizados de cada provedor versionados como fixtures, com testes de assinatura válida, assinatura inválida (`webhook.assinatura_invalida`), envio desconhecido e eventos fora de ordem. Teste manual contra o sandbox do provedor só ao trocar a versão da API do provedor.
3. **Migração (OIK-ADR-0009).** Fixtures **anonimizadas** das duas fontes (JSON da API e XLSX), geradas por um script de anonimização a partir da sondagem real, sem nenhum dado real no repositório. Testes de mapeamento por entidade e valor; **idempotência** (carregar duas vezes = mesmo resultado: contagens e impressão digital por entidade iguais); **reaplicação de decisões** (decisão reaplicada no ensaio seguinte; grupo alterado → decisão invalidada e conflito novo); supressão de avisos (nenhum `envio` para organização `em_migracao`).
4. **Contrato da API.** O `openapi.json` gerado pelo Scramble precisa ser igual ao versionado; `oasdiff` bloqueia mudança incompatível em `/api/v1` em relação à `main` **e** em relação ao contrato da tag da versão mínima do app em uso (OIK-ADR-0007). Quebra intencional exige nova versão de rota ou subir a versão mínima, registrado no PR.
5. **Web (`web/`, `packages/`).** Vitest + React Testing Library + MSW (respostas tipadas pelo `packages/api-contrato`); ESLint (typescript-eslint, `eslint-plugin-jsx-a11y`, regras de import para Public API e boundaries de feature — `FE-QUAL-002`) + Prettier; `tsc --noEmit` strict. **Playwright** nas jornadas críticas — login com 2FA, troca de organização, ficha com categoria sensível, elevação no painel, formulário público — contra a API real com PostgreSQL, com **axe** (`@axe-core/playwright`): violação séria ou crítica de acessibilidade falha (meta WCAG 2.2 AA). **Orçamento de bundle** com `size-limit`: JS inicial da entrada `membro` ≤ 200 KB gzip e do `painel` ≤ 350 KB gzip (bloqueia no CI). **Meta de campo:** LCP p75 ≤ 2,5 s, INP p75 ≤ 200 ms e CLS p75 ≤ 0,1 na entrada `membro` em celular.
6. **App (`app/`).** Jest (`jest-expo`) + React Native Testing Library; ESLint e `tsc` com as mesmas regras compartilhadas. **Maestro** em emulador Android com as jornadas de login, carteirinha/ficha, push aberto e link universal, rodado antes de toda publicação para produção (build de loja ou OTA), não em todo PR. **Metas:** abertura a frio p75 ≤ 3 s num Android de 2–3 GB de RAM; download Android ≤ 40 MB. Esteira: PR não gera build; merge na `main` publica OTA no canal de homologação; tag de release dispara EAS Build/Submit ou OTA de produção com envio gradual (canais e segredos no OIK-ADR-0011).
7. **CI no GitHub Actions (repositório privado, plano Free).**
   - **No PR (bloqueante, meta ≤ 10 min):** jobs só das partes alteradas (filtro por caminho), cache de dependências e cancelamento de execuções antigas do mesmo PR.
     - API: Pint `--test` → PHPStan/Larastan (nível na Spec; baseline vazio no início) → Pest em paralelo com serviço PostgreSQL (unidade, feature, RLS, autorização, arquitetura, log seguro) → Deptrac → contrato (gerado = versionado + `oasdiff`).
     - Web: `tsc` → ESLint → Vitest → build com `size-limit`.
     - App: `tsc` → ESLint → Jest.
     - Todos: **cobertura ≥ 80% nas linhas alteradas** (relatórios Clover/LCOV; total só informativo); gitleaks; auditoria de dependências (`composer audit`, `pnpm audit`) — vulnerabilidade crítica bloqueia, alta vira aviso; Dependabot semanal.
   - **Noturno em dia útil, na `main`:** Playwright completo + axe, suíte de migração completa e verificação de dependências; falha abre aviso no chat da equipe.
   - **Antes de publicar o app em produção:** Maestro (item 6).
   - **Branch protection na `main`:** trunk-based, branches curtas por task; merge só por PR com checks obrigatórios verdes e **1 aprovação humana de quem não escreveu o código** (o Reviewer IA é adicional e não substitui); squash; sem push direto nem force push; release por tag `vX.Y.Z`. O branch `Fase-1` é aposentado.
   - **Custo:** consumo acima de 1.800 min/mês → plano Pro ou minutos pagos, decidido na hora; até lá o CI pesado fica no noturno.
8. **Pre-Review Gate.** Os mesmos passos do CI rodam localmente por scripts únicos — `composer gate` (Pint → PHPStan/Larastan → Pest → Deptrac → contrato) e `pnpm gate` (tsc → ESLint → testes → build/size-limit) — com variante restrita ao módulo alterado para o executor antes do `REVIEW REQUEST` (`enforcement.md` §16). O CI é a autoridade; gate local verde não dispensa o CI. Comandos e durações reais entram no `project.md` da governança quando rodarem pela primeira vez (`CTX-011`).
9. **Instrumentação e correlação.**
   - A API aceita `X-Request-Id` da borda ou gera um (UUID), devolve no cabeçalho e no corpo de todo erro, e grava em todo log; o navegador e o app enviam `traceparent` W3C (OIK-ADR-0006/0007) e `X-Cliente`; `request_id` e `trace_id` aparecem juntos em todo log.
   - Jobs, envios e execuções de migração herdam o `request_id` de origem como correlation id (`COMM-005`); rotinas agendadas geram o seu.
   - Logs Laravel em JSON no stdout com os campos do Architecture Standard §21, um processador de redação que remove campos proibidos e o rótulo `ambiente`; SDK OpenTelemetry PHP para traces (requisição, Action, consulta SQL, job, chamada externa).
   - **Grafana Alloy** (distribuição do OpenTelemetry Collector) recebe OTLP da API, dos workers e do navegador (endpoint público com limite de taxa), aplica redação e amostragem por cauda (100% dos traces com erro ou lentos, ~10% dos demais) e envia ao Grafana Cloud.
   - **Sentry** recebe erros do navegador (`painel`, `membro`), do app (`@sentry/react-native`, travamentos nativos, source maps por OTA e por build) e exceções não tratadas da API, sempre com `trace_id`, `release` = versão do `X-Cliente` e id da OTA. Configuração obrigatória: `sendDefaultPii=false`, IP descartado, sem corpo de requisição, sem Session Replay, `beforeSend` com a mesma redação.
   - Batimento (heartbeat) do agendador e de cada worker a cada minuto, como métrica.
10. **Métricas e cardinalidade.** Métricas técnicas (RED por rota, latência p95, filas, conexões, Web Vitals, travamentos) vão ao Grafana com rótulos de baixa cardinalidade (rota, módulo, fila, canal, provedor, versão do cliente, ambiente). **O id da organização não é rótulo de métrica** — fica nos logs e traces —, para não estourar o limite de séries com centenas de organizações. Métricas de negócio (envios por estado, conflitos, lixeira, pedidos LGPD) são calculadas por uma rotina agendada a cada minuto a partir do PostgreSQL e expostas como agregados.
11. **Painéis.**
    - **Grafana (versionados no repositório, ambiente selecionável):** (1) API — taxa, erros e duração por rota, 5xx, versões de cliente e de API obsoleta; (2) Banco e isolamento — conexões vs limite, p95 de consultas, `rls_violation`/`context_missing` (esperado zero); (3) Filas e avisos — idade e tamanho por fila (`codigos` em destaque), envios por canal e estado, falhas por provedor e código, webhooks, varredor, agendador; (4) Identidade e segurança — logins e falhas, bloqueios, entrega de 2FA, negações, decifra, suporte; (5) Clientes — erros e travamentos por versão e OTA, 401/409/419/422/426, Web Vitals, chunk load; (6) Migração — por execução e fase.
    - **Painel de operação dentro da plataforma** (área de plataforma do painel, permissão própria do catálogo), lendo o PostgreSQL: filas e envios **por organização**, números de WhatsApp e qualidade, uso vs limite, execuções e relatórios de migração, lixeira e pedidos LGPD pendentes. É o painel de filas próprio do OIK-ADR-0008 e permite ao suporte agir sem acessar o Grafana.
12. **Alertas (Grafana Alerting; Sentry só para erro novo ou regressão).** Destino: canal de chat da equipe (ferramenta escolhida no bootstrap) + e-mail dos devs. **Todo alerta tem runbook** em `docs/operacao/runbooks/` (o que significa, como confirmar, o que fazer, quando escalar); alerta sem runbook não entra.
    - **P1 — notifica a qualquer hora, sem obrigação de resposta fora das janelas:**
      - API, `painel` ou `membro` fora do ar (verificação externa falhando em 2 locais por 2 ciclos);
      - 5xx > 5% das requisições por 5 min (mínimo 50 requisições);
      - job na fila `codigos` há mais de 1 min;
      - qualquer `tenant.rls_violation`, `tenant.context.missing`, `authz.context_missing`, `crypto.decrypt_failed` ou `audit.write_failed`;
      - agendador ou worker sem batimento por 5 min;
      - qualquer envio criado para organização `em_migracao`;
      - `auth.support_access.used` sem autorização vigente ou uso do papel de plataforma sem linha em `auditoria_plataforma`;
      - nenhuma telemetria recebida do ambiente de produção por 10 min.
    - **P2 — notifica só dentro das janelas; fora delas fica silenciado até a próxima:**
      - qualquer fila (exceto `codigos`) parada há mais de 15 min; varredor reenfileirando por mais de 30 min;
      - falha definitiva > 10% por canal ou provedor em 15 min (mínimo 20 envios); `auth.2fa.delivery_failed` > 20% por canal em 15 min (mínimo 10);
      - mais de 20 `webhook.assinatura_invalida` em 5 min; número de WhatsApp desconectado ou com qualidade baixa; template rejeitado; automáticas do dia não geradas até 10h no fuso da organização;
      - `auth.login.failed`/`auth.lockout` > 3× a média da mesma hora nos últimos 7 dias e > 50 em 15 min; mais de 30 `authz.denied` de um mesmo acesso em 10 min; `auth.context.denied` > 3× a média; exportação com categoria sensível fora das janelas; `authz.last_admin_blocked`;
      - em até 2 h após um deploy ou OTA: 401, 419, 426, `api.org_mismatch` ou `ui.chunk_load_failed` > 3× a média de 7 dias; travamento de uma OTA > 2% das sessões em 1 h (interromper o envio gradual); queda > 50% nos registros de push por plataforma em 24 h; `app.deeplink.unmatched` > 3× a média;
      - conexões > 80% do limite por 10 min; p95 da API > 1,5 s por 15 min;
      - LCP p75 da entrada `membro` > 2,5 s por 3 dias seguidos;
      - `trash.purge.failed` ou pessoa na lixeira há mais de 31 dias; `lgpd.erasure.failed`, `lgpd.erasure.blocked_no_policy` ou pedido LGPD aprovado e não concluído em 24 h; partição de retenção vencida e não removida;
      - migração: execução falhou; fase parada além de 2× a duração da mesma fase no ensaio anterior (2 h até o 1º ensaio); `migracao.contagem.divergente` com diferença não explicada; rejeições > 2% por entidade; qualquer `migracao.alteracao_pos_congelamento`; bruto além de 90 dias após a virada; erro de credencial da fonte.
    - **P3 — sem notificação (painel + resumo semanal no chat):** uso de versão de API obsoleta, `uso.limite_80`, tendências de latência e de volume, aparelhos abaixo da versão recomendada.
    - **Janelas de atendimento** (configuráveis, horário de Brasília): segunda a sexta 8h–18h; quarta 19h–22h; domingo 7h–13h e 17h–21h. Execuções de migração e virada rodam dentro de janela, com o operador acompanhando (OIK-ADR-0009).
    - Limiares são iniciais; revisados depois de 30 dias de produção e do 1º ensaio de migração, com a mudança registrada no runbook.
13. **Retenção e dados fora do Brasil.** Log técnico, métricas e traces: 14 dias (plano gratuito do Grafana) — atende o "log técnico de retenção curta" do OIK-ADR-0005; erros no Sentry: 30 dias. Auditoria, eventos de autenticação e consentimentos continuam no PostgreSQL com a retenção do OIK-ADR-0005. O stack do Grafana Cloud fica na **região Brasil** (AWS São Paulo). O Sentry (EUA/UE) recebe só erros **sem dado pessoal**: identificadores internos pseudônimos (`conta_id`, `acesso_id`, id da preparação), nunca nome, CPF, contato, IP, conteúdo ou token. Os dois fornecedores com acordo de tratamento de dados aceito; o jurídico confere a transferência internacional no `OPEN-013`.
14. **Gatilhos de custo e saída.** Sentry Team (~US$ 26/mês) quando um segundo dev precisar acessar os erros ou o volume passar de 5 mil erros/mês; Grafana Pro (~US$ 19/mês + uso) quando passar de 10 mil séries, 50 GB/mês ou precisar de mais de 14 dias; minutos do GitHub conforme item 7. Como a instrumentação é OpenTelemetry e os SDKs do Sentry aceitam outro destino compatível, trocar para auto-hospedado (opção B) não exige reinstrumentar o código. Preços e limites são de 2026-10, confirmados na contratação.
15. **Fora deste ADR — o que outros ADRs precisam deste.**
    - **OIK-ADR-0011:** onde roda o Alloy (processo junto da API e dos workers) e saída de rede para Grafana Cloud e Sentry; endpoints de saúde públicos para a verificação externa (API, `painel`, `membro`, arquivos de associação do app); segredos por ambiente (token do Grafana Cloud, DSN e token de source maps do Sentry, token do EAS, segredos do CI); ambientes homologação e produção no mesmo stack, separados pelo rótulo `ambiente`; imagem e versão do PostgreSQL do CI iguais às de produção; deploy só depois do CI verde na `main`, com marcação de deploy enviada ao Grafana e ao Sentry; canais de OTA de homologação e produção.
    - **Spec:** nível do PHPStan, versões exatas das ferramentas, nomes dos scripts, lista final das jornadas do Playwright e do Maestro, ferramenta de chat da equipe, textos dos runbooks, enum final de eventos e métricas.

## Justificativa

A opção A vence porque o recurso mais escasso do projeto é tempo de operação de uma equipe de 2–4 devs sem plantão, e não dinheiro de infraestrutura. Os planos gratuitos do Grafana Cloud e do Sentry cobrem a escala inicial (uma denominação, ~520 membros) a custo zero (pedido do usuário) sem nenhum componente a manter (**simplicidade**). O monitor fica fora da infraestrutura que vigia: uma queda do provedor do OIK-ADR-0011 é vista de fora (**observabilidade**), o que as opções B e C não garantem. Como tudo passa por OpenTelemetry, a escolha não prende o Oikora ao fornecedor (**boas práticas**, ADR-0003 global). O limite de séries é tratado na origem: a organização vai para logs e para o painel de operação, não para rótulos de métrica (**escalabilidade**). A amostragem e o envio assíncrono pelo Alloy não pesam na requisição (**desempenho**).

A estratégia de testes segue os riscos que os ADRs anteriores criaram: o maior risco do produto é vazar dado entre organizações (OIK-ADR-0002), por isso a suíte de RLS roda com o papel real da aplicação e um teste de catálogo pega a tabela esquecida; o segundo é quebrar o app antigo com mudança de API (OIK-ADR-0007), por isso o `oasdiff` compara também com a versão mínima do app. As integrações externas e a migração ficam testáveis sem rede nem dado real. O CI cabe nos 2.000 minutos do plano Free porque o PR roda só o que mudou e o pesado vai para o noturno e para antes da publicação do app. As janelas P1/P2 respeitam "sem plantão": o que só um dev resolve e é perigoso (vazamento, cifra, auditoria, sistema fora) avisa a qualquer hora, sem obrigar ninguém; o resto espera a próxima janela.

## Consequências

**Positivas:**
- Custo inicial zero de ferramentas e nenhum servidor de observabilidade para manter.
- Vazamento entre organizações, tabela sem RLS, quebra de contrato com o app, bundle grande e dado pessoal em log são barrados no CI, não descobertos em produção.
- Um `request_id` liga a reclamação da secretaria ao log, ao trace, ao erro do Sentry e ao job que falhou.
- O suporte diagnostica filas, avisos e migração por organização no painel de operação, sem acesso ao Grafana nem ao código.
- Alertas com runbook e janelas evitam ruído fora do horário e definem quem faz o quê.

**Negativas / trade-offs aceitos:**
- Retenção de 14 dias para log técnico e traces: incidente notado depois disso só se investiga pela auditoria e pelos eventos no PostgreSQL.
- Sentry gratuito tem 1 usuário: até o upgrade, só uma pessoa abre a tela de erros (os alertas chegam a todos no chat).
- Erros vão para fora do Brasil (Sentry), aceito porque não levam dado pessoal; depende da conferência jurídica do `OPEN-013`.
- Dois fornecedores externos e duas telas (Grafana e Sentry), ligados pelo `trace_id`.
- Testes com PostgreSQL real e Playwright deixam o CI mais lento que testes em memória; o limite de 2.000 min/mês obriga a manter o PR enxuto.
- 1 aprovação humana obrigatória pode segurar PRs quando só um dev estiver disponível.
- Jornadas do app em aparelho (Maestro) rodam só antes da publicação, não em todo PR.

## Observabilidade — o que precisa ser instrumentado
- **Eventos a logar:** os eventos listados nas seções de Observabilidade dos OIK-ADR-0001 a 0009, no formato do item 9; além deles, `ci.gate.failed` (job, passo, PR) e `deploy.started`/`deploy.succeeded`/`deploy.failed` (versão, commit, ambiente), `alerta.disparado`/`alerta.resolvido` (regra, severidade), `telemetria.redacao_aplicada` (campo removido, origem — sem o valor) e o batimento de agendador e workers.
- **Métricas a expor:** as métricas dos OIK-ADR-0001 a 0009 com a regra de cardinalidade do item 10; duração e taxa de falha do CI por job; minutos do GitHub Actions usados no mês; séries ativas, GB ingeridos e erros/mês contra as cotas gratuitas; tempo entre o alerta e a resolução; Web Vitals e tempo de abertura do app contra as metas.
- **Alertas a configurar:** os do item 12; além deles, cota gratuita acima de 80% (séries, GB, erros ou minutos de CI) e alerta disparado sem runbook (P3).
- **Cenário de incidente validado:** num domingo às 7h40, a secretaria da Igreja A diz que a lista de pessoas não carrega e mostra "erro, código 7f3c…" (o `request_id`). O alerta P1 de 5xx já tinha chegado ao chat às 7h32, dentro da janela de domingo. No Grafana, o dev que acompanha a janela filtra os logs por esse `request_id`: `GET /api/v1/pessoas`, módulo Pessoas, organização A, e o trace mostra uma consulta de busca levando 30 s até o timeout, iniciada logo depois de `deploy.succeeded` da v1.8.0 no sábado à noite. No Sentry, o mesmo `trace_id` aparece no erro do `painel@1.8.0`. O painel Banco mostra o p95 de consultas subindo desde o deploy e nenhuma `rls_violation`, então não há vazamento, só lentidão. Ele reverte para a v1.7.2 pelo runbook de deploy; às 7h55 o 5xx zera e o alerta se resolve. Na segunda, o índice que a migração de banco da v1.8.0 removeu é recriado com teste novo. Ninguém leu código durante o incidente; se fosse sábado à 1h, o mesmo P1 teria chegado ao celular sem obrigar resposta, e o P2 de p95 só teria tocado no domingo às 7h.

## Lacunas conhecidas
- Limites e preços dos planos gratuitos (Grafana Cloud: 10 mil séries, 50 GB de logs e traces, 14 dias, 3 usuários; Sentry Developer: 1 usuário, 5 mil erros/mês; GitHub Free: 2.000 min/mês) vêm de fontes de 2026 e precisam ser confirmados na contratação, assim como a disponibilidade da região Brasil no plano gratuito do Grafana Cloud. Se a região não estiver disponível no plano gratuito, a telemetria vai para a região mais próxima, com a mesma regra de "sem dado pessoal".
- Transferência internacional dos erros (Sentry) depende da conferência jurídica do `OPEN-013`; se for vetada, troca-se o Sentry por GlitchTip auto-hospedado no Brasil (mesmo SDK).
- Metas de abertura e tamanho do app foram definidas sem levantamento dos aparelhos reais dos membros (informado só "muitos Android simples"); revisar com os dados de produção.
- Prazo de cada fase da migração só será conhecido no 1º ensaio; até lá vale o limite de 2 h.
- Consumo real de minutos de CI com 2–4 devs não foi medido; estimado perto do limite do plano Free.
- Ferramenta de chat da equipe para os alertas não foi escolhida (Spec/bootstrap).
- Janelas de culto informadas de forma geral ("horário comercial + cultos"); os horários do item 12 são iniciais e configuráveis.
