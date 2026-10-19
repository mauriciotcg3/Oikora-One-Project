# ADR-0003: Identidade, autenticação e sessão (conta global + Sanctum cookie/token)

**Status:** Aceito — após confirmação do usuário (2026-10-19)
**Data:** 2026-10-19
**Contexto do projeto:** Oikora One — plataforma da denominação (3 igrejas, ~520 membros) que substitui o inChurch módulo a módulo, com plano de atender outras igrejas.
**Identificação:** Produto `OIK-ADR-0003` · Governança `ADR-0025`
**Fonte da verdade:** `oikora-one:docs/adr/0003-identidade-autenticacao-e-sessao.md`

## Contexto
O OIK-ADR-0001 fixou Laravel exposto só pela API `/api/v1`, consumida por duas entradas web em React/TS (painel; área do membro + formulários públicos) e por um app nas lojas (tecnologia no OIK-ADR-0007), e deixou para este ADR a autenticação web vs app (cookie vs token). O OIK-ADR-0002 fixou a organização como fronteira de isolamento, com RLS forçada e contexto resolvido por requisição, e deixou para este ADR se uma conta acessa mais de uma organização e quais tabelas de identidade ficam fora da RLS por organização. A norma de frontend exige ADR antes de qualquer persistência de sessão, token ou CSRF (`FE-SEC-002`).

O documento de produto `docs/product/0001-fundacao-e-pessoas.md` traz as regras que a identidade precisa garantir:

- **Conta ligada à Pessoa (BR-002, FLOW-002, FR-018/019):** qualquer pessoa cria conta na área do membro; a conta pertence a no máximo uma Pessoa, ligação confirmada pela secretaria, nunca deduzida por e-mail igual; conta sem Pessoa confirmada não vê ficha nenhuma.
- **Convite (BR-010, FR-002/003, FLOW-005):** acesso ao painel só por convite com expiração, revogável a qualquer momento; ninguém define a senha de outra pessoa.
- **2FA (BR-016):** obrigatória para o administrador da denominação, opcional para a secretaria.
- **Responsável (BR-024, FLOW-010):** pessoa sem e-mail e sem celular não tem conta; um familiar com conta, reconhecido pela secretaria, cuida da ficha dela. Logo, quem tem só celular pode ter conta.
- **Canais (NFR-005, FR-036):** área do membro como site e como app nas lojas; convites e avisos por e-mail, WhatsApp e aviso na plataforma.
- **Dados sensíveis (BR-007, NFR-002):** contatos, documentos, saúde e fé/conversão, sob LGPD.

A especificação de referência (`docs/analise/especificacao-tecnica-php.md` §1, §3.3, §5.4, §8) sugere Sanctum com token por dispositivo para o app, Fortify para 2FA TOTP de administradores, `users.pessoa_id` e convite por link assinado de 72h; é referência, não decisão.

Informado pelo usuário em 2026-10-19: uma pessoa em mais de uma organização cliente é **rara** e poderia ter contas separadas; a entrada é por **e-mail ou celular**; o **endereço de acesso é único** para todos os clientes (a organização não vem do endereço); 2FA por **código enviado por e-mail ou WhatsApp**; **painel com sessão curta e área do membro/app com sessão longa**; o suporte da plataforma entra numa organização **só com autorização** dela; **sem login social** (Google/Apple) no MVP. Equipe de 2–4 desenvolvedores + IA, disponibilidade em horário comercial e cultos, sem plantão 24h.

Termos usados aqui: **conta** é a credencial de login de uma pessoa; **acesso** é o vínculo de uma conta com uma organização (e, nela, com uma Pessoa); **operador da plataforma** é alguém da equipe Oikora. Papéis, permissões e escopo dentro da organização são do OIK-ADR-0004.

## Opções consideradas
Modelo de conta (com endereço único):
- **A. Conta global + acessos por organização** — uma identidade por pessoa na plataforma; cada organização guarda, sob RLS, o acesso da conta e a Pessoa vinculada; seletor de organização após o login quando houver mais de uma.
- **B. Conta por organização** — `contas.organizacao_id`, e-mail único por organização; o login no endereço único procura o identificador em todas as organizações e pergunta qual; senha e recuperação separadas por organização.
- *(Descartada: conta por organização com código da organização no login — contradiz o endereço único.)*

