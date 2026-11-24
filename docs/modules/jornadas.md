# Módulo: Jornadas

**Última atualização:** 2026-11-23
**Arquivos principais:** `api/app/Modules/Jornadas/**`; `web/src/shared/ui/JornadaGuiada/**`

## Visão geral

Mantém rascunhos temporários de jornadas guiadas para a conta na organização ativa. A primeira jornada catalogada é `conectar_whatsapp`; a persistência é opt-in no componente e expira após 30 dias.

## Regras de negócio ativas

| ID | Regra | Onde está implementada | Origem |
|---|---|---|---|
| RN-01 | Existe no máximo um rascunho por organização, conta e tipo de jornada. | migration `rascunhos_jornada` | OIK-ADR-0016; T-57 |
| RN-02 | Leitura, gravação e descarte exigem acesso à jornada na organização ativa; RLS também isola a organização. | `RascunhosJornada` | OIK-ADR-0016; T-57 |
| RN-03 | A gravação usa versão otimista; versão obsoleta recebe 409 e o cliente recarrega o servidor sem mesclar. | `RascunhosJornada`; `criarPersistenciaJornada` | OIK-ADR-0016; T-57 |
| RN-04 | Credenciais, tokens, senhas e campos fora do catálogo são recusados; `numero_telefone` é cifrado. | `TipoJornada`; `RascunhosController` | OIK-ADR-0016; T-57 |
| RN-05 | Concluir ou descartar remove o rascunho; rascunhos sem atividade expiram em 30 dias. | `JornadaGuiada`; `ExpirarRascunhosJornada` | OIK-ADR-0016; T-57 |

## Interfaces / contratos expostos

- `GET /api/v1/painel/rascunhos/{tipo}` retorna o rascunho da conta atual ou `data: null`.
- `PUT /api/v1/painel/rascunhos/{tipo}` recebe `etapa`, `dados` e `versao`; devolve a nova versão ou 409 `rascunho_desatualizado`.
- `DELETE /api/v1/painel/rascunhos/{tipo}` descarta o rascunho.
- `criarPersistenciaJornada(tipo)` integra a API à `JornadaGuiada` e retoma etapa e campos após recarga.
- `jornadas:expirar-rascunhos` distribui um job por organização.

## Dependências

- **Depende de:** Autorização (`AlcanceAtual` e `avisos.whatsapp.gerir`), Tenancy/RLS, cifra de campos e PostgreSQL.
- **É usado por:** jornadas guiadas do painel, inicialmente a conexão de WhatsApp.

## Decisões arquiteturais relevantes

| ADR | Como afeta este módulo |
|---|---|
| OIK-ADR-0016 | Define dono, versão otimista, catálogo de dados, cifra, expiração e descarte. |

## Observabilidade — o que o suporte deve saber

- Logs registram somente tipo, etapa, resultado e quantidade; nunca o conteúdo do rascunho.
- `rascunho_desatualizado` significa que outra aba/sessão salvou antes; a tela recarrega a versão do servidor.

## Pontos em aberto / riscos conhecidos

- Novos tipos de jornada precisam entrar no catálogo com campos públicos e sensíveis explícitos.

## Changelog

- 2026-11-23 — T-57: persistência versionada, cifra, expiração e integração com `JornadaGuiada`.
