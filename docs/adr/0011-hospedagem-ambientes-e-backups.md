# ADR-0011: Hospedagem, ambientes e backups — AWS São Paulo com VM Lightsail, RDS PostgreSQL 17 e Cloudflare

**Status:** Aceito — após confirmação do usuário (2026-10-21)
**Data:** 2026-10-21
**Contexto do projeto:** Oikora One — plataforma da denominação (3 igrejas, ~520 membros) que substitui o inChurch módulo a módulo, com plano de atender outras igrejas.
**Identificação:** Produto `OIK-ADR-0011` · Governança `ADR-0033`
**Fonte da verdade:** `oikora-one:docs/adr/0011-hospedagem-ambientes-e-backups.md`

## Contexto

Este é o último ADR do roteiro. Os OIK-ADR-0001 a 0010 decidiram o que roda (API Laravel `/api/v1`, SPA `web/` com entradas `painel` e `membro`, app Expo, fila `database` no PostgreSQL, observabilidade com Alloy → Grafana Cloud + Sentry) e deixaram para cá onde e como roda. Os pedidos recebidos:

- **OIK-ADR-0002:** provedor, versão exata do PostgreSQL 17+, pooler (variável de sessão ou `SET LOCAL`), backups e PITR.
- **OIK-ADR-0003:** painel, área do membro e API sob o mesmo domínio-pai; sessão em banco ou Redis, desde que permita listar e encerrar sessões por conta.
- **OIK-ADR-0004:** cofre de segredos, cifra de disco e backup, chave de campos guardada fora do backup do banco, rotação.
- **OIK-ADR-0005:** retenção e cifra de backups; procedimento de restauração que reaplica as exclusões LGPD (`exclusoes_executadas`).
- **OIK-ADR-0006:** hospedagem estática/CDN, domínios, CSP (estilos em tempo de execução da MUI), roteamento das URLs com `og:` até o Laravel.
- **OIK-ADR-0007:** domínio principal com `apple-app-site-association` e `assetlinks.json`, página web de exclusão de conta, organização de demonstração em produção, canais de OTA de homologação e produção, segredos de build.
- **OIK-ADR-0008 (item 18):** workers supervisionados (um dedicado a `codigos`) e agendador; SPF/DKIM/DMARC e domínio verificado na Meta; segredos por ambiente; números e contas de teste em homologação; gatilho do Redis.
- **OIK-ADR-0009 (item 14):** cofre das credenciais do inChurch por organização; armazenamento privado de fotos e XLSX; cifra e expiração do bruto também nos backups; dados fictícios em homologação; processo da execução.
- **OIK-ADR-0010 (item 15):** Alloy junto da API e dos workers com saída para Grafana Cloud e Sentry; health checks públicos; segredos de Grafana, Sentry, EAS e CI; ambientes separados pelo rótulo `ambiente`; PostgreSQL do CI igual ao de produção; deploy só depois do CI verde na `main`, com marcação de deploy.

Restrições informadas pelo usuário (2026-10-21): orçamento de hospedagem **até ~R$ 200/mês** (produção + homologação, sem WhatsApp/SMS/e-mail); **dados no Brasil obrigatoriamente** (banco, arquivos e backups); operação pelos **próprios devs com o mínimo de esforço**; instância dedicada para cliente grande **só com o caminho aberto**; **RPO ~5 min** (PITR); **RTO desejado de 1h, inclusive em cultos**; domínio **ainda não registrado**; homologação **sempre ligada e mínima**. Equipe de 2–4 devs + IA, sem plantão 24h; repositório privado no GitHub Free.

Fatos levantados em 2026-10-21: o Lightsail chegou a São Paulo em junho de 2026, com os mesmos preços das demais regiões; o banco gerenciado do Lightsail oferece PostgreSQL só até a 16, e o plano de US$ 15 não é cifrado; o PITR do Supabase é um adicional de ~US$ 100/mês; no DBaaS da Magalu Cloud não há confirmação pública de PITR nem de PostgreSQL 17.

## Opções consideradas

