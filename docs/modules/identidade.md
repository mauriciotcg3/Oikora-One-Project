# Módulo: Identidade

**Última atualização:** 2026-11-07
**Arquivos principais:** `api/app/Modules/Identidade/**`, `api/config/oikora.php`, `api/config/fortify.php`, `api/database/migrations/2026_11_07_000003_create_identidade_tables.php`

## Visão geral
Cuida da conta global da pessoa e da entrada no sistema. Hoje faz o login por e-mail ou celular com senha, a sessão de 30 dias, o logout, o limite de tentativas e o registro dos eventos de autenticação. O 2FA ainda não foi implementado: a conta com 2FA configurado fica sem sessão até a T-12.

## Regras de negócio ativas
| ID | Regra | Onde está implementada | Origem |
|---|---|---|---|
| — | Senha errada e conta inexistente têm a mesma resposta e gastam tempo equivalente. | `Application/Entrar.php`, `Presentation/AbrirSessao.php` | OIK-ADR-0003 §4 |
| — | Passar do limite por identificador ou por IP bloqueia o login, mesmo com a senha certa. O bloqueio dobra a cada violação, até o máximo configurado. | `Application/LimiteDeTentativas.php` | OIK-ADR-0003 §4; U-22 |
| — | Login com sucesso limpa o contador do identificador, mas não o do IP. | `Application/LimiteDeTentativas.php` | ⚠️ ORIGEM NÃO RASTREADA (escolha da T-10) |
| — | Conta com canal de 2FA não abre sessão só com a senha: recebe 403 `dois_fatores_necessario`, com canal e destino mascarado. | `Application/Entrar.php` | OIK-ADR-0003 §7 |
| — | Conta bloqueada recebe 403 `conta_bloqueada`, só depois de a senha conferir. | `Application/Entrar.php` | plan.md §7 |
| — | A sessão dura 30 dias e é renovada com o uso. | `api/config/session.php` | OIK-ADR-0003 §9; U-30 |
| — | Senha nova exige no mínimo 8 caracteres e não pode estar vazada. A regra está definida, mas nenhuma rota define senha ainda. | `IdentidadeServiceProvider.php` | OIK-ADR-0003 §4; U-29 |
| — | Uma conta tem no máximo um acesso ativo por organização, e uma Pessoa também. | índices únicos parciais de `acessos` | OIK-ADR-0003 §2 |

## Interfaces / contratos expostos
| Rota | Respostas |
|---|---|
| `POST /api/v1/auth/login` (`identificador`, `senha`) | 204 entrou; 422 dados inválidos; 403 `dois_fatores_necessario` ou `conta_bloqueada`; 429 `muitas_tentativas` com `Retry-After` |
| `POST /api/v1/auth/logout` | 204 saiu; 401 sem sessão |

Funções do banco para uso antes do contexto: `identidade_localizar_conta(texto)` e `identidade_identificador_em_uso(texto)`.

## Dependências
- **Depende de:** `Shared/Tenancy`, `Shared/Observabilidade`, Laravel Fortify, `giggsey/libphonenumber-for-php`.
- **É usado por:** feature `web/src/features/Identidade`.

## Decisões arquiteturais relevantes
| ADR | Como afeta este módulo |
|---|---|
| OIK-ADR-0003 | Conta global, RLS de identidade, via estreita antes do contexto, sessão por cookie. |
| OIK-ADR-0002 | `acessos` usa a RLS por organização; `contas` tem política própria, sem `FORCE`. |
| OIK-ADR-0005 | `eventos_autenticacao` é só inclusão para `oikora_app`. |

## Observabilidade — o que o suporte deve saber
- **Eventos logados** em `eventos_autenticacao`, com identificador e IP em HMAC: `auth.login.sucesso`, `auth.login.falha`, `auth.login.limite`, `auth.login.conta_bloqueada`, `auth.login.dois_fatores_pendente`.
- **Métricas expostas:** nenhuma ainda (T-06).
- **Erros comuns:** todo erro traz `request_id` no corpo e `X-Request-Id` no cabeçalho, que aparece também no evento. 419 é token CSRF ausente ou vencido.

## Pontos em aberto / riscos conhecidos
- ⚠️ Os números do limite de tentativas são provisórios (U-22).
- ⚠️ Conta com 2FA não consegue entrar até a T-11/T-12.
- ⚠️ `acessos.pessoa_id` está sem FK até a T-09.
- ⚠️ Atrás de proxy, o limite por IP vê o IP do proxy até a configuração de proxies confiáveis.
- ⚠️ A T-10 previa 200 no login; o código responde 204, conforme o contrato do front.
- ⚠️ Logout não grava evento em `eventos_autenticacao`.

## Changelog
| Data | Mudança | Origem (task/spec/adr) |
|---|---|---|
| 2026-11-07 | Criação inicial do documento, com o login por senha. | OIK-SPEC-0001 T-10 (fatia do login) |
