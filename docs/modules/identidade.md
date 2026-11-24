# Módulo: Identidade

**Última atualização:** 2026-11-20
**Arquivos principais:** `api/app/Modules/Identidade/**`, `api/config/oikora.php`, `api/config/fortify.php`, `api/database/migrations/2026_11_07_000003_create_identidade_tables.php`, `api/database/migrations/2026_11_07_000004_add_codigo_cifrado_to_codigos_verificacao.php`, `api/database/migrations/2026_11_20_000003_create_convites_table.php`

## Visão geral
Cuida da conta global da pessoa e da entrada no sistema. Hoje faz o login por e-mail ou celular com senha, o segundo fator (código por mensagem, TOTP e códigos de recuperação), a recuperação e a troca de senha, a confirmação de senha de 15 minutos, a verificação de contato, a sessão de 30 dias, o logout, o limite de tentativas, o registro dos eventos de autenticação, o convite ao painel (emitir, aceitar, revogar, expirar) e a revogação de acesso.

## Regras de negócio ativas
| ID | Regra | Onde está implementada | Origem |
|---|---|---|---|
| — | Senha errada e conta inexistente têm a mesma resposta e gastam tempo equivalente. | `Application/Entrar.php`, `Presentation/AbrirSessao.php` | OIK-ADR-0003 §4 |
| — | Passar do limite por identificador ou por IP bloqueia o login, mesmo com a senha certa. O bloqueio dobra a cada violação, até o máximo configurado. | `Application/LimiteDeTentativas.php` | OIK-ADR-0003 §4; U-22 |
| — | Login com sucesso limpa o contador do identificador, mas não o do IP. | `Application/LimiteDeTentativas.php` | ⚠️ ORIGEM NÃO RASTREADA (escolha da T-10) |
| — | Conta com canal de 2FA não abre sessão só com a senha: recebe 403 `dois_fatores_necessario`, com canal e destino mascarado, e o primeiro código já sai no login. A conta pendente fica na sessão, sem autenticar. | `Application/Entrar.php`, `Presentation/DesafioPendente.php` | OIK-ADR-0003 §7 |
| RN-16 | A sessão só abre no `confirmar`, com id de sessão novo. O código tem 6 dígitos, vale 10 minutos e é de uso único. | `Application/ConfirmarDoisFatores.php`, `Presentation/DoisFatoresController.php` | OIK-ADR-0003 §7 |
| — | Depois de 5 erros o código deixa de valer, mesmo que o certo venha depois. São até 4 reenvios por desafio, e o desafio vale 30 minutos. | `Application/ConfirmarDoisFatores.php`, `Presentation/DoisFatoresController.php` | ⚠️ ORIGEM NÃO RASTREADA (provisório, U-22) |
| — | Código novo invalida o anterior da mesma finalidade. | `Infrastructure/CodigosPostgres.php` | ⚠️ ORIGEM NÃO RASTREADA (escolha da T-12) |
| — | Conta com TOTP completa o desafio pelo aplicativo e não cai para código por mensagem, para não enfraquecer o segundo fator. | `Application/ConfirmarDoisFatores.php`, `Application/ReenviarCodigo.php` | ⚠️ ORIGEM NÃO RASTREADA (escolha da T-12) |
| RN-16 | O TOTP só vale depois de conferir um código do aplicativo. O mesmo código não vale duas vezes, e o segredo fica cifrado. | `Application/GerirDoisFatores.php`, `Infrastructure/TotpGoogle2fa.php` | OIK-ADR-0003 §7 |
| RN-16 | Ativar o 2FA gera 10 códigos de recuperação de uso único, guardados só em HMAC. O código aceita minúsculas e falta de hífen. | `Domain/CodigoDeRecuperacao.php`, `Application/GerirDoisFatores.php` | OIK-ADR-0003 §7 |
| — | Ativar por e-mail ou WhatsApp exige o contato verificado. | `Application/GerirDoisFatores.php` | ⚠️ ORIGEM NÃO RASTREADA (escolha da T-12) |
| RN-16 | Quem tem 2FA obrigatório não recebe o código pelo canal de uma recuperação de senha das últimas 24h: vai pelo outro canal, ou o login recusa com `canal_indisponivel`. Essa pessoa também não pode desativar o 2FA. | `Application/RegraDoCanal.php`, `Application/GerirDoisFatores.php` | OIK-ADR-0003 §7 |
| — | Ativar ou desativar o 2FA e trocar a senha exigem a senha confirmada nos últimos 15 minutos. | `Presentation/ExigirSenhaConfirmada.php` | OIK-ADR-0003 §11 |
| — | Trocar a senha encerra todas as sessões da conta, inclusive a atual. | `Application/SenhaDaConta.php`, `Presentation/ContaController.php` | OIK-ADR-0003 §11 |
| — | Confirmar o código marca `email_verificado_em` ou `celular_verificado_em`. O celular recebe pelo WhatsApp, com SMS de reserva. Contato já verificado não recebe código. | `Application/VerificarContato.php` | OIK-ADR-0003 §4 |
| — | A recuperação só manda algo para identificador verificado, pelo canal desse identificador. A resposta é igual exista ou não a conta. | `Application/PedirRecuperacao.php` | OIK-ADR-0003 §4 e §8 |
| — | O token de recuperação vale 60 minutos e é de uso único. Redefinir a senha encerra todas as sessões da conta, não dispensa o 2FA e não desbloqueia a conta. | `Application/RedefinirSenha.php`, `Infrastructure/ContasPostgres.php` | OIK-ADR-0003 §8 |
| — | Conta bloqueada recebe 403 `conta_bloqueada`, só depois de a senha conferir. | `Application/Entrar.php` | plan.md §7 |
| — | A sessão dura 30 dias e é renovada com o uso. | `api/config/session.php` | OIK-ADR-0003 §9; U-30 |
| — | Senha nova exige no mínimo 8 caracteres e não pode estar vazada. | `IdentidadeServiceProvider.php`, `Presentation/RecuperacaoSenhaController.php` | OIK-ADR-0003 §4; U-29 |
| RN-12 | Ninguém define a senha de outra pessoa: a senha só muda pela própria sessão (`PUT /conta/senha`, com senha confirmada) ou pelo token de recuperação enviado ao identificador verificado da própria conta. Não há rota de painel que grave senha alheia. No aceite do convite, quem não tem conta define a própria senha. | `Presentation/ContaController.php`, `Application/RedefinirSenha.php`, `Application/AceitarConvite.php` | OIK-ADR-0003 §5; RN-12 |
| — | Uma conta tem no máximo um acesso ativo por organização, e uma Pessoa também. | índices únicos parciais de `acessos` | OIK-ADR-0003 §2 |
| RN-02 | O acesso nasce só do convite emitido pela organização, nunca de e-mail igual. Quem aceita sem sessão ganha conta nova com os contatos da ficha; se um desses contatos já é de outra conta, o aceite responde 409 `entre_para_aceitar` e a pessoa entra na conta dela para aceitar. Pessoa ou conta que já tem acesso ativo na organização → 409. | `Application/AceitarConvite.php`, `Application/Actions/ConvidarAction.php` | OIK-ADR-0003 §2, §5; CA-02 |
| RN-12 | Convite ao painel: Pessoa + papel + escopo, com a anti-escalada da atribuição (`escalada_bloqueada`); só com `acessos.gerir` no escopo da organização; Pessoa sem e-mail e sem celular → 422; um convite válido por Pessoa (`convite_pendente`). Link de uso único com token aleatório de 48 caracteres, guardado só em hash SHA-256, válido por 72h; usado, revogado ou vencido → `convite_invalido`. O comando diário `convites:expirar` (03h30) marca `expirado`. Aceitar cria o acesso e a atribuição do convite. | `Application/Actions/ConvidarAction.php`, `Application/Actions/RevogarConviteAction.php`, `Application/AceitarConvite.php`, `Infrastructure/Console/ExpirarConvites.php` | OIK-ADR-0003 §5; P BR-010 |
| — | Revogar o acesso (`DELETE /painel/acessos/{id}`) vale na próxima requisição daquela conta (a organização é revalidada a cada pedido) e nunca tira o último Administrador ativo (`ultimo_administrador`, com `authz.last_admin_blocked`). | `Application/Actions/RevogarAcessoAction.php` | OIK-ADR-0003 §11; OIK-ADR-0004 §3; CA-09 |
| RN-16 | 2FA obrigatório para quem tem o papel de sistema Administrador em alguma organização (acesso ativo); opcional para os demais. Administrador sem 2FA cadastrado não eleva (`dois_fatores_cadastro_necessario`); quem tem 2FA, obrigatório ou não, precisa do código para elevar. | `Infrastructure/DoisFatoresPeloPapel.php`, `Application/ElevarPainel.php` | OIK-ADR-0003 §7; CA-10 |
| — | A organização ativa fica na sessão e é revalidada a cada requisição contra os acessos ativos. Com um único acesso, é escolhida sozinha; com mais de um, a pessoa escolhe por `PUT /me/organizacao-ativa`. Acesso revogado depois do login: a próxima requisição responde 403 `acesso_revogado`, sem gravar contexto. | `Infrastructure/ResolvedorOrganizacaoSessao.php`, `Application/MinhaConta.php` | OIK-ADR-0003 §12; CA-09 |
| — | Rotas `/api/v1/painel/*` exigem elevação viva: expira com 2h sem uso ou 12h no total (`elevacao_necessaria`). O login com `entrada: painel` já abre a elevação (senha + 2FA quando a conta o tem). A elevação regenera o id da sessão. | `Presentation/ElevacaoPainel.php`, `Presentation/ElevacaoNoLogin.php`, `Application/SessaoDoPainel.php` | OIK-ADR-0003 §9; U-32 |

