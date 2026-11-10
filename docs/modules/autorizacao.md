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

## Interfaces / contratos expostos

- `PoliticaDePermissao::autoriza(...)` responde se um acesso pode agir sobre uma igreja.
- `Alcance::calcular(...)` devolve as igrejas alcançadas por permissão.
- `#[RequerPermissao(Permissao::...)]` declara a permissão exigida pela Action.
- `AtribuicoesDePermissao` é a porta de leitura das atribuições persistidas.

## Dependências

- **Depende de:** contratos públicos `CatalogoIgrejas` do módulo Organização; tabelas `acessos`, `igrejas` e `regionais`; Tenancy/RLS.
- **É usado por:** Policies e Actions dos módulos de negócio nas ondas seguintes.

## Decisões arquiteturais relevantes

| ADR | Como afeta este módulo |
|---|---|
| OIK-ADR-0002 | Papéis e atribuições pertencem a uma organização e são protegidos por RLS. |
| OIK-ADR-0004 | Define autorização por recurso, ação e escopo, com defesa em camadas. |
| OIK-ADR-0012 | A concessão temporária de permissões será construída sobre este catálogo em onda posterior. |

## Observabilidade — o que o suporte deve saber

- **Eventos logados:** nenhum diretamente nesta entrega; negações e mudanças de atribuição serão instrumentadas nas Actions correspondentes.
- **Métricas expostas:** nenhuma nesta entrega.
- **Erros comuns e o que significam:** uma decisão negativa significa permissão ausente ou igreja fora do alcance calculado; escopos regional/igreja sem identificador são inválidos.

## Pontos em aberto / riscos conhecidos

- ⚠️ Os papéis de sistema ainda serão semeados e administrados nas tasks de acesso e papéis.
- ⚠️ A Policy entregue cobre recursos de igreja; projeção de campos sensíveis e fluxos de aprovação entram nas ondas seguintes.

## Changelog

| Data | Mudança | Origem (task/spec/adr) |
|---|---|---|
| 2026-11-08 | Criação do catálogo, persistência de papéis/atribuições, alcance, Policy e guard arquitetural. | OIK-SPEC-0001 T-13 |
| 2026-11-10 | Gestão de papéis e atribuições com travas e auditoria; grupo de middleware `painel` (`ResolverAtor`) e Contract `AlcanceAtual` por requisição. | OIK-SPEC-0001 T-15 |