Todas em São Paulo, com Cloudflare (plano gratuito) à frente para DNS, TLS e cache.

- **A — AWS gerenciado mínimo** — VM Lightsail com Docker Compose (API, workers, agendador, Alloy, Caddy), RDS PostgreSQL 17 em uma zona com PITR e cifra KMS, S3 para arquivos, SSM Parameter Store para segredos. ~R$ 220–280/mês.
- **B — Tudo em VM** — VM Lightsail maior com o PostgreSQL 17 em container e PITR por WAL-G para o S3. ~R$ 180/mês.
- **C — Magalu Cloud** — VM, DBaaS PostgreSQL e Object Storage, cobrados em reais. ~R$ 180/mês.

Descartadas sem comparação completa: banco gerenciado do Lightsail (PostgreSQL ≤ 16 viola o OIK-ADR-0002; o plano de entrada não é cifrado, o que viola o OIK-ADR-0004) e Supabase (PITR estoura o orçamento; sem ele o RPO é de 24 h).

## Comparação

| Pilar | A — AWS gerenciado | B — Tudo em VM | C — Magalu Cloud |
|---|---|---|---|
| Desempenho | Bom: banco em máquina própria, latência local; a VM só roda PHP e workers. | Bom: tudo local, mas banco e PHP disputam a mesma CPU e memória. | Bom no papel; sem histórico de produção que confirme. |
| Simplicidade de manutenção | Bom: patch, backup, PITR e cifra do banco ficam com a AWS; a VM não guarda estado e é recriada por script. | Ruim: arquivamento de WAL, upgrade do PostgreSQL, disco e testes de restauração são da equipe, sem plantão. | Aceitável: gerenciado, mas com documentação curta, poucos exemplos e menos ferramentas de terceiros. |
| Boas práticas | Ótimo: cifra KMS em repouso, PITR, segredos fora do disco, banco separado do servidor web, infraestrutura como código. | Aceitável: funciona, mas o banco vive junto da aplicação exposta à internet. | Ruim: RPO de 5 min (PITR) e PostgreSQL 17 (OIK-ADR-0002) não confirmados. |
| Escalabilidade | Bom: troca de classe do RDS, Multi-AZ e réplica de leitura por configuração; segunda VM atrás de balanceador. | Aceitável: crescer exige separar o banco depois, com migração e janela. | Aceitável: tem multi-zona e réplicas; sem PITR confirmado. |
| Observabilidade / logs | Bom: métricas do RDS, horário restaurável visível, Alloy na VM, verificação externa pelo Grafana. | Aceitável: atraso do arquivamento de WAL é mais um sinal que, se ninguém olhar, só aparece no dia da restauração. | Aceitável: telemetria do DBaaS pouco documentada. |

## Decisão

1. **Provedor e região.** AWS, região São Paulo (`sa-east-1`), para produção e homologação, numa conta AWS própria do Oikora com MFA e usuários nominais; nenhum dado de produção fora do Brasil. Cloudflare (plano gratuito) como DNS, proxy, TLS de borda, cache de arquivos estáticos e proteção básica; a origem só aceita tráfego vindo da Cloudflare. Cobrança estimada de ~R$ 220–280/mês (câmbio ~R$ 5,50; reserva de 1 ano do RDS reduz para ~R$ 220), acima do teto de R$ 200 por escolha do usuário.

2. **Computação de produção.** Uma VM Lightsail Linux de 2 GB (Ubuntu LTS, atualizações de segurança automáticas) rodando Docker Compose com imagens publicadas pelo CI:
   - `web`: Caddy servindo os arquivos estáticos de `painel` e `membro` (copiados na imagem) e repassando `/api/*`, as URLs de formulário público com `og:` (OIK-ADR-0006) e os endpoints de saúde ao PHP (FPM ou FrankenPHP — escolha na Spec);
   - workers separados com limite de memória: `worker-codigos` (só a fila `codigos`, OIK-ADR-0008), `worker-geral` (`avisos` e padrão) e `worker-pesadas` (exportações e a execução da migração do OIK-ADR-0009, com timeout longo);
   - `scheduler` (`schedule:work`);
   - `alloy` (Grafana Alloy) recebendo OTLP da API e dos workers, com saída para Grafana Cloud e Sentry.
   Supervisão por `restart: unless-stopped` + healthcheck de cada serviço; batimento de workers e agendador conforme o OIK-ADR-0010. A VM é descartável: nada de estado no disco além de cache; é recriada por script (`cloud-init` + Compose) em menos de 30 min. Gatilho de aumento para 4 GB: memória acima de 80% sustentada ou swap em uso contínuo.