## Interfaces / contratos expostos
| Rota | Respostas |
|---|---|
| `POST /api/v1/auth/login` (`identificador`, `senha`, `entrada` opcional: `painel` ou `membro`) | 204 entrou (com `painel`, já elevado quando cabe); 422 dados inválidos; 403 `dois_fatores_necessario`, `conta_bloqueada` ou `canal_indisponivel`; 429 `muitas_tentativas` com `Retry-After` |
| `POST /api/v1/auth/logout` | 204 saiu; 401 sem sessão |
| `POST /api/v1/auth/2fa/confirmar` (`codigo` ou `codigo_recuperacao`) | 204 entrou; 401 `sem_desafio`; 403 `conta_bloqueada`; 422 código incorreto ou vencido; 429 `muitas_tentativas` |
| `POST /api/v1/auth/2fa/enviar` (`canal`: `email` ou `whatsapp`) | 200 com `canal` e `destino` mascarado; 401 `sem_desafio`; 403 `conta_bloqueada`; 422 canal sem contato; 429 `muitas_tentativas` |
| `POST /api/v1/auth/senha/esqueci` (`identificador`) | 204 sempre que aceito; 422 dados inválidos; 429 `muitas_tentativas` |
| `POST /api/v1/auth/senha/redefinir` (`token`, `senha`) | 204 redefinida; 422 token ou senha inválidos |
| `POST /api/v1/conta/confirmar-senha` (`senha`) | 204 confirmada; 401 sem sessão; 422 senha errada; 429 `muitas_tentativas` |
| `GET /api/v1/conta/2fa` | 200 com `canal` e `recuperacao_restantes`; 401 sem sessão |
| `POST /api/v1/conta/2fa` (`canal`, `codigo` só no TOTP) | 200 com `codigos_recuperacao`, ou com `segredo` e `otpauth_url` no primeiro passo do TOTP; 401; 403 `senha_nao_confirmada`; 422 |
| `DELETE /api/v1/conta/2fa` | 204 desativado; 401; 403 `senha_nao_confirmada` ou `dois_fatores_obrigatorio` |
| `PUT /api/v1/conta/senha` (`senha`) | 204 trocada, sem sessão; 401; 403 `senha_nao_confirmada`; 422 |
| `POST /api/v1/conta/verificacao/{email\|celular}` | 204 aceito; 401; 422 sem esse contato; 429 `muitas_tentativas` |
| `POST /api/v1/conta/verificacao/{email\|celular}/confirmar` (`codigo`) | 204 verificado; 401; 422 código incorreto; 429 `muitas_tentativas` |
| `GET /api/v1/me` | 200 com `conta`, `organizacao_ativa` (ou null), `organizacoes`, `capacidades` (`permissao`, `organizacao`), `elevacao` (`ativa`, `expira_em`) e `dois_fatores` (`canal`, `obrigatorio`); 401 |
| `PUT /api/v1/me/organizacao-ativa` (`organizacao`: uuid) | 204 escolhida; 401; 403 `organizacao_sem_acesso`; 422 |
| `POST /api/v1/painel/elevacao` (`senha`, `codigo` ou `codigo_recuperacao`) | 204 elevado, com sessão nova; 401; 403 `dois_fatores_necessario` (com `canal` e `destino`), `dois_fatores_cadastro_necessario`, `conta_bloqueada`, `canal_indisponivel` ou erro de organização; 422 senha ou código; 429 `muitas_tentativas` |
| `POST /api/v1/painel/convites` (`pessoa`, `papel`, `escopo_tipo`, `escopo` exceto em `organizacao`) | 201 com `uuid`, `estado` e `expira_em`; 403 `authz.denied` ou `escalada_bloqueada`; 422 por campo (`pessoa_inexistente`, `pessoa_sem_contato`, `papel_inexistente`, `escopo_inexistente`) ou `pessoa_ja_tem_acesso`, `convite_pendente` |
| `DELETE /api/v1/painel/convites/{id}` | 204 revogado; 403; 404 `convite_inexistente`; 422 `convite_nao_pendente` |
| `DELETE /api/v1/painel/acessos/{id}` | 204 revogado; 403; 404 `acesso_inexistente`; 422 `acesso_revogado` ou `ultimo_administrador` |
| `GET /api/v1/convites/{token}` | 200 com `organizacao.nome`, `pessoa.nome`, `papel.nome` e `expira_em`; 404 `convite_invalido` |
| `POST /api/v1/convites/{token}/aceitar` (sem sessão: `senha` e `senha_confirmation`) | 201 com `acesso`, `conta_nova` e `organizacao`; 409 `entre_para_aceitar`, `conta_ja_tem_acesso` ou `pessoa_ja_tem_acesso`; 422 `convite_invalido` ou senha inválida; 429 |
| Rotas `/api/v1/painel/*` | 403 `elevacao_necessaria`, `organizacao_nao_escolhida`, `acesso_revogado`, `acesso_inexistente` ou `sem_atribuicao`; 409 `organizacao_trocada` quando `X-Organizacao-Esperada` diverge |