Mecanismo de autenticação:
- **M1. Laravel Sanctum: cookie de sessão na web + token por dispositivo no app** — sessão no servidor com cookie `HttpOnly`/`Secure` e CSRF para o painel e a área do membro; token de acesso por dispositivo no armazenamento seguro do app.
- **M2. Bearer token em tudo** — inclusive na web, com o token guardado no navegador.
- **M3. OAuth2/OIDC** — Laravel Passport ou provedor externo (Keycloak, Auth0 etc.), access + refresh token, PKCE no app.

## Comparação

Modelo de conta:

| Pilar | A. Conta global + acessos | B. Conta por organização |
|---|---|---|
| Desempenho | Ótimo — login por índice único global do identificador | Bom — o login procura o identificador em todas as organizações |
| Simplicidade de manutenção | Bom — um login, uma recuperação, um app; o caso raro de várias organizações não custa nada a mais | Ruim — mesmo e-mail com várias senhas; recuperação e "qual organização?" antes do login; o app guarda várias contas |
| Boas práticas | Bom — o vínculo conta↔Pessoa (BR-002) fica por organização e sob RLS; a credencial pertence à pessoa, não à igreja | Aceitável — parece isolar mais, mas o login exige leitura fora da RLS do mesmo jeito, e o ganho some |
| Escalabilidade | Ótimo — não cresce com o número de organizações; instância dedicada (OIK-ADR-0002) leva só os acessos daquela organização | Bom — mover um cliente exige tratar identificadores repetidos |
| Observabilidade / logs | Ótimo — um `conta_id` por pessoa nos eventos de autenticação | Aceitável — a mesma pessoa aparece como várias contas |

Mecanismo de autenticação:

| Pilar | M1. Cookie web + token app | M2. Token em tudo | M3. OAuth2/OIDC |
|---|---|---|---|
| Desempenho | Ótimo — sessão e token consultados localmente, sem rede extra | Ótimo | Bom — validação de JWT ou introspecção, mais refresh |
| Simplicidade de manutenção | Ótimo — nativo do Laravel, documentado para SPA + mobile | Bom — um mecanismo, mas refresh e armazenamento ficam por conta do projeto | Ruim — servidor de autorização, clients, escopos e rotação de chaves para 2–4 devs, ou custo e dependência de terceiro |
| Boas práticas | Ótimo — na web nenhum token fica acessível a JavaScript (`FE-SEC-002`); no app, Keychain/Keystore | Ruim — token no `localStorage` exposto a XSS num sistema com dados LGPD sensíveis | Ótimo — padrão de mercado, mas voltado a terceiros e SSO, que o produto não tem |
| Escalabilidade | Bom — exige painel, área do membro e API sob o mesmo domínio-pai | Ótimo — sem restrição de domínio | Ótimo |
| Observabilidade / logs | Bom — sessões e tokens listáveis e revogáveis por conta e dispositivo | Bom | Bom — JWT não revoga antes de expirar sem lista de bloqueio |

## Decisão
Adotar **A (conta global + acessos por organização)** e **M1 (Sanctum: cookie de sessão na web, token por dispositivo no app)**, com as regras abaixo.