3. **Banco de produção.** Amazon RDS for PostgreSQL 17 (última versão menor disponível, fixada em um único arquivo de versões do repositório e usada também pela imagem do CI e da homologação), classe `db.t4g.micro` em uma zona, armazenamento gp3, cifra em repouso com KMS, acesso só pela rede privada a partir da VM (peering do Lightsail com a VPC), TLS obrigatório, sem acesso público. Extensões `pg_trgm` e `unaccent`. Atualização de versão menor automática desligada: a equipe sobe a versão de propósito, junto com a imagem do CI.
   - **Sem pooler** no início: conexões diretas, poucas e previsíveis (processos PHP + workers), com `application_name` por processo (OIK-ADR-0002).
   - **Contexto da RLS por transação:** `set_config('app.organizacao_id', …, true)` (equivalente a `SET LOCAL`) dentro da transação de cada requisição e de cada job; nenhum código depende de estado da sessão da conexão. Assim um PgBouncer em modo transação pode entrar depois sem mudar a regra. Detalhe do middleware na Spec.
   - **Sessão, cache e fila no PostgreSQL** (drivers `database`): a tabela de sessões guarda a conta para listar e encerrar sessões (OIK-ADR-0003). **Redis** só entra pelo gatilho do OIK-ADR-0008 (idade do job mais antigo de `avisos` acima de 1 min de forma sustentada) ou por contenção medida nas tabelas de sessão/cache; quando entrar, começa como container na própria VM.

4. **Ambientes.**
   - **Desenvolvimento:** local, Docker Compose com as mesmas imagens e o mesmo PostgreSQL 17 da produção; esquema `oikora://` do app só aqui.
   - **Homologação:** sempre ligada, VM Lightsail de 1 GB com o mesmo Compose e o PostgreSQL 17 em container (mesma imagem do CI); só **dados fictícios** gerados por seeders — dado real nunca sai da produção (OIK-ADR-0009). Contas de teste dos provedores: números de teste da Meta, servidor de teste do Postmark, credenciais de teste do Twilio, projeto Expo no canal `homologacao`. Backup da homologação é dispensado (recriável por seed).
   - **Produção:** itens 2 e 3.
   - Telemetria dos dois ambientes vai para o mesmo stack do Grafana Cloud e do Sentry, separada pelo rótulo `ambiente` (`homologacao`, `producao`) (OIK-ADR-0010).

5. **Domínios.** O domínio `.com.br` (nome a registrar pelo usuário) fica no Registro.br com DNS na Cloudflare.
   - Domínio principal (`<dominio>`): entrada `membro`, formulários públicos com `og:`, `/.well-known/apple-app-site-association`, `/.well-known/assetlinks.json` (OIK-ADR-0007), página pública `/excluir-conta` e destino dos links universais.
   - `painel.<dominio>`: entrada `painel`.
   - A API responde em `/api/v1` **nos dois hosts** (mesma origem, sem CORS); o app usa `https://<dominio>/api/v1`. O cookie de sessão usa `Domain=<dominio>` para valer no domínio-pai (OIK-ADR-0003); `SANCTUM_STATEFUL_DOMAINS` lista os dois hosts.
   - **Homologação em domínio registrável separado** (ex.: `<nome>-hml.com.br`), para que o cookie de produção, válido em todos os subdomínios de `<dominio>`, nunca seja enviado a um servidor de homologação.
   - E-mail em subdomínio próprio (ex.: `avisos.<dominio>`) com SPF, DKIM (Postmark) e DMARC começando em `p=none` e indo a `quarantine` após o aquecimento; domínio verificado no Meta Business por registro DNS (OIK-ADR-0008).