Funções do banco para uso antes do contexto: `identidade_localizar_conta(texto)`, `identidade_identificador_em_uso(texto)`, `identidade_acessos_ativos_da_conta(conta)` (organizações com acesso ativo), `identidade_organizacao_do_convite(hash)` e `identidade_criar_conta(email, celular, hash da senha)` (só o aceite usa). Autorização publica `autorizacao_conta_tem_papel_sistema(conta, sistema)` para a RN-16.

Contract publicado para Avisos: `Contracts/CodigosDeIdentidade` (`emitir`, `paraEnvio`, `registrarEnvio`). `Contracts/AcessosDaOrganizacao` ganhou `pessoas(ids)` (Pessoa de cada acesso, para o autor na linha do tempo) e `contasAtivas(ids)` (conta de cada acesso ativo, para avisos de serviço).

## Dependências
- **Depende de:** `Shared/Tenancy`, `Shared/Observabilidade`, Laravel Fortify, `giggsey/libphonenumber-for-php`, Contract `EnvioCodigo` de Avisos; Contracts `Avisos` (convite), `ContatosDaPessoa::paraConvite` (Pessoas) e `ConcessaoDeAcesso` (Autorização: anti-escalada, atribuição no aceite, trava do último Administrador).
- **É usado por:** feature `web/src/features/Identidade`; módulo Avisos, pelo Contract `CodigosDeIdentidade`.

