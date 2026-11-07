# Módulo: Identidade

**Última atualização:** 2026-11-07
**Arquivos principais:** `api/app/Modules/Identidade/**`, `api/config/oikora.php`, `api/config/fortify.php`, `api/database/migrations/2026_11_07_000003_create_identidade_tables.php`, `api/database/migrations/2026_11_07_000004_add_codigo_cifrado_to_codigos_verificacao.php`

## Visão geral
Cuida da conta global da pessoa e da entrada no sistema. Hoje faz o login por e-mail ou celular com senha, o segundo fator por código, a recuperação de senha, a sessão de 30 dias, o logout, o limite de tentativas e o registro dos eventos de autenticação. TOTP, códigos de recuperação, verificação de contato e confirmação de senha de 15 minutos ainda não existem.

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
| — | Conta com TOTP não abre desafio nem cai para código por mensagem, para não enfraquecer o segundo fator. | `Application/Entrar.php`, `Presentation/AbrirSessao.php` | ⚠️ ORIGEM NÃO RASTREADA (escolha da T-12) |
| — | A recuperação só manda algo para identificador verificado, pelo canal desse identificador. A resposta é igual exista ou não a conta. | `Application/PedirRecuperacao.php` | OIK-ADR-0003 §4 e §8 |
| — | O token de recuperação vale 60 minutos e é de uso único. Redefinir a senha encerra todas as sessões da conta, não dispensa o 2FA e não desbloqueia a conta. | `Application/RedefinirSenha.php`, `Infrastructure/ContasPostgres.php` | OIK-ADR-0003 §8 |
| — | Conta bloqueada recebe 403 `conta_bloqueada`, só depois de a senha conferir. | `Application/Entrar.php` | plan.md §7 |
| — | A sessão dura 30 dias e é renovada com o uso. | `api/config/session.php` | OIK-ADR-0003 §9; U-30 |
| — | Senha nova exige no mínimo 8 caracteres e não pode estar vazada. | `IdentidadeServiceProvider.php`, `Presentation/RecuperacaoSenhaController.php` | OIK-ADR-0003 §4; U-29 |
| — | Uma conta tem no máximo um acesso ativo por organização, e uma Pessoa também. | índices únicos parciais de `acessos` | OIK-ADR-0003 §2 |

## Interfaces / contratos expostos
| Rota | Respostas |
|---|---|
| `POST /api/v1/auth/login` (`identificador`, `senha`) | 204 entrou; 422 dados inválidos; 403 `dois_fatores_necessario` ou `conta_bloqueada`; 429 `muitas_tentativas` com `Retry-After` |
| `POST /api/v1/auth/logout` | 204 saiu; 401 sem sessão |
| `POST /api/v1/auth/2fa/confirmar` (`codigo`) | 204 entrou; 401 `sem_desafio`; 403 `conta_bloqueada`; 422 código incorreto ou vencido; 429 `muitas_tentativas` |
| `POST /api/v1/auth/2fa/enviar` (`canal`: `email` ou `whatsapp`) | 200 com `canal` e `destino` mascarado; 401 `sem_desafio`; 403 `conta_bloqueada`; 422 canal sem contato; 429 `muitas_tentativas` |
| `POST /api/v1/auth/senha/esqueci` (`identificador`) | 204 sempre que aceito; 422 dados inválidos; 429 `muitas_tentativas` |
| `POST /api/v1/auth/senha/redefinir` (`token`, `senha`) | 204 redefinida; 422 token ou senha inválidos |

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
- **Eventos logados** em `eventos_autenticacao`, com identificador e IP em HMAC: `auth.login.sucesso`, `auth.login.falha`, `auth.login.limite`, `auth.login.conta_bloqueada`, `auth.login.dois_fatores_pendente`, `auth.2fa.sucesso`, `auth.2fa.falha`, `auth.2fa.limite`, `auth.senha.recuperacao_pedida`, `auth.senha.recuperacao_limite`, `auth.senha.redefinida`.
- **Métricas expostas:** nenhuma ainda (T-06).
- **Erros comuns:** todo erro traz `request_id` no corpo e `X-Request-Id` no cabeçalho, que aparece também no evento. 419 é token CSRF ausente ou vencido.

## Pontos em aberto / riscos conhecidos
- ⚠️ Os números do limite de tentativas, de reenvios e de pedidos de recuperação são provisórios (U-22).
- ⚠️ Conta com TOTP não consegue entrar até o TOTP existir.
- ⚠️ A tela de código oferece "Usar um código de recuperação", mas a API ainda recusa esse caminho.
- ⚠️ `POST /auth/senha/redefinir` não tem tela no web: o token chega, mas não há onde usá-lo pelo navegador.
- ⚠️ A regra das 24h depois de recuperar a senha falta: ela depende de saber quem tem 2FA obrigatório, o que vem do papel Administrador.
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
