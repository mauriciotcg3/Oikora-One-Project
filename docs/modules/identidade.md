# Módulo: Identidade

**Última atualização:** 2026-11-07
**Arquivos principais:** `api/app/Modules/Identidade/**`, `api/config/oikora.php`, `api/config/fortify.php`, `api/database/migrations/2026_11_07_000003_create_identidade_tables.php`, `api/database/migrations/2026_11_07_000004_add_codigo_cifrado_to_codigos_verificacao.php`

## Visão geral
Cuida da conta global da pessoa e da entrada no sistema. Hoje faz o login por e-mail ou celular com senha, o segundo fator (código por mensagem, TOTP e códigos de recuperação), a recuperação e a troca de senha, a confirmação de senha de 15 minutos, a verificação de contato, a sessão de 30 dias, o logout, o limite de tentativas e o registro dos eventos de autenticação.

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
| — | Uma conta tem no máximo um acesso ativo por organização, e uma Pessoa também. | índices únicos parciais de `acessos` | OIK-ADR-0003 §2 |

## Interfaces / contratos expostos
| Rota | Respostas |
|---|---|
| `POST /api/v1/auth/login` (`identificador`, `senha`) | 204 entrou; 422 dados inválidos; 403 `dois_fatores_necessario`, `conta_bloqueada` ou `canal_indisponivel`; 429 `muitas_tentativas` com `Retry-After` |
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

Funções do banco para uso antes do contexto: `identidade_localizar_conta(texto)` e `identidade_identificador_em_uso(texto)`.

Contract publicado para Avisos: `Contracts/CodigosDeIdentidade` (`emitir`, `paraEnvio`, `registrarEnvio`).

## Dependências
- **Depende de:** `Shared/Tenancy`, `Shared/Observabilidade`, Laravel Fortify, `giggsey/libphonenumber-for-php`, Contract `EnvioCodigo` de Avisos.
- **É usado por:** feature `web/src/features/Identidade`; módulo Avisos, pelo Contract `CodigosDeIdentidade`.

## Decisões arquiteturais relevantes
| ADR | Como afeta este módulo |
|---|---|
| OIK-ADR-0003 | Conta global, RLS de identidade, via estreita antes do contexto, sessão por cookie. |
| OIK-ADR-0002 | `acessos` usa a RLS por organização; `contas` tem política própria, sem `FORCE`. |
| OIK-ADR-0005 | `eventos_autenticacao` é só inclusão para `oikora_app`. |

## Observabilidade — o que o suporte deve saber
- **Eventos logados** em `eventos_autenticacao`, com identificador e IP em HMAC: `auth.login.sucesso`, `auth.login.falha`, `auth.login.limite`, `auth.login.conta_bloqueada`, `auth.login.dois_fatores_pendente`, `auth.2fa.sucesso`, `auth.2fa.falha`, `auth.2fa.limite`, `auth.senha.recuperacao_pedida`, `auth.senha.recuperacao_limite`, `auth.senha.redefinida`, `auth.senha.trocada`, `auth.2fa.ativado`, `auth.2fa.desativado`, `auth.contato.verificado`, `auth.login.canal_indisponivel`. O canal de `auth.2fa.sucesso` pode ser `recuperacao`.
- **Métricas expostas:** nenhuma ainda (T-06).
- **Erros comuns:** todo erro traz `request_id` no corpo e `X-Request-Id` no cabeçalho, que aparece também no evento. 419 é token CSRF ausente ou vencido.

## Pontos em aberto / riscos conhecidos
- ⚠️ Os números do limite de tentativas, de reenvios e de pedidos de recuperação são provisórios (U-22).
- ⚠️ `ExigenciaDeDoisFatores` responde "não" para todos até a T-16 ligar o papel Administrador. Até lá, a regra das 24h e a proibição de desativar não valem em uso real, e um Administrador sem 2FA entra (CA-10).
- ⚠️ As rotas `/conta/*` não têm tela no web.
- ⚠️ Com o e-mail ou o celular como único contato, quem tem 2FA obrigatório fica sem entrar por até 24h depois de recuperar a senha (`canal_indisponivel`).
- ⚠️ A mensagem de erro da tela de TOTP fala em "Confira a mensagem", texto pensado para código por mensagem.
- ⚠️ `POST /auth/senha/redefinir` não tem tela no web: o token chega, mas não há onde usá-lo pelo navegador.
- ⚠️ O pedido de recuperação para conta existente faz mais escrita no banco do que para conta inexistente; a diferença de tempo é pequena, mas não é zero.
- ⚠️ `acessos.pessoa_id` está sem FK até a T-09.
- ⚠️ Atrás de proxy, o limite por IP vê o IP do proxy até a configuração de proxies confiáveis.
- ⚠️ A T-10 previa 200 no login; o código responde 204, conforme o contrato do front.
- ⚠️ Logout não grava evento em `eventos_autenticacao`.

## Changelog
| Data | Mudança | Origem (task/spec/adr) |
|---|---|---|
| 2026-11-07 | Criação inicial do documento, com o login por senha. | OIK-SPEC-0001 T-10 (fatia do login) |
| 2026-11-07 | 2FA por código e recuperação de senha, com a sessão aberta só no `confirmar`. | OIK-SPEC-0001 T-12 (recorte) |
| 2026-11-07 | TOTP, códigos de recuperação, regra das 24h, confirmação de senha, troca de senha e verificação de contato. | OIK-SPEC-0001 T-12 |
