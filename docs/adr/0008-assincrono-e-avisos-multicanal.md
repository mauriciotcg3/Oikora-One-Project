# ADR-0008: Assíncrono e avisos multicanal — módulo Avisos com caixa de saída no PostgreSQL

**Status:** Aceito — após confirmação do usuário (2026-10-21)
**Data:** 2026-10-21
**Contexto do projeto:** Oikora One — plataforma da denominação (3 igrejas, ~520 membros) que substitui o inChurch módulo a módulo, com plano de atender outras igrejas.
**Identificação:** Produto `OIK-ADR-0008` · Governança `ADR-0030`
**Fonte da verdade:** `oikora-one:docs/adr/0008-assincrono-e-avisos-multicanal.md`

## Contexto

O produto (`docs/product/0001-fundacao-e-pessoas.md`) exige que convites e avisos cheguem por **e-mail, WhatsApp e como aviso dentro da plataforma** (FR-036, §15): convite ao painel, conta vinculada (FR-019), alteração aprovada/recusada com justificativa (FR-022), transferência (FR-031) e pedido de exclusão (FR-024). Datas comemorativas (aniversários, batismos, casamentos — FR-033/034) estão no MVP. O §20 deixou "comportamento em falha a definir". No inChurch, a justificativa da recusa volta ao membro por push e e-mail, e há "Mensagens do sistema" automáticas para datas comemorativas (`docs/analise/`, só referência).

ADRs anteriores deixaram para cá:

- **OIK-ADR-0001:** cada caso de uso é uma Action única chamada por todos os canais; nada de dado pessoal em log.
- **OIK-ADR-0002:** todo job e todo comando precisam gravar o contexto de organização da RLS; contexto ausente é erro; papel de plataforma com `BYPASSRLS` só em comando administrativo auditado.
- **OIK-ADR-0003:** verificação de celular por código via WhatsApp/SMS; 2FA de 6 dígitos (10 min) por e-mail ou WhatsApp, nunca no canal de uma recuperação de senha das últimas 24h; convite de 72h ao contato da Pessoa; recuperação de 60 min. Provedores e falhas de envio ficaram para este ADR.
- **OIK-ADR-0005:** auditoria só de inclusão gravada pela Action, sem valores; consentimentos revogáveis por finalidade; removedor único da LGPD; retenção 5 anos (autenticação 1 ano); avisos ao titular ficaram para cá.
- **OIK-ADR-0006:** SPA com TanStack Query; tema (logo e cor) por organização.
- **OIK-ADR-0007 §7 e §16:** o app registra o token de push do aparelho ligado ao token Sanctum, payload sem dado pessoal, cada push com organização e destino (link universal). Ficaram para cá: provedor de push e formato do token, envio, preferências por tipo, falha e reenvio, push como canal do aviso na plataforma e da recusa de alteração.
- O Standard (`docs/architecture/architecture-standard.md` §10, §16, §18, §19) deixou o driver de fila em aberto, exige integração por Contract + Adapter (COMM-004), correlação em jobs (COMM-005) e proíbe broadcast sem requisito (COMM-006).

Informado pelo usuário em 2026-10-21: **não há conta** Meta Business, WhatsApp Business API nem provedor; o WhatsApp sai do **número de cada organização**; mensagens **automáticas de parabéns entram no MVP, inclusive por WhatsApp**; o custo de WhatsApp é **medido por organização e repassado ao cliente**. E-mail sai do **domínio da plataforma**, sem provedor contratado. **SMS só como reserva de código**. A caixa de avisos **não precisa de tempo real**. Organização sem número ligado fica **sem WhatsApp até ligar**. O membro escolhe **por tipo e canal**, sem desligar os obrigatórios. O que pode esperar sai numa **janela diurna**. No limite do plano, **corta-se o não essencial**. Equipe de 2–4 desenvolvedores + IA, sem plantão 24h (horário comercial + cultos), expansão para centenas de organizações em 2–3 anos.

## Opções consideradas

