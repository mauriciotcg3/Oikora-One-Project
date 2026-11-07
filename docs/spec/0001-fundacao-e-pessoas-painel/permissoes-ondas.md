# Permissões — execução por ondas

Este documento é o mapa de execução da frente de permissões. Ele complementa
as tasks T-13, T-15 e T-16 sem autorizar implementação fora das dependências
definidas na spec.

## Onda 1 — descoberta do OmniPlus

**Estado:** concluída em 2026-11-07.

- mapear tabelas, entidades, políticas, middleware, endpoints e comportamento
  da interface;
- separar núcleo reutilizável das regras específicas do produto de origem;
- manter a origem estritamente somente leitura;
- registrar evidências e riscos em
  `docs/modernizacao/omniplus-permissoes/README.md`.

## Onda 2 — decisão e contratos

**Estado:** concluída documentalmente em 2026-11-07.

- conciliar o modelo `papel + perfil` com a ADR-0004;
- propor o fluxo de solicitação e aprovação de permissões na ADR-0012;
- definir o contrato HTTP independente de Fuse ou de qualquer template;
- atualizar as tasks da spec depois da aceitação da ADR-0012;
- não criar migrations nem código de aplicação enquanto a fundação necessária
  estiver sob responsabilidade do Mauricio.

Resultado: a spec preserva `papel + atribuição + escopo`; o perfil observado no
OmniPlus não entra nesta versão. A ADR-0012 foi aceita, o contrato foi aprovado
e as entregas foram decompostas em T-47 (backend) e T-48 (frontend).

## Onda 3 — núcleo backend

**Estado:** aguardando fundação e publicação da T-47 no tracker.

Entregas previstas:

1. catálogo `Permissao` e agrupamento para apresentação;
2. persistência de papéis, teto do papel, perfis, permissões do perfil e
   atribuições por escopo;
3. dependências transitivas e permissões obrigatórias;
4. serviço de alcance por requisição;
5. Policies, atributo `#[RequerPermissao]` e middleware fail-closed;
6. trava do último administrador, anti-escalada e guard de substituição;
7. eventos de auditoria e observabilidade;
8. testes unitários, feature, integração PostgreSQL/RLS e arquitetura.

Gate de entrada:

- fundação backend reconciliada;
- tabelas `organizacoes`, `igrejas`, `contas` e `acessos` estabilizadas;
- ADR-0012 aceita e T-47 publicada no tracker, se o tracker permanecer ligado;
- branch da task atualizada a partir da integração definida pelo projeto.

## Onda 4 — frontend independente de template

**Estado:** aguardando contrato da Onda 2 e endpoints da Onda 3.

Entregas previstas:

1. `AuthorizationProvider` carregado pelo `/me`;
2. funções `can`, `canAny` e `canAll` com escopo quando necessário;
3. `ProtectedRoute` e componente `Can`, sem dependência do Fuse;
4. filtragem de navegação e ações somente como UX;
5. gestão de papéis, perfis e atribuições;
6. solicitação, aprovação, recusa e revogação de permissões;
7. formulários agrupados por domínio/módulo e limitados ao teto do papel;
8. testes de comportamento visível com React Testing Library.

Gate de saída:

- acesso direto por URL também é negado;
- mudança de organização recarrega capacidades;
- revogação reflete na próxima requisição;
- respostas 401, 403, 409 e 422 têm estados visíveis e traduzidos;
- nenhum componente do Fuse foi importado.

## Onda 5 — integração e endurecimento

**Estado:** futura.

- cenários ponta a ponta de administrador, secretaria e usuário solicitante;
- concorrência de aprovação/revogação e idempotência;
- matriz de permissões × escopos × categorias sensíveis;
- revisão de fronteiras, segurança, observabilidade e acessibilidade;
- documentação viva do módulo Autorização;
- handoff e evidências dos gates executados.

## Regra de continuidade

Cada sessão executa uma única onda ou uma fatia atômica explicitamente indicada
dela. O estado deve ser atualizado neste documento antes do handoff.