6. **Borda, cache e CSP.** Arquivos com hash do Vite: `Cache-Control: public, max-age=31536000, immutable`, cacheados na Cloudflare; `index.html`, casca `og:`, arquivos de associação do app e `/api/*`: sem cache na borda. Cabeçalhos de segurança emitidos pela origem (Caddy/Laravel): HSTS, `X-Content-Type-Options`, `Referrer-Policy`, `frame-ancestors 'none'` e CSP com `default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob: <bucket de arquivos>; connect-src 'self' <Sentry> <coletor OTel do navegador>; object-src 'none'; base-uri 'self'`. `unsafe-inline` só em `style-src`, por causa dos estilos em tempo de execução da MUI (OIK-ADR-0006); script inline continua proibido. Lista final de origens na Spec.

7. **Arquivos.** Bucket S3 privado em São Paulo por ambiente, com bloqueio de acesso público, cifra padrão (SSE-S3) e versionamento; leitura e upload só por URL assinada de curta duração emitida pela API depois da autorização (OIK-ADR-0004). Prefixos: fotos e anexos por organização; `migracao/` para fotos baixadas e XLSX do inChurch (OIK-ADR-0009), apagados pelo job de 90 dias e com expiração de versões antigas em 14 dias; `lgpd/` (item 9). Acesso da VM por usuário IAM com chave de menor privilégio (bucket do ambiente e caminho de parâmetros do ambiente), rotacionada a cada 90 dias — o Lightsail não aceita papel de instância.

8. **Segredos e chaves.**
   - **Segredos da plataforma** (APP_KEY, chave de campos e chave HMAC do CPF do OIK-ADR-0004, senha do banco, Postmark, Twilio, Expo, token do Grafana Cloud, DSN do Sentry, número da plataforma no WhatsApp) no **SSM Parameter Store** (`SecureString`, nível padrão sem custo), um caminho por ambiente (`/oikora/producao/…`, `/oikora/homologacao/…`), lidos na subida dos containers; nunca no repositório nem na imagem.
   - **Segredos por organização** (token de integração da Meta, credenciais da API do inChurch) **cifrados no banco** com chave própria guardada no SSM, no mesmo padrão do OIK-ADR-0004; o backup do banco não basta para decifrá-los.
   - **Chaves fora do backup do banco:** as chaves de cifra vivem só no SSM; cópia de custódia offline num cofre de senhas da organização, acessível por duas pessoas, atualizada a cada rotação. Perder SSM e custódia ao mesmo tempo torna CPF, RG e saúde ilegíveis — por isso a custódia é obrigatória.
   - **Rotação:** chave de campos e HMAC anualmente ou após incidente, pelo comando do OIK-ADR-0004; chaves de provedores ao trocar alguém da equipe; chave IAM da VM a cada 90 dias.
   - **CI e build:** o GitHub (plano Free, sem ambientes protegidos em repositório privado) guarda só o mínimo, como segredos de repositório com prefixo por ambiente: chave SSH de deploy por ambiente, token de leitura do registro de imagens, token de source maps do Sentry e `EXPO_TOKEN`. As variáveis do app ficam nos ambientes do EAS (`homologacao`, `producao`).