1. **Conta global.** Tabela `contas` com e-mail e/ou celular (E.164), cada um verificado e único na plataforma; hash de senha; configuração de 2FA; estado (ativa, bloqueada). A conta pertence à pessoa: só ela altera identificador, senha e 2FA. Sem login social no MVP (novo ADR se entrar; na App Store exige também "Entrar com Apple").
2. **Acessos por organização (sob RLS).** `acessos` (conta × organização, Pessoa vinculada, estado, origem: convite ou pedido de vínculo) tem `organizacao_id` e segue a RLS do OIK-ADR-0002, assim como `convites`, `pedidos_vinculo` e `autorizacoes_suporte`. BR-002 vale por organização: em cada organização a conta liga no máximo uma Pessoa, e cada Pessoa no máximo uma conta. Papéis e escopos (OIK-ADR-0004) se penduram no acesso.
3. **Tabelas de identidade fora da RLS por organização:** `contas`, sessões web, tokens do app (`personal_access_tokens`), códigos de verificação/recuperação e `operadores_plataforma`. `contas` tem uma **RLS de identidade** própria: no contexto de uma requisição, só é visível a própria conta ou contas com acesso na organização do contexto. Login, recuperação e verificação de unicidade no cadastro, que ocorrem antes do contexto, usam uma via estreita e dedicada (função com retorno mínimo), nunca consulta livre. A Spec fecha a lista nominal e o teste de arquitetura do OIK-ADR-0002 passa a conhecê-la.
4. **Entrada:** e-mail ou celular + senha. O celular é verificado por código via WhatsApp/SMS (canal no OIK-ADR-0008). A senha é definida só pelo dono, com mínimo de 8 caracteres e recusa de senhas vazadas. Limite de tentativas por identificador e por IP, com bloqueio progressivo; mensagens não revelam se a conta existe.
5. **Convite ao painel (BR-010, FLOW-005):** link de uso único com token aleatório guardado em hash, válido por 72h, revogável; enviado ao contato da Pessoa convidada. Quem já tem conta faz login para aceitar; quem não tem cria a conta e define a própria senha. Aceitar cria o acesso ligado àquela Pessoa — o vínculo vem do convite emitido pela organização, não de e-mail igual. Papel que exige 2FA obriga cadastrar o 2FA antes do primeiro acesso ao painel.
6. **Auto-cadastro (FR-018/019, FLOW-002):** a conta nasce sem acesso a organização nenhuma; a pessoa escolhe a igreja (link/QR da igreja ou busca) e gera um pedido de vínculo, com a organização derivada da igreja, nunca de parâmetro livre. A secretaria liga a uma Pessoa existente, cria a Pessoa ou recusa; até lá a conta não vê ficha nenhuma. Pessoa sem e-mail e sem celular não tem conta; o responsável (BR-024) age pela própria conta, e a regra de acesso à ficha dependente é do OIK-ADR-0004.
7. **2FA (BR-016):** código de 6 dígitos, válido por 10 min e de uso único, enviado por e-mail ou WhatsApp à escolha da pessoa; app autenticador (TOTP) disponível como opção e recomendado ao administrador; 10 códigos de recuperação de uso único gerados no cadastro do 2FA. Exigido a cada elevação para o painel quando o papel o obriga (administrador da denominação) ou quando a pessoa o ativou. Para quem tem 2FA obrigatório, o código não vai ao mesmo canal usado numa recuperação de senha nas últimas 24h. Operadores da plataforma usam TOTP obrigatório.
8. **Recuperação de senha:** link ou código de uso único, válido por 60 min, para um identificador verificado. Redefinir senha encerra todas as sessões e tokens da conta e não dispensa o 2FA no próximo acesso ao painel.
9. **Web (painel e área do membro):** Sanctum em modo SPA — sessão no servidor, cookie `HttpOnly`, `Secure`, `SameSite=Lax`, proteção CSRF; nenhum token acessível a JavaScript. Área do membro: sessão de 30 dias renovada com o uso. Painel: exige uma **elevação** própria (senha + 2FA quando aplicável) que expira após 2h sem uso ou 12h no total, sem derrubar a sessão de membro; rotas do painel conferem a elevação. Painel, área do membro e API ficam sob o mesmo domínio-pai (restrição registrada para o OIK-ADR-0011). Armazenamento da sessão (banco ou Redis) fica na Spec/OIK-ADR-0011, desde que permita listar e encerrar sessões por conta.
10. **App:** token Sanctum por dispositivo, guardado no Keychain/Keystore, expira após 90 dias sem uso; o token do app **não dá acesso a rotas do painel** no MVP (painel só na web). Abilities do token não substituem permissões — autorização é sempre no servidor (OIK-ADR-0004).
11. **Reautenticação e revogação:** alterar e-mail, celular, senha ou 2FA exige confirmar a senha nos últimos 15 min. O acesso à organização é conferido a cada requisição, então revogar um acesso ou convite vale na hora. Bloquear a conta ou trocar/redefinir a senha encerra todas as sessões e tokens. A pessoa vê e encerra os próprios dispositivos e sessões.
12. **Organização do contexto (OIK-ADR-0002):** com um único acesso ativo, a organização é automática; com mais de um, a pessoa escolhe num seletor após o login. A organização ativa fica **guardada no servidor** (na sessão web ou no registro do token do app) e, a cada requisição, é revalidada contra os acessos ativos da conta antes de gravar o contexto da RLS; nunca vem de parâmetro livre. Conta sem acesso ativo só chega às rotas da própria conta e de pedido de vínculo.
13. **Suporte da plataforma:** operadores têm contas e login próprios (`operadores_plataforma`, guard separado). Só entram numa organização com **autorização temporária** criada pelo administrador dela (operador, escopo e prazo); rodam com o papel da aplicação sob RLS, nunca com `BYPASSRLS`; cada ação é auditada com o operador identificado (OIK-ADR-0005) e a tela mostra aviso de acesso de suporte. O papel de plataforma com `BYPASSRLS` continua restrito a comandos administrativos (OIK-ADR-0002).
14. **Pacotes:** Laravel Sanctum; Laravel Fortify em modo headless para os fluxos de senha, confirmação e TOTP, com o desafio de código por mensagem implementado no módulo de identidade. Versões na Spec.
15. **Fora deste ADR:** papéis, permissões, escopo e acesso do responsável à ficha dependente (OIK-ADR-0004); auditoria e retenção (OIK-ADR-0005); telas e armazenamento no front (OIK-ADR-0006); tecnologia do app (OIK-ADR-0007); provedores de e-mail/WhatsApp/SMS e falhas de envio (OIK-ADR-0008); domínios, sessão em Redis e hospedagem (OIK-ADR-0011).

