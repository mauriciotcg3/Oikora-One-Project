# Módulo: Autorização

**Última atualização:** 2026-11-08
**Arquivos principais:** `api/app/Modules/Autorizacao/**`

## Visão geral

Define o catálogo fechado de permissões e calcula, a cada chamada, em quais igrejas um acesso pode executar cada ação. Papéis recebem permissões e são atribuídos a um acesso nos escopos organização, regional ou igreja.

## Regras de negócio ativas

| ID | Regra | Onde está implementada | Origem |
|---|---|---|---|
| RN-01 | Somente valores do enum `Permissao` podem ser persistidos em um papel. | `Permissao` e constraint `papel_permissoes_catalogo` | OIK-SPEC-0001 T-13; OIK-ADR-0004 |
| RN-02 | Escopo de organização alcança todas as igrejas; regional, as igrejas da regional; igreja, apenas ela. | `Alcance::calcular` | OIK-SPEC-0001 T-13 |
| RN-03 | Atribuições acumulam alcance e são recalculadas sem cache a cada invocação. | `Alcance` e `AtribuicoesDePermissaoPostgres` | OIK-SPEC-0001 T-13 |
| RN-04 | A Policy permite recurso de igreja somente quando a igreja está no alcance da permissão requerida. | `PoliticaDePermissao::autoriza` | OIK-SPEC-0001 T-13; OIK-ADR-0004 |
| RN-05 | Toda Action deve declarar sua exigência por `#[RequerPermissao]`. | guard em `DeclaracoesDeSegurancaTest` | OIK-SPEC-0001 T-13 |
| RN-17 | Papéis prontos e criados são editáveis com permissões do catálogo. Sempre existe ao menos um acesso ativo com o papel Administrador no escopo da organização, e ele não perde `papeis.gerir`/`acessos.gerir` (422 + `authz.last_admin_blocked`). Ninguém concede permissão ou escopo que não tem (403 + `authz.escalation_blocked`). | `EditarPapelAction`, `RemoverAtribuicaoAction`, `AntiEscalada` | OIK-SPEC-0001 T-15; OIK-ADR-0004 §3 |
| RN-17.1 | Gestão de papéis e acessos exige a permissão no escopo da organização. Mudanças guardam na auditoria as permissões e o escopo antes e depois. | `Governanca`; Actions de papéis | OIK-SPEC-0001 T-15; OIK-ADR-0005 §5 |
| RN-24 | Qualquer acesso ativo pede permissões temporárias (escopo, justificativa, 1 a 72 h); o pedido não dá capacidade. Governança (`papeis.gerir`, `acessos.gerir`), exportação e a própria aprovação não são solicitáveis (422 `permissao_nao_solicitavel`; duração fora da faixa, 422 `duracao_concessao_invalida`). Sensível exige `categoria_sensivel` igual à da permissão e aparece na resposta e na auditoria. | `SolicitarPermissaoAction`; `Permissao::solicitavel`/`categoriaSensivel` | OIK-SPEC-0001 T-47; OIK-ADR-0012 §2, §5, §6; CA-31 |
| RN-24.1 | Só administrador (papel de sistema) com `permissoes.solicitacoes.aprovar` decide; o pedido é aprovado inteiro ou recusado com justificativa. Autoaprovação → 403 `autoaprovacao_negada`; permissão ou escopo fora do alcance de quem decide → 403 `escalada_bloqueada` + `authz.escalation_blocked`. A linha fica travada na decisão; decisão sobre pedido já decidido ou com `versao` antiga → 409 `solicitacao_permissao_alterada`; o banco garante uma concessão por pedido. | `AprovarSolicitacaoAction`, `RecusarSolicitacaoAction`, `Decisoes` | OIK-SPEC-0001 T-47; OIK-ADR-0012 §4, §8, §9 |
| RN-24.2 | A concessão vigente (`inicia_em` ≤ agora < `expira_em`, sem revogação) entra no alcance como uma atribuição; vencida ou revogada deixa de valer na leitura seguinte, pelo relógio do banco, sem job nem cache. Concessão sozinha não satisfaz a exigência de papel do painel. Quem pediu cancela o próprio pedido pendente; o administrador revoga concessão vigente. | `AtribuicoesDePermissaoPostgres::doAcesso`; `CancelarSolicitacaoAction`; `RevogarConcessaoAction` | OIK-SPEC-0001 T-47; OIK-ADR-0012 §7, §10 |

## Interfaces / contratos expostos