- **A. Laravel Notifications direto** — a Action chama `notify()` com `ShouldQueue` + `afterCommit`; fila em Redis + Horizon; caixa de avisos pelo canal `database` do Laravel.
- **B. Módulo Avisos com caixa de saída no PostgreSQL** — na mesma transação, a Action grava o fato, o `aviso` e um `envio` pendente por canal; a fila é a `database` do Laravel no mesmo PostgreSQL e um varredor agendado reenfileira o que ficar parado; um Adapter por provedor.
- **C. Orquestrador de mensagens terceirizado** (Novu, Knock, OneSignal etc.) — a API envia o evento; o serviço cuida dos canais, das preferências, da caixa de avisos e das tentativas.

Escolhas de provedor dentro da opção vencedora:

- **WhatsApp:** W1 API oficial da Meta (Cloud API) direto, com a Oikora como *Tech Provider* e *Embedded Signup*; W2 provedor intermediário oficial (Twilio, 360dialog, Gupshup, Zenvia); W3 API não oficial (Z-API, Evolution — automação do WhatsApp Web).
- **Push:** P1 serviço de push do Expo; P2 FCM/APNs direto.
- **E-mail:** E1 Postmark; E2 Amazon SES; E3 Resend.
- **SMS (só reserva de código):** S1 Twilio Programmable Messaging; S2 provedor nacional (Zenvia etc.).

## Comparação

| Pilar | A. Notifications + Redis | B. Avisos + caixa de saída no PG | C. Orquestrador SaaS |
|---|---|---|---|
| Desempenho | Ótimo — Redis aguenta alto volume | Bom — fila no PG com `SKIP LOCKED` aguenta milhares de jobs/min; o volume previsto (aniversários de centenas de organizações, avisos de secretaria) fica muito abaixo disso | Bom — escala do fornecedor, mas cada aviso tem uma chamada externa a mais |
| Simplicidade de manutenção | Bom — padrão do Laravel, mas exige Redis já no MVP | Bom — nada novo além do PostgreSQL; o módulo é nosso para manter | Aceitável — pouco código, mas mais um fornecedor; número de WhatsApp por organização e RLS ficam fora do nosso modelo |
| Boas práticas | Aceitável — `afterCommit` perde o aviso se o processo cair entre o commit e o envio à fila; canal `database` sem `organizacao_id` nem RLS | Ótimo — entrar na fila é atômico com o fato (nunca se avisa algo que não foi gravado); `avisos`/`envios` sob RLS; Contract + Adapter (COMM-004) | Ruim — dado pessoal vai a mais um operador LGPD, a RLS não chega lá, e caixa e preferências ficam fora da `/api/v1` |
| Escalabilidade | Ótimo | Bom — trocar o driver por Redis/SQS é configuração; a caixa de saída `envios` mantém a garantia sem a atomicidade da fila | Bom — custo cresce por usuário ativo |
| Observabilidade / logs | Bom — Horizon | Ótimo — cada envio tem estado, tentativas, erro do provedor e `request_id` consultáveis por organização; o suporte responde "chegou?" sem ler log | Aceitável — diagnóstico no painel do fornecedor, separado do nosso `request_id` |

**WhatsApp:**

| Pilar | W1 Meta Cloud API direto | W2 Provedor oficial | W3 Não oficial |
|---|---|---|---|
| Desempenho | Ótimo | Ótimo | Ruim — depende de celular/sessão web conectada |
| Simplicidade de manutenção | Aceitável — verificação da empresa e revisão do app na Meta (semanas); templates criados por API em cada conta da organização | Bom — onboarding assistido pelo provedor | Bom no início, Ruim depois (reconexões, QR code) |
| Boas práticas | Ótimo — oficial, templates aprovados, opt-in; a organização paga a Meta direto (custo repassado sem faturarmos WhatsApp) | Ótimo — oficial; faturamento passa pela Oikora | Ruim — viola os termos do WhatsApp; risco de banir o número da igreja |
| Escalabilidade | Ótimo — centenas de contas sem sobretaxa | Bom — sobretaxa por mensagem ou mensalidade por número | Ruim |
| Observabilidade / logs | Bom — webhook de status (enviado, entregue, lido, falhou) com código de erro da Meta | Bom | Ruim |