## Justificativa
- **Simplicidade de manutenção:** com endereço único, conta por organização (B) não evita a leitura fora da RLS no login e ainda multiplica senhas, recuperações e contas no app. A conta global resolve o caso comum e o raro com o mesmo fluxo. Sanctum é o mecanismo nativo do Laravel para SPA de primeira parte + mobile; OAuth2/OIDC (M3) traz um servidor de autorização que 2–4 desenvolvedores teriam de operar sem necessidade de terceiros ou SSO.
- **Boas práticas (LGPD):** o que é da organização — vínculo com a Pessoa, convites, pedidos, autorizações de suporte — fica sob a RLS do OIK-ADR-0002; a credencial, que é da pessoa, fica numa RLS de identidade e com leitura pré-login restrita. Na web nenhum token fica ao alcance de XSS (M2 descartado), e o painel, que mostra dados sensíveis das 3 igrejas, exige elevação curta com 2FA, enquanto o membro, que vê só a própria ficha, tem sessão longa.
- **Boas práticas (2FA):** o código por e-mail/WhatsApp foi a escolha do usuário; é mais fraco que TOTP porque depende do canal de mensagem. Mitigações: TOTP opcional e recomendado ao administrador, códigos de recuperação e canal do código diferente do canal de uma recuperação de senha recente.
- **Escalabilidade:** conta global e sessão no servidor não crescem com o número de organizações; a única restrição (mesmo domínio-pai) é compatível com o endereço único escolhido.
- **Desempenho:** login por índice único; contexto revalidado por uma consulta indexada por conta e organização; volume de ~520 membros torna o custo desprezível.
- **Observabilidade:** sessões e tokens por conta e dispositivo são listáveis e revogáveis, e cada etapa (login, 2FA, convite, recuperação, suporte) gera evento próprio.

## Consequências

**Positivas:**
- Uma pessoa, uma conta, um login — no site e no app; o caso raro de várias organizações é coberto pelo seletor.
- O vínculo conta↔Pessoa e a revogação de acesso ficam na organização, sob RLS, com efeito imediato.
- Nenhuma senha definida por terceiros (BR-010) e nenhum vínculo deduzido por e-mail (BR-002).
- Painel protegido por elevação curta e 2FA sem obrigar o membro a logar toda hora.
- Suporte da plataforma só entra com autorização da organização, sob RLS e auditado.