9. **Backups e restauração (RPO ~5 min).**
   - **PITR do RDS com retenção de 14 dias** (backups automáticos cifrados pelo KMS). Antes de toda migração de banco destrutiva, snapshot manual, apagado após 14 dias.
   - **Cópia independente diária:** `pg_dump` cifrado com chave pública (`age`), feito pela VM de produção às 3h e enviado a um Object Storage de **outro provedor no Brasil** (Magalu Cloud), com retenção de 14 dias. Protege contra perda ou comprometimento da conta AWS. A chave privada fica só na custódia offline.
   - **Retenção e LGPD:** o dado excluído (pedido LGPD, bruto da migração aos 90 dias) some dos backups em até 14 dias, porque nenhum backup vive mais que isso (OIK-ADR-0005 e 0009).
   - **Exclusões fora do banco:** cada exclusão executada grava, além de `exclusoes_executadas`, um registro mínimo (ids de pessoa e pedido, data, modo — sem dado pessoal) no prefixo `lgpd/` do S3, só inclusão (versionado, com bloqueio de objeto). Uma restauração para antes da exclusão traz de volta a tabela antiga; o registro no S3 não volta.
   - **Procedimento de restauração (runbook):** restaurar por PITR numa instância nova → apontar a VM → rodar o comando de reaplicação das exclusões lendo `exclusoes_executadas` **e** `lgpd/` → conferir contagens e o teste de catálogo de RLS → abrir o tráfego. Restauração de uma única organização: PITR numa instância temporária e cópia dirigida, sob o mesmo comando.
   - **Teste de restauração trimestral** numa instância descartável da própria conta de produção (dado real nunca vai à homologação), apagada no fim; resultado e duração registrados.

10. **Disponibilidade (RTO).** Meta de 1h tratada como **melhor esforço**; garantia de até **4h no horário comercial**. Falhas comuns se recuperam sozinhas: processo cai → Compose reinicia; VM com defeito de hardware → recuperação do Lightsail; manutenção do RDS agendada fora de cultos. Restauração do banco ou recriação da VM exigem uma pessoa (30–60 min de trabalho, roteiro pronto). Queda inteira da região São Paulo não é coberta no prazo: a volta seria pela cópia do item 9 em outro provedor, em dias. **Gatilho do Multi-AZ** no RDS (~R$ 130/mês a mais, failover automático em minutos): primeira organização pagante fora da denominação ou primeiro incidente de banco que passe de 1h.

11. **Deploy.**
    - PR roda o CI do OIK-ADR-0010; merge na `main` com CI verde gera as imagens (API/web, worker) com o hash do commit e as publica no GitHub Container Registry privado.
    - **Homologação:** deploy automático após o merge (SSH com chave de deploy restrita: `docker compose pull`, `php artisan migrate --force`, `up -d`, verificação de saúde); OTA do app no canal `homologacao`.
    - **Produção:** promoção da **mesma imagem** por tag `vX.Y.Z` criada por um dev em horário comercial, fora dos cultos; mesma sequência, com snapshot do item 9 antes de migração destrutiva; OTA ou build de loja do canal `producao` com envio gradual (OIK-ADR-0007/0010).
    - Migrações compatíveis com a versão anterior (expandir → contrair) para permitir voltar a imagem sem voltar o banco; padrão detalhado na Spec.
    - Marcação de deploy enviada ao Grafana e ao Sentry e eventos `deploy.*` (OIK-ADR-0010). Reverter = promover a tag anterior.
    - Retenção do registro: últimas 10 imagens por ambiente (cota do GitHub Free).

12. **Endpoints de saúde e verificação externa.** `GET /api/v1/saude` público e sem dado (vivo, banco acessível, idade do job mais antigo por fila, versão); verificados de fora pelo Grafana Synthetic Monitoring junto com `index.html` de `painel` e `membro` e os dois arquivos de associação do app (OIK-ADR-0010).

13. **Organização de demonstração e página de exclusão.** Em produção existe uma organização "Demonstração", com dados fictícios e contas de revisão para Apple e Google, isolada pela RLS como qualquer outra e marcada para não entrar em métricas de negócio. `/excluir-conta` é uma página pública da entrada `membro` que explica e inicia o pedido (OIK-ADR-0007).

14. **Infraestrutura como código e instância dedicada.** Recursos de nuvem (Lightsail, RDS, S3, IAM, parâmetros do SSM sem valores, KMS, DNS na Cloudflare) descritos em **OpenTofu** no repositório (`infra/`), com estado num bucket S3 versionado da própria conta. Instância dedicada para cliente grande: mesma descrição aplicada numa **conta AWS própria do cliente**, com domínio e segredos próprios; contratação, custo e operação decididos quando o cliente existir (novo ADR se mudar a topologia).