**Push, e-mail e SMS:**

| Pilar | P1 Expo push | P2 FCM/APNs direto | E1 Postmark | E2 Amazon SES | S1 Twilio | S2 Nacional |
|---|---|---|---|---|---|---|
| Desempenho | Bom — lotes de 100 por chamada | Ótimo | Ótimo — entrega transacional rápida | Ótimo | Bom | Bom |
| Simplicidade de manutenção | Ótimo — uma API para iOS e Android; app já é Expo (0007), sem SDK do Firebase | Aceitável — Firebase no app + chaves APNs/FCM no servidor | Ótimo — mailer nativo do Laravel, webhooks simples | Aceitável — webhooks via SNS, saída do sandbox | Bom — API e webhook documentados | Aceitável — documentação e SDK menos padronizados |
| Boas práticas | Bom — payload sem dado pessoal; token de acesso do Expo | Bom | Ótimo — fluxos separados (transacional × broadcast), `List-Unsubscribe` | Bom — preso à hospedagem do 0011 | Bom | Bom — rota nacional |
| Escalabilidade | Bom — limite de envio alto; troca para P2 prevista pela coluna `provedor` | Ótimo | Bom — custo maior que SES em alto volume (gatilho de troca) | Ótimo — mais barato em volume | Bom | Bom |
| Observabilidade / logs | Bom — tickets e recibos (`DeviceNotRegistered`) | Bom | Ótimo — histórico por mensagem, bounce e reclamação por webhook | Bom | Bom — status callback | Aceitável |

## Decisão

Adotar a **opção B** com **W1** (WhatsApp pela API oficial da Meta), **P1** (push pelo Expo), **E1** (Postmark) e **S1** (Twilio, só reserva de código).

1. **Módulo Avisos.** Bounded context próprio. As Actions só chamam o Contract `Avisos::registrar(tipo, destinatários, referência, chave)`; nenhum módulo chama provedor direto (COMM-004). Um **catálogo de tipos de aviso** no código define, por tipo: categoria (`codigo`, `convite`, `servico`, `automatica`), se é obrigatório, canais permitidos e ordem padrão, se pode esperar a janela diurna, template por canal e categoria de template do WhatsApp (`authentication`, `utility`, `marketing`). Cada canal é um Adapter em Infrastructure (Meta, Postmark, Twilio, Expo). O e-mail usa o mailer nativo do Laravel; o canal `database` do Laravel Notifications não é usado.
2. **Caixa de saída transacional — nada sai antes do commit.** Na mesma transação do fato, a Action grava o `aviso` (um por destinatário) e um `envio` pendente por canal, e enfileira o job de envio na fila `database`, que usa a mesma conexão. Fato, aviso e job entram juntos ou não entram. `envios` é a fonte do estado: `pendente`, `agendado`, `enviando`, `enviado`, `entregue`, `lido`, `falhou_temporario`, `falhou_definitivo`, `suprimido`, `cancelado`. Chamada a provedor nunca acontece dentro da transação. Eventos de domínio para outros módulos usam `afterCommit`, e listeners lentos ou externos rodam em fila.
3. **Filas e workers.** Driver `database` do Laravel no PostgreSQL. A tabela `jobs` fica fora da RLS, como as de identidade, e o payload leva só ids, sem dado pessoal. Filas:
   - `codigos`, com **worker dedicado**: um lote de parabéns nunca atrasa um 2FA;
   - `avisos`, `automaticas`, `webhooks`, `pesadas` (exportação, importação) e `default`.

   **Tentativas:**
   - avisos e automáticas: até 5, com espera de 1 min, 5 min, 30 min e 2 h;
   - códigos: até 2 tentativas em 2 min (o código vale 10 min); depois, o canal de reserva.

   Cada Adapter classifica o erro em temporário (timeout, 5xx, limite de taxa → nova tentativa) ou **definitivo** (número sem WhatsApp, e-mail inexistente, token de push inválido, template rejeitado, conta sem pagamento → `falhou_definitivo` na hora e próximo canal). A entrega é *pelo menos uma vez*: em timeout ambíguo, aceita-se uma duplicata rara em vez de perder o aviso. Job esgotado vai para `failed_jobs` com o `envio` marcado. Horizon só existe se o driver mudar para Redis. O gatilho da troca, avaliado no OIK-ADR-0011, é a idade do job mais antigo da fila `avisos` passar de 1 min de forma sustentada.