**Negativas / trade-offs aceitos:**
- `contas` precisa de uma RLS de identidade e de uma via estreita para login/recuperação, além da RLS por organização; mais um ponto a testar.
- A organização não controla a credencial: trocar o e-mail ou excluir a conta é ação da pessoa; uma exclusão LGPD numa organização não apaga a conta se houver acesso em outra (tratamento no OIK-ADR-0005).
- 2FA por mensagem depende do provedor de e-mail/WhatsApp: se ele cair, o administrador só entra por outro canal, TOTP ou código de recuperação.
- Cookie de sessão obriga painel, área do membro e API sob o mesmo domínio-pai e exige CSRF nas chamadas web.
- O app usa um mecanismo diferente da web (token), com expiração e revogação próprias para testar.
- Sem login social no MVP; incluir depois exige novo ADR.

## Observabilidade — o que precisa ser instrumentado
- **Eventos a logar:** `auth.login.succeeded`/`auth.login.failed` (motivo codificado, canal web/app, `conta_id` quando conhecida, identificador só em hash); `auth.lockout`; `auth.elevation.granted`/`auth.elevation.expired` (painel); `auth.2fa.challenge_sent`, `auth.2fa.delivery_failed` (canal, provedor, erro), `auth.2fa.failed`, `auth.2fa.recovery_code_used`; `auth.password_reset.requested`/`completed`; `auth.invite.sent`/`accepted`/`expired`/`revoked`; `auth.link_request.created`/`approved`/`rejected`; `auth.session.revoked` e `auth.token.created`/`revoked` (motivo: logout, troca de senha, bloqueio, revogação de acesso); `auth.context.selected`/`auth.context.denied` (acesso inexistente ou revogado); `auth.support_access.granted`/`used`/`expired`. Todos com `request_id`, sem senha, código, token ou cookie no log (`OBS-002`).
- **Métricas a expor:** logins com sucesso e falha por canal e motivo; bloqueios por hora; desafios 2FA enviados vs falhos por canal e provedor; tempo de entrega do código; convites enviados, aceitos e expirados; pedidos de vínculo pendentes por igreja e idade do mais antigo; sessões e tokens ativos; acessos de suporte ativos.
- **Alertas a configurar:** pico de `auth.login.failed`/`auth.lockout` acima do normal (ataque de credenciais); taxa de `auth.2fa.delivery_failed` acima do limiar por canal (administradores sem entrar); qualquer `auth.support_access.used` sem autorização vigente; `auth.context.denied` em volume anormal. Ferramentas e limiares no OIK-ADR-0010.
- **Cenário de incidente validado:** domingo às 8h o provedor de WhatsApp fica fora do ar e o administrador da denominação não recebe o código para entrar no painel antes do culto. O alerta de `auth.2fa.delivery_failed` dispara com `canal=whatsapp` e o erro do provedor; a tela oferece o e-mail, o TOTP ou um código de recuperação, e o administrador entra sem acionar desenvolvedor. Na segunda, o suporte vê pelas métricas de entrega a janela da falha e o provedor afetado, e confirma pelos eventos `auth.2fa.challenge_sent` que nenhum código foi aceito fora do esperado.

## Lacunas conhecidas
- Provedor e custo de WhatsApp/SMS para verificação de celular e 2FA, e o comportamento em falha de envio: OIK-ADR-0008.
- Domínios concretos (mesmo domínio-pai para painel, área do membro e API), armazenamento de sessão (banco ou Redis) e pooler: OIK-ADR-0011.
- Como o responsável (BR-024) age sobre a ficha dependente e o escopo de papéis dentro do acesso: OIK-ADR-0004.
- Destino da conta e dos acessos numa exclusão LGPD e retenção dos eventos de autenticação (`OPEN-013`): OIK-ADR-0005.
- Biometria/desbloqueio local no app e armazenamento exato do token: OIK-ADR-0007.
- Interface do operador da plataforma (onde ele entra, o que vê): não definida; o MVP só garante o mecanismo de autorização temporária.
- Política de senha além do mínimo (expiração periódica não é adotada) e prazos (72h, 10 min, 60 min, 2h/12h, 30/90 dias) podem ser ajustados por configuração na Spec sem novo ADR, desde que não fiquem mais permissivos.