15. **Fora deste ADR — o que outros documentos precisam deste.**
    - **Spec:** versão menor exata do PostgreSQL e das imagens, servidor PHP (FPM ou FrankenPHP), middleware do contexto da RLS por transação, lista final de origens da CSP, nomes de buckets, caminhos de parâmetros e serviços do Compose, comando de reaplicação das exclusões, roteiro de deploy e de restauração, padrão expandir/contrair das migrações.
    - **Bootstrap:** criar conta AWS e Cloudflare, registrar os domínios de produção e de homologação, conferir os preços de São Paulo na calculadora da AWS, conta no Object Storage da Magalu Cloud, cofre de senhas para a custódia, peering Lightsail–VPC.
    - **Ações do usuário:** escolher e registrar o nome do domínio; aprovar o custo mensal e a reserva de 1 ano do RDS.

## Justificativa

A opção A vence porque é a única que entrega, ao mesmo tempo, as três exigências fixas do usuário: dados no Brasil, RPO de ~5 min e operação de mínimo esforço. O PITR, a cifra KMS, os patches e o backup do banco ficam com a AWS; a equipe de 2–4 devs sem plantão só mantém uma VM sem estado, recriável por script (**simplicidade**). Nenhuma escolha contraria um ADR anterior: PostgreSQL 17 com RLS e papel sem `BYPASSRLS` (0002), sessão listável no banco (0003), chaves fora do backup (0004), backups que expiram o dado excluído e reaplicação LGPD resistente à própria restauração (0005), mesma origem para SPA e API (0006), links universais no domínio principal (0007), workers separados e fila no PostgreSQL (0008), bruto da migração em armazenamento privado e nunca na homologação (0009), mesmo PostgreSQL no CI e deploy só com CI verde (0010) (**boas práticas**). O banco separado da VM e o contexto de RLS por transação deixam abertos Multi-AZ, réplica, pooler, Redis e instância dedicada por configuração, sem reescrever código (**escalabilidade**). O volume inicial (uma denominação, ~520 membros) cabe com folga numa `db.t4g.micro` e numa VM de 2 GB, com gatilhos medidos para crescer (**desempenho**). Health checks públicos, métricas do RDS e a verificação de fora pelo Grafana mostram a queda mesmo quando a VM inteira está fora (**observabilidade**).

**Discordâncias registradas:**
- **Orçamento:** a opção A custa ~R$ 220–280/mês, até ~40% acima do teto de R$ 200. Só a opção B cabia no teto, ao preço de pôr a operação do banco (WAL, upgrades, restauração) sobre uma equipe sem plantão. O usuário aceitou o custo maior.
- **RTO de 1h nos cultos sem plantão:** com o banco em uma zona, só falhas de processo e de hardware da VM voltam sozinhas; restauração exige uma pessoa. O usuário aceitou 1h como melhor esforço e 4h no horário comercial como garantia, com o Multi-AZ entrando por gatilho.

## Consequências

**Positivas:**
- Dados de produção, arquivos e backups só no Brasil, com cifra em repouso e chaves fora do backup.
- Restauração para qualquer minuto dos últimos 14 dias sem script próprio, e uma segunda cópia fora da conta AWS.
- A restauração não "ressuscita" pessoas excluídas a pedido: o registro das exclusões sobrevive fora do banco.
- Uma VM sem estado, recriável em menos de 30 min, com deploy igual em homologação e produção (mesma imagem promovida).
- Caminhos claros para crescer (Multi-AZ, VM maior, Redis, pooler, réplica) e para instância dedicada, todos por configuração e OpenTofu.

**Negativas / trade-offs aceitos:**
- Custo acima do teto pedido; preço do RDS em São Paulo e câmbio ainda a confirmar.
- Ponto único de falha na VM e no banco em uma zona: uma restauração ou recriação no domingo depende de alguém disponível; queda da região São Paulo inteira não tem volta em horas.
- Chave IAM estática na VM (limitação do Lightsail), mitigada por menor privilégio e rotação.
- `'unsafe-inline'` em `style-src` por causa da MUI.
- Homologação com 1 GB e dados fictícios não reproduz carga real; teste de carga, se necessário, roda em instância temporária.
- Mais um fornecedor (Magalu Cloud) e um domínio extra (homologação) para administrar.
- Fila, sessão e cache no PostgreSQL disputam conexões com a aplicação até o gatilho do Redis.