4. **Idempotência.** `envios` tem chave única (`aviso_id`, `canal`). `avisos` tem chave única (organização, tipo, referência, destinatário, chave) — por exemplo, o parabéns de aniversário é único por (organização, pessoa, data). O job trava o envio (`FOR UPDATE SKIP LOCKED`) e confere o estado antes de chamar o provedor; envio já `enviado` ou além não é repetido. Webhooks só avançam o estado (`entregue` não volta para `enviado`).
5. **Contexto de organização em jobs (OIK-ADR-0002).** Todo job de domínio leva `organizacao_id`, `request_id`/correlation id e, quando houver, `conta_id`. Um **middleware de job** confere se a organização existe e está ativa, abre a transação, grava o contexto da RLS (mecanismo do 0002/0011) e o limpa no fim. Contexto ausente gera `tenant.context.missing` e o job falha sem nova tentativa: é defeito, não carga. Jobs sem organização só existem numa **lista fechada** e marcada:
   - jobs de identidade (códigos de verificação, 2FA e recuperação), que usam a via estreita do OIK-ADR-0003;
   - jobs de plataforma (distribuição por organização, processamento inicial de webhook).

   A lista de organizações ativas e seus fusos vem de uma função estreita, sem `BYPASSRLS`. **Rotina agendada faz distribuição por organização:** um job por organização, cada um com o próprio contexto.
6. **Agendador.** Laravel Scheduler (`schedule:run` a cada minuto) em uma única instância, com `onOneServer` + `withoutOverlapping` (trava no cache do banco). Rotinas:
   - distribuidor das automáticas, a cada hora: para cada organização em que já são **9h no fuso dela**, enfileira `GerarAutomaticasDoDia(organização, data)` (aniversário, batismo, casamento), idempotente por (organização, data, tipo);
   - `envios:varrer`, a cada minuto: reenfileira envios pendentes sem job há mais de 5 min e libera os agendados cuja janela abriu;
   - `push:recibos`, a cada 15 min: consulta os recibos do Expo;
   - limpeza de retenção, diária.

   As rotinas do OIK-ADR-0005 (lixeira, retenção da auditoria) seguem o mesmo padrão.
7. **Caixa de avisos (aviso dentro da plataforma).** Tabela `avisos` sob RLS, por conta × organização, com tipo, referência, destino (caminho de link universal, igual ao do 0007), `lido_em` e criação. **O texto é montado na leitura**, a partir do tipo e da referência, com o alcance de quem lê (OIK-ADR-0004); nenhum conteúdo sensível é gravado. API: listar paginado, contagem de não lidos, marcar lido e marcar todos.
   - Web: contador atualizado ao focar a tela, ao navegar e a cada ~60 s com a aba visível (TanStack Query).
   - App: atualiza ao receber push e ao voltar ao primeiro plano.
   - Sem WebSocket nem broadcast (COMM-006).
   - Avisos sobre a ficha de um dependente (BR-024) vão para a conta do responsável.
   - Pessoa sem conta não tem caixa; recebe só pelos canais externos.
8. **Push — serviço do Expo.** Tabela `dispositivos_push`, ligada ao token Sanctum do aparelho (0007): `provedor` (`expo`), token (`ExponentPushToken[...]`), plataforma, versão do app, estado da permissão, ativo e último erro. O push é um canal do mesmo `aviso`:
   - título com o nome da organização e texto genérico, sem dado pessoal ("Há uma novidade na sua ficha"), mais `organizacao_id`, `aviso_id` e destino;
   - envio em lotes de 100, com token de acesso do Expo;
   - recibos consultados depois de 15 min; `DeviceNotRegistered` desativa o registro.

   A coluna `provedor` permite migrar para FCM/APNs direto sem mudar o modelo.