- `PoliticaDePermissao::autoriza(...)` responde se um acesso pode agir sobre uma igreja.
- `Alcance::calcular(...)` devolve as igrejas alcançadas por permissão (atribuições + concessões vigentes).
- `#[RequerPermissao(Permissao::...)]` declara a permissão exigida pela Action; `#[RequerPermissao(null)]` declara que basta o acesso ativo.
- `AtribuicoesDePermissao` é a porta de leitura das permissões efetivas (`doAcesso`) e da existência de papel (`temPapel`).
- Contract `PapeisDaConta` (para Identidade): `administrador(conta)` (RN-16) e `capacidades(organização, acesso)` no formato do `/me` (`permissao`, `escopo_tipo`, `escopo_ids`).
- **Painel:** `GET|POST /solicitacoes-permissao` (`visao=proprias|aprovaveis`), `POST /solicitacoes-permissao/{id}/{cancelar|aprovar|recusar}` (`versao` opcional; `justificativa` na recusa), `POST /concessoes-permissao/{id}/revogar` (`motivo` opcional; 204; vencida ou revogada → 409 `concessao_encerrada`).

## Dependências

- **Depende de:** contratos públicos `CatalogoIgrejas` do módulo Organização; tabelas `acessos`, `igrejas` e `regionais`; Tenancy/RLS.
- **É usado por:** Policies e Actions dos módulos de negócio nas ondas seguintes.

## Decisões arquiteturais relevantes

| ADR | Como afeta este módulo |
|---|---|
| OIK-ADR-0002 | Papéis e atribuições pertencem a uma organização e são protegidos por RLS. |
| OIK-ADR-0004 | Define autorização por recurso, ação e escopo, com defesa em camadas. |
| OIK-ADR-0012 | Concessão temporária: `solicitacoes_permissao`, `solicitacao_permissoes`, `concessoes_permissao`, `concessao_permissoes`, sob RLS, com FKs compostas e checks de duração (≤ 72 h) e de permissão solicitável. |

## Observabilidade — o que o suporte deve saber

- **Eventos logados:** `authz.permission_requested`, `authz.permission_request_cancelled`, `authz.permission_request_approved`, `authz.permission_request_rejected`, `authz.permission_grant_revoked` (id do pedido ou da concessão e estado; nunca justificativa); `authz.denied` nas negações.
- **Auditoria:** os mesmos eventos, com permissões, escopos (`igreja:<id>`), categorias sensíveis, duração, concessão e vencimento; `authz.escalation_blocked` com `motivo_codigo: teto_do_aprovador`.
- **Métricas expostas:** nenhuma nesta entrega.
- **Erros comuns e o que significam:** uma decisão negativa significa permissão ausente ou igreja fora do alcance calculado; escopos regional/igreja sem identificador são inválidos.

## Pontos em aberto / riscos conhecidos

- ⚠️ Os papéis de sistema ainda serão semeados e administrados nas tasks de acesso e papéis.
- ⚠️ A Policy entregue cobre recursos de igreja; projeção de campos sensíveis e fluxos de aprovação entram nas ondas seguintes.
- ⚠️ A expiração da concessão não grava `authz.permission_grant_expired` (o evento existe no catálogo): falta a rotina agendada; e pedidos pendentes não passam sozinhos a `expirada`.
- ⚠️ O contrato (`contrato-permissoes.md`) cita `sem_permissao` para acesso negado; as rotas do painel respondem `authz.denied`, como as da T-15. Alinhar na T-48.
- ⚠️ Concessão vigente conta no teto da anti-escalada de quem a recebeu; como governança não é solicitável, ela não abre gestão de papéis.

## Changelog

| Data | Mudança | Origem (task/spec/adr) |
|---|---|---|
| 2026-11-23 | Contract `PapeisProntos` (Administrador e Secretaria do provisionamento) e `QuemPode::naOrganizacao` (sem ator, para jobs). | OIK-SPEC-0001 T-23, T-20 |
| 2026-11-08 | Criação do catálogo, persistência de papéis/atribuições, alcance, Policy e guard arquitetural. | OIK-SPEC-0001 T-13 |
| 2026-11-10 | Gestão de papéis e atribuições com travas e auditoria; grupo de middleware `painel` (`ResolverAtor`) e Contract `AlcanceAtual` por requisição. | OIK-SPEC-0001 T-15 |
| 2026-11-17 | Elevação (`elevacao.painel`) e exigência de papel no grupo `painel`; Contract `PapeisDaConta` (RN-16 e capacidades do `/me`). | OIK-SPEC-0001 T-16 |
| 2026-11-17 | Solicitações e concessões temporárias de permissão, integradas ao alcance. | OIK-SPEC-0001 T-47 |