## Decisões arquiteturais relevantes
| ADR | Como afeta este módulo |
|---|---|
| OIK-ADR-0003 | Conta global, RLS de identidade, via estreita antes do contexto, sessão por cookie. |
| OIK-ADR-0002 | `acessos` usa a RLS por organização; `contas` tem política própria, sem `FORCE`. |
| OIK-ADR-0005 | `eventos_autenticacao` é só inclusão para `oikora_app`. |

## Observabilidade — o que o suporte deve saber
- **Eventos logados** em `eventos_autenticacao`, com identificador e IP em HMAC: `auth.login.sucesso`, `auth.login.falha`, `auth.login.limite`, `auth.login.conta_bloqueada`, `auth.login.dois_fatores_pendente`, `auth.2fa.sucesso`, `auth.2fa.falha`, `auth.2fa.limite`, `auth.senha.recuperacao_pedida`, `auth.senha.recuperacao_limite`, `auth.senha.redefinida`, `auth.senha.trocada`, `auth.2fa.ativado`, `auth.2fa.desativado`, `auth.contato.verificado`, `auth.login.canal_indisponivel`, `auth.elevacao`, `auth.elevacao.falha`, `auth.elevacao.sem_dois_fatores`. O canal de `auth.2fa.sucesso` pode ser `recuperacao`.
- **Auditoria:** `auth.elevacao` também vai para a auditoria da organização ativa, com `via` (`login` ou `dialogo`) e `segundo_fator`. No login de quem tem mais de uma organização, a elevação fica só em `eventos_autenticacao`, porque ainda não há organização escolhida.
- **Métricas expostas:** nenhuma ainda (T-06).
- **Erros comuns:** todo erro traz `request_id` no corpo e `X-Request-Id` no cabeçalho, que aparece também no evento. 419 é token CSRF ausente ou vencido.