9. **E-mail — Postmark, domínio da plataforma.** Dois fluxos: transacional (códigos, convites, serviço) e broadcast (automáticas).
   - Remetente: "Nome da Igreja via Oikora <avisos@{domínio de envio da plataforma}>", com responder-para no contato da organização. Códigos de identidade saem como "Oikora".
   - Templates Blade/Markdown com o tema da organização (logo e cor, 0006).
   - E-mail não obrigatório leva `List-Unsubscribe` com descadastro em um clique (RFC 8058).
   - Webhooks de entrega, bounce e reclamação.

   O Amazon SES fica como troca quando o volume justificar.
10. **WhatsApp — API oficial da Meta, número por organização.** A Oikora se cadastra como *Tech Provider* da Meta.
    - **Ligar o número:** o administrador liga o número da organização pelo *Embedded Signup* no painel, com permissão própria do catálogo (0004). Ficam guardados a conta WhatsApp Business, o id do número, o nome exibido, o estado, a qualidade e o nível de limite; o token de integração fica cifrado (segredos no 0011).
    - **Pagamento:** a organização paga as mensagens à Meta com o próprio meio de pagamento.
    - **Templates:** catálogo versionado no código (pt_BR), criado por API em cada conta ao ligar e a cada nova versão; só se envia template aprovado. `utility` para convites e serviço, `marketing` para automáticas. A organização não edita o texto no MVP: os parâmetros são nome da igreja, nome da pessoa e link.
    - **Sem número ativo:** organização sem número ligado, ou com número desconectado, fica **sem WhatsApp**; não há reserva pelo número da plataforma.
    - **Número da plataforma:** uma conta da Oikora, usada **só para códigos de identidade** (verificação de celular, 2FA, recuperação), com template `authentication` e custo da plataforma.
    - **Opt-in e opt-out:** só se envia para contato marcado como "é WhatsApp"; responder "SAIR" ou tocar no botão de descadastro desliga a preferência daquela organização.
11. **SMS — Twilio, só reserva de código de identidade.** O SMS é usado quando o WhatsApp devolve falha definitiva (número sem WhatsApp) ou quando a pessoa pede "receber por SMS" depois de uma falha. Só números +55. Limite por número (3/h, 10/dia, ajustável na Spec) e por IP, além dos limites do 0003, contra fraude de SMS. Custo da plataforma.
12. **Regras de canal por categoria.**
    - **Códigos:**
      - canal escolhido pela pessoa (WhatsApp da plataforma ou e-mail), com SMS de reserva;
      - respeita a regra das 24h do 0003;
      - saem na hora, sem caixa de avisos e sem preferência.
    - **Convites** (painel, 0003 item 5):
      - vão por e-mail e pelo WhatsApp da organização, nos dois quando a Pessoa tiver os dois contatos;
      - saem na hora e são obrigatórios.
    - **Serviço** (vínculo FR-019, aprovação/recusa FR-022, exclusão FR-024, transferência FR-031, conta vinculada):
      - caixa de avisos sempre, mais push se houver aparelho, mais **um** canal externo: o preferido da pessoa ou, por padrão, o WhatsApp (se a organização tiver número e o contato for WhatsApp), senão o e-mail;
      - **reenvio pelo próximo canal** em falha definitiva ou quando o WhatsApp não confirma entrega em 30 min;
      - obrigatórios: a pessoa troca o canal externo, mas não desliga.
    - **Automáticas** (parabéns de aniversário, batismo e casamento):
      - cada organização liga por tipo e canal;
      - vão a quem tem **consentimento registrado** na finalidade de mensagens automáticas (0005) e preferência ligada;
      - saem às 9h no fuso da organização, com caixa e push para quem tem conta;
      - reserva por e-mail só em falha definitiva.