## Observabilidade — o que precisa ser instrumentado
- **Eventos a logar:** `deploy.started`/`deploy.succeeded`/`deploy.failed` (ambiente, tag, commit, duração, migração rodada); `backup.dump.succeeded`/`backup.dump.failed` (tamanho, duração, destino); `restore.started`/`restore.finished` (tipo, ponto no tempo, duração, exclusões reaplicadas); `lgpd.exclusao.registrada_externa` (id do pedido, sem dado pessoal); `segredo.rotacionado` (nome lógico, ambiente, sem valor); `infra.vm.recriada`; batimento dos workers e do agendador (OIK-ADR-0010).
- **Métricas a expor:** CPU, memória, swap e disco da VM (Alloy); CPU, conexões, memória livre, IOPS, armazenamento livre e atraso do horário restaurável do RDS (CloudWatch → Grafana Cloud); idade da última cópia diária em outro provedor; idade do job mais antigo por fila (gatilho do Redis); conexões por `application_name`; dias desde o último teste de restauração; custo mensal estimado da conta AWS.
- **Alertas a configurar:** API, `painel`, `membro` ou arquivos de associação fora na verificação externa (P1); RDS com armazenamento livre < 20% ou conexões > 80% do máximo (P2); horário restaurável atrasado > 15 min (P1); cópia diária com mais de 26 h (P2); memória da VM > 80% por 30 min ou swap contínuo (P2); falha de deploy (P2); certificado de origem a menos de 14 dias de vencer (P2); teste de restauração com mais de 100 dias (P3); orçamento AWS acima de R$ 300 no mês (P3, alerta de orçamento da própria AWS). Todos com runbook, nas janelas do OIK-ADR-0010.
- **Cenário de incidente validado:** num domingo às 8h50, antes do culto, o alerta P1 da verificação externa avisa no chat que `membro` e `/api/v1/saude` não respondem. O dev da janela abre o Grafana: `/api/v1/saude` parou às 8h44; as métricas da VM pararam no mesmo minuto, mas as do RDS continuam normais, com o horário restaurável em dia — o banco está bem, a VM não. No console do Lightsail a instância aparece com falha de verificação do sistema. Ele segue o runbook "recriar VM": cria a VM pelo OpenTofu, o `cloud-init` instala Docker, lê os segredos do SSM e sobe o Compose com a tag atual de produção; o DNS da Cloudflare passa ao novo IP. Às 9h20 a verificação externa volta, o evento `infra.vm.recriada` fica registrado e nenhum dado se perdeu, porque nada morava na VM. Se fosse o banco, o painel teria mostrado o RDS fora e o runbook seria a restauração por PITR com reaplicação das exclusões, também sem ler código.

## Lacunas conhecidas
- Preço do RDS `db.t4g.micro` em São Paulo não confirmado (estimado em 1,6–1,9 vez o de us-east-1, US$ 0,016/h); câmbio estimado em R$ 5,50. Conferir na calculadora da AWS no bootstrap; se passar de ~R$ 300/mês, revisar o tamanho da homologação antes do banco.
- Disponibilidade de PostgreSQL 17 com `pg_trgm` e `unaccent` no RDS de São Paulo e do peering Lightsail–VPC a confirmar no bootstrap (esperado, não verificado).
- Bloqueio de objeto e retenção no Object Storage da Magalu Cloud não verificados; sem bloqueio, a cópia independente perde parte da proteção contra comprometimento.
- O nome do domínio não foi escolhido.
- Janelas de culto continuam informadas de forma geral; a janela de manutenção do RDS será definida com os horários reais.
- Conferência jurídica do `OPEN-013` pode mudar o prazo de 14 dias dos backups.