## Pontos em aberto / riscos conhecidos
- ⚠️ Os números do limite de tentativas, de reenvios e de pedidos de recuperação são provisórios (U-22).
- ⚠️ O cadastro do 2FA antes do primeiro acesso do Administrador depende das telas da T-38; até lá, o painel só responde `dois_fatores_cadastro_necessario`.
- ⚠️ A elevação continua valendo depois de `PUT /me/organizacao-ativa`: ela prova quem é a pessoa, não a organização.
- ⚠️ As rotas `/conta/*` não têm tela no web.
- ⚠️ Com o e-mail ou o celular como único contato, quem tem 2FA obrigatório fica sem entrar por até 24h depois de recuperar a senha (`canal_indisponivel`).
- ⚠️ A mensagem de erro da tela de TOTP fala em "Confira a mensagem", texto pensado para código por mensagem.
- ⚠️ `POST /auth/senha/redefinir` não tem tela no web: o token chega, mas não há onde usá-lo pelo navegador.
- ⚠️ O pedido de recuperação para conta existente faz mais escrita no banco do que para conta inexistente; a diferença de tempo é pequena, mas não é zero.
- ⚠️ Atrás de proxy, o limite por IP vê o IP do proxy até a configuração de proxies confiáveis.
- ⚠️ A T-10 previa 200 no login; o código responde 204, conforme o contrato do front.
- ⚠️ Logout não grava evento em `eventos_autenticacao`.
- ⚠️ O link do convite (com o token) fica em `avisos.destino` até o envio, porque a caixa de saída é assíncrona e reenvia pelo próximo canal; no próprio convite só fica o hash. A tabela está sob RLS e o token vale 72h e uma vez.
- ⚠️ O aceite sem sessão não verifica o contato nem abre sessão: a pessoa entra depois pelo login normal.
- ⚠️ `GET /painel/acessos` (lista de acessos e convites) ainda não existe; fica para a T-40 (telas).

## Changelog
| Data | Mudança | Origem (task/spec/adr) |
|---|---|---|
| 2026-11-23 | `GET /painel/acessos` (acessos, papéis e convites válidos, com `acessos.gerir` na organização); Contract `ConviteDoProvisionamento` (convite sem emissor); `AcessosDaOrganizacao::ativoDaPessoa|religarPessoa|revogarDaPessoa|pessoaTeveAcesso`. Telas de cadastro do 2FA, aceite de convite e seletor de organização. | OIK-SPEC-0001 T-23, T-32, T-35, T-38, T-40 |
| 2026-11-20 | Convite ao painel (emitir, ler, aceitar com ou sem conta, revogar, expirar) e revogação de acesso com a trava do último Administrador. | OIK-SPEC-0001 T-22 |
| 2026-11-17 | Organização ativa na sessão, `/me`, troca de organização, elevação do painel (2h/12h, entrada `painel`) e RN-16 pelo papel Administrador. | OIK-SPEC-0001 T-16 |
| 2026-11-13 | T-10 fechada: casos do login, limite, eventos, sessão, RLS de identidade e unicidade de `acessos` conferidos (27 testes); RN-12 registrada. | OIK-SPEC-0001 T-10 |
| 2026-11-08 | Integridade referencial de `acessos.pessoa_id` ligada ao cadastro de pessoas. | OIK-SPEC-0001 T-09 |
| 2026-11-07 | Criação inicial do documento, com o login por senha. | OIK-SPEC-0001 T-10 (fatia do login) |
| 2026-11-07 | 2FA por código e recuperação de senha, com a sessão aberta só no `confirmar`. | OIK-SPEC-0001 T-12 (recorte) |
| 2026-11-07 | TOTP, códigos de recuperação, regra das 24h, confirmação de senha, troca de senha e verificação de contato. | OIK-SPEC-0001 T-12 |