13. **Preferências e descadastro.** `preferencias_aviso` guarda conta × organização × tipo × canal, ligada por padrão. Tipos obrigatórios não podem ser desligados. Para pessoa sem conta, o descadastro (link de e-mail com token assinado ou "SAIR" no WhatsApp) fica gravado no contato da Pessoa. Quando a base for consentimento, o descadastro também revoga o consentimento (0005).
14. **Janela diurna.** Tipos que podem esperar (serviço e automáticas) só saem para canais externos e push entre **8h e 21h no fuso da organização** (`organizacoes.fuso`, padrão `America/Sao_Paulo`). Fora disso, o envio fica `agendado` para as 8h. A caixa de avisos é gravada na hora. Códigos e convites saem sempre na hora.
15. **Webhooks de status.** `POST /api/v1/webhooks/{meta|postmark|twilio}`:
    - **entrada:** sem sessão, com limite de taxa e assinatura validada (Meta `X-Hub-Signature-256`, Postmark com credencial do webhook, Twilio `X-Twilio-Signature`); assinatura inválida → 401, sem processar;
    - **processamento:** resposta 200 rápida e o resto num job na fila `webhooks`;
    - **organização:** o id externo leva à organização por uma **função estreita** fora da RLS (mesmo padrão do 0003): o envio pelo id do provedor; a conta WhatsApp Business para a Meta. Depois, o job grava o contexto e atualiza o envio;
    - **guarda:** o payload bruto não é guardado;
    - **efeitos no contato:** bounce definitivo ou "número sem WhatsApp" marca o contato com falha de entrega (`entrega_falhou_em`), e ele deixa de ser usado até ser atualizado; reclamação de spam vira descadastro dos não obrigatórios;
    - **Expo:** não tem webhook; os recibos são consultados pelo agendador (item 6).
16. **Limites de taxa e custo por organização.**
    - **Taxa:** middleware de taxa por provedor (vazão do número na Meta, limites do Postmark, Twilio e Expo) e por organização.
    - **Uso:** contador `uso_mensageria` (organização, mês, canal, categoria, quantidade, custo estimado pela tabela de preços configurada); a fatura real do WhatsApp é da Meta para a organização.
    - **Limite:** o limite mensal de WhatsApp vem do plano e é configurável por organização. Com 80%, o administrador é avisado; com 100%, serviço e automáticas deixam de usar WhatsApp (seguem por e-mail, push e caixa) e convites continuam.
    - **Códigos:** são da plataforma e têm só os limites anti-abuso.
17. **O que fica registrado e por quanto tempo.**
    - **`envios`:** tipo, canal, provedor, destino mascarado (`+55 11 9****-1234`, `m***@g***.com`) + hash do destino, id externo, estados com horário, tentativas, código de erro do provedor e `request_id`. **Sem conteúdo, sem parâmetros, sem código.** Particionada por mês e retida por **12 meses**.
    - **`avisos`:** 12 meses.
    - **`uso_mensageria`:** 5 anos (cobrança).
    - **Eventos de código:** seguem a retenção de autenticação (1 ano, 0003/0005).
    - **Auditoria (0005):** só as ações administrativas sobre canais (ligar/desligar número, mudar limite, ligar automáticas), não cada envio.
    - **LGPD:** o removedor de Pessoas (0005) cobre `avisos`, `preferencias_aviso` e `envios` (destino e hash removidos ou anonimizados conforme o modo).
18. **Fora deste ADR — o que outros ADRs precisam deste.**
    - **OIK-ADR-0009:** migrar e-mails, celulares com "é WhatsApp" e consentimentos de comunicação do inChurch; importação e ensaios rodam com avisos suprimidos (nenhum aviso dispara durante a migração).
    - **OIK-ADR-0010:** ferramentas, painéis e limiares das métricas e alertas abaixo; testes com Adapters falsos e testes de contrato dos webhooks.
    - **OIK-ADR-0011:**
      - processos supervisionados de workers (um dedicado a `codigos`) e do agendador;
      - domínio/subdomínio de envio de e-mail com SPF, DKIM e DMARC, e domínio verificado na Meta;
      - segredos por ambiente (tokens da Meta por organização cifrados, Postmark, Twilio, Expo);
      - números e contas de teste em homologação;
      - gatilho do Redis.
    - **Spec:** catálogo de tipos de aviso, textos dos templates, telas da caixa e das preferências, tela de ligar o WhatsApp, nomes de tabelas e limites numéricos.

## Justificativa

- **Boas práticas:** o requisito central do usuário — não avisar algo que não foi gravado e não perder aviso obrigatório (convite, recusa com justificativa, LGPD) — é cumprido pela atomicidade da fila no mesmo PostgreSQL mais a caixa de saída `envios`. Isso dispensa um *relay* de outbox separado, que a opção A precisaria para fechar a janela do `afterCommit`. `avisos`, `envios` e preferências sob RLS seguem o 0002, e o texto montado na leitura segue o 0004 (alcance) e o 0005 (sem valores). A opção C levaria nome e contato de todos os membros a mais um operador, fora da RLS.
- **Simplicidade de manutenção:** o MVP não ganha Redis só por causa da fila; um módulo com um Adapter por provedor é o padrão do Standard (COMM-004). Push pelo Expo aproveita o app já Expo (0007) sem Firebase no app. A Meta direto evita contrato com intermediário e faturamento de WhatsApp pela Oikora, porque cada organização paga a própria conta.
- **Desempenho:** o volume previsto (centenas de organizações, aniversários diários, avisos de secretaria) é baixo para uma fila no PostgreSQL. A fila `codigos` com worker dedicado garante que 2FA e verificação nunca esperem um lote.
- **Escalabilidade:** o driver de fila e os provedores são trocáveis por configuração e Adapter (Redis/Horizon, SES, FCM/APNs, provedor oficial de WhatsApp), sem tocar nas Actions. O número por organização evita que a reputação de uma igreja derrube o WhatsApp das outras.
- **Observabilidade:** cada envio é uma linha com estado, tentativa, código de erro do provedor e `request_id`. "O aviso chegou?" é respondido por organização, sem ler log e sem ver conteúdo.
- **Discordância registrada:** o número por organização (escolha do usuário) traz atrito de onboarding: cada cliente precisa de portfólio no Meta Business e de um número próprio, e os códigos de identidade exigem um número da plataforma à parte. Foi aceito porque protege a marca e a reputação de cada igreja e casa com o custo pago pelo cliente. Parabéns por WhatsApp são categoria marketing (mais caros, com opt-in obrigatório e limite da Meta por pessoa), aceitos pelo mesmo motivo.

## Consequências

**Positivas:**
- Nenhum aviso sai de fato não gravado, e nenhum aviso obrigatório se perde em queda de processo: a fila e o estado estão no mesmo banco.
- Códigos não esperam lotes; falha de um canal vira reenvio por outro com motivo registrado.
- Custo de WhatsApp pago pela organização à Meta, medido e limitado por plano, sem a Oikora faturar mensagem.
- Caixa de avisos, preferências e consentimento passam pela mesma API, regras de campo e RLS do resto do sistema; web e app usam o mesmo contrato.
- Sem Redis, WebSocket nem orquestrador externo no MVP.

**Negativos / trade-offs aceitos:**
- A Oikora depende da verificação da empresa, do cadastro como *Tech Provider* e da revisão do app na Meta (prazo de semanas); sem isso não há WhatsApp. Cada organização também precisa ligar o próprio número.
- Fila no PostgreSQL disputa conexões e I/O com a aplicação; acima do gatilho, migrar para Redis (0011). Sem Horizon, o painel de filas é nosso (0010).
- Entrega *pelo menos uma vez*: duplicata rara possível em timeout ambíguo.
- Templates do WhatsApp dependem de aprovação da Meta; um template rejeitado deixa o tipo sem WhatsApp até a correção, e os outros canais seguem.
- O texto da caixa é montado na leitura: se a referência for removida (lixeira/LGPD), o aviso mostra texto genérico.
- Janela diurna atrasa avisos de serviço feitos à noite até as 8h, nos canais externos e no push.

## Observabilidade — o que precisa ser instrumentado

- **Eventos a logar:**
  - `aviso.registrado` (tipo, organização, nº de destinatários);
  - `envio.enfileirado`/`enviado`/`entregue`/`lido`;
  - `envio.falhou` (canal, provedor, código de erro do provedor, `temporaria|definitiva`, tentativa);
  - `envio.fallback` (canal de origem → destino, motivo);
  - `envio.suprimido` (motivo: preferência, consentimento, limite, sem número, contato com falha, migração);
  - `envio.agendado_janela`;
  - `webhook.assinatura_invalida` (provedor, IP);
  - `webhook.envio_desconhecido`;
  - `whatsapp.numero.conectado`/`desconectado`/`qualidade_alterada`;
  - `whatsapp.template.aprovado`/`rejeitado` (tipo, versão, organização);
  - `push.token.desativado` (motivo);
  - `uso.limite_80`/`uso.limite_atingido` (organização, canal);
  - `sms.bloqueado_antiabuso`;
  - `job.started`/`completed`/`retry`/`failed` (nome, fila, organização, duração, tentativa, correlation id — COMM-005);
  - `scheduler.rotina.executada`/`atrasada`.

  Todos com `request_id` ou correlation id, sem conteúdo, código, token, e-mail ou telefone em claro (`OBS-002`).
- **Métricas a expor:**
  - idade do job mais antigo e tamanho por fila (`codigos` acima de tudo) e jobs por minuto;
  - envios por canal × estado × organização;
  - falhas por canal × provedor × código de erro;
  - tempo até `entregue` por canal;
  - taxa de entrega e de bounce/reclamação;
  - suprimidos por motivo;
  - uso vs limite por organização;
  - reenfileirados pelo varredor (esperado ≈ 0);
  - tokens de push desativados;
  - qualidade e nível de limite de cada número de WhatsApp.
- **Alertas a configurar:**
  - job na fila `codigos` há mais de 1 min;
  - qualquer fila parada há mais de 15 min;
  - taxa de falha definitiva acima do normal por canal ou provedor numa janela de 15 min;
  - rajada de `webhook.assinatura_invalida`;
  - número de WhatsApp de organização desconectado ou com qualidade baixa;
  - template rejeitado;
  - agendador sem execução há 5 min;
  - automáticas do dia não geradas até 10h no fuso da organização;
  - varredor reenfileirando de forma sustentada;
  - `tenant.context.missing` em qualquer job.

  Ferramentas e limiares no OIK-ADR-0010.
- **Cenário de incidente validado:** num domingo, às 9h, os parabéns da Igreja B não chegam por WhatsApp.
  - **O alerta:** dispara pela taxa de falha definitiva, com `envio.falhou canal=whatsapp provedor=meta organizacao=B`, e o código de erro da Meta indica problema de pagamento na conta da organização.
  - **O efeito:** `envio.fallback whatsapp→email` mostra que os parabéns seguiram por e-mail; a idade da fila `codigos` está normal, então nenhum 2FA atrasou.
  - **A solução:** o suporte avisa o administrador da Igreja B para regularizar o meio de pagamento no Meta Business, sem acionar desenvolvedor e sem ver o conteúdo de nenhuma mensagem.
  - **A conferência:** na segunda, as métricas por organização mostram a janela da falha e quantos envios foram para a reserva.

## Lacunas conhecidas

- Preços atuais da Meta por categoria no Brasil, condições do programa *Tech Provider* e uso de número já ativo no app WhatsApp Business (coexistência): confirmar na contratação e na Spec.
- Preço do SMS no Brasil (Twilio × provedor nacional): confirmar na contratação; o Adapter permite trocar.
- Limites numéricos dos planos (WhatsApp por mês) e tabela de preço estimado: decisão de negócio, antes da virada.
- Base legal dos avisos de serviço por WhatsApp e texto de opt-in das automáticas: orientação jurídica (`OPEN-013`) e finalidades de consentimento a levantar antes da Spec.
- Domínio de envio de e-mail e verificação de domínio na Meta: OIK-ADR-0011.
- Volume real de automáticas por dia não foi medido; a estimativa por aniversários é baixa e cabe na fila `database`.
- Ação do usuário: verificar a empresa Oikora no Meta Business e iniciar o cadastro de *Tech Provider* e a revisão do app; definir o número da plataforma para códigos; abrir contas Postmark, Twilio e Expo (token de acesso de push).
