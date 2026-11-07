# Descoberta do esquema de permissões do OmniPlus

## Objetivo e limites

Mapear o comportamento de autorização existente no OmniPlus e adaptar o núcleo
reutilizável ao Oikora One. A origem é estritamente somente leitura. Este pacote
não copia permissões específicas de CRM, telefonia, campanhas ou WhatsApp, nem
qualquer componente do Fuse.

- Origem canônica: `/home/thiagonascimento/Elev/Products/omniplus-next`
- Revisão observada: `74c9fa62fbde4e909c8bcc25c0a3c848242272d6`
- Branch observada: `develop`
- Destino: `/home/thiagonascimento/Desktop/pessoal/Oikora-One-Project`
- Data da descoberta: 2026-11-07

## Evidências principais

| ID | Evidência na origem | Comportamento observado |
|---|---|---|
| E-01 | `api/database/migrations/2025_12_19_144117_create_authorization_tables.php` | Papéis, perfis e permissões são entidades distintas. |
| E-02 | `api/database/migrations/2025_12_19_144131_create_user_authorization_pivots.php` | O perfil é atribuído ao usuário no contexto do cliente. |
| E-03 | `api/app/Models/Profile.php` | Perfil seleciona permissões dentro do teto do papel e resolve herança de template. |
| E-04 | `api/app/Models/Permission.php` | Dependências de permissão são expandidas transitivamente. |
| E-05 | `api/app/Models/User.php` | Permissão efetiva soma perfis aplicáveis ao cliente e permissões diretas, com cache por cliente. |
| E-06 | `api/app/Services/Authorization/RoleHierarchyPolicy.php` | Quem atribui não pode conceder papel acima do próprio nível. |
| E-07 | `api/app/Services/Authorization/PermissionScopeReplaceGuard.php` | Substituições em lote recusam remoções inesperadas e registram o diff. |
| E-08 | `api/app/Http/Middleware/CheckPermission.php` | Rotas são protegidas no backend; a UI não é autoridade de autorização. |
| E-09 | `api/app/Http/Resources/RoleResource.php` | O teto do papel é devolvido agrupado por menu, módulo e permissão. |
| E-10 | `web/src/app/(control-panel)/settings/access-profiles/` | O frontend lista, cria, edita, desativa e atribui perfis pelo contrato HTTP. |
| E-11 | `web/src/app/(control-panel)/settings/access-profiles/api/utils/roleScopePermissions.ts` | A interface só exibe permissões pertencentes ao teto do papel selecionado. |
| E-12 | `web/src/@fuse/core/FuseAuthorization/FuseAuthorization.tsx` | Integração específica do Fuse; explicitamente fora do escopo da cópia. |

## Modelo observado

```text
papel --define teto--> permissões permitidas
  |
  +-- perfil --seleciona subconjunto + obrigatórias--> permissões efetivas
                    |
acesso/usuário + tenant --recebe perfil(s)-----------+
```

Características relevantes:

- catálogo versionado de permissões;
- permissões agrupadas para apresentação;
- permissões obrigatórias por papel;
- dependências transitivas entre permissões;
- perfil padrão/template e perfil customizado;
- atribuição contextual ao tenant;
- hierarquia e bloqueio de autoelevação;
- invalidação imediata do cache quando perfil, vínculo ou permissão muda;
- rotas protegidas no backend e ocultação/disable apenas como UX no frontend;
- proteção contra remoção acidental em sincronizações em lote.

## Decisão de adaptação ao Oikora

O Oikora preservará a separação **papel + perfil**, confirmada pelo responsável
do produto em 2026-11-07:

- o **papel** define o teto de permissões que um perfil pode selecionar;
- o **perfil de acesso** é uma combinação nomeada de permissões dentro do papel;
- a **atribuição** liga um acesso a um perfil e a um escopo (`organizacao`,
  `regional` ou `igreja`);
- várias atribuições somam permissões e alcance, como já determina a ADR-0004;
- permissões novas continuam surgindo somente pelo catálogo em código;
- o alcance continua calculado por requisição, sem o cache de dez minutos do
  OmniPlus, para que revogações valham imediatamente;
- o tenant do OmniPlus será traduzido para organização + escopo de igreja do
  Oikora, respeitando a RLS definida pela ADR-0002.

Não serão copiados:

- nomes de papéis e permissões próprios do OmniPlus;
- permissões diretas e informais em usuário;
- exceções de superadministrador da plataforma;
- herança ligada à carteira de revenda;
- menus e componentes do Fuse;
- cache entre requisições;
- regras específicas de telefonia, CRM, campanhas ou WhatsApp.

## Lacunas transformadas em decisão de produto

O responsável solicitou um fluxo em que o usuário pede permissões e alguém as
aprova. A concessão resultante deve ser explícita, auditável e separada da
edição permanente de perfil. A decisão detalhada está proposta na ADR-0012.

## Riscos de migração

| Risco | Mitigação no Oikora |
|---|---|
| Duplicar permissões no backend e frontend e provocar drift | Backend devolve catálogo/teto e permissões efetivas; frontend mantém tipos e chaves testadas contra o contrato. |
| Um aprovador conceder mais do que possui | Política anti-escalada compara permissão e alcance antes de aprovar ou atribuir. |
| Remoção em lote ampliar ou quebrar acesso silenciosamente | Guard de diff inspirado em E-07 e testes de regressão. |
| UI esconder ação, mas API aceitá-la | Toda rota e Action continuam protegidas no backend; componentes visuais são somente conveniência. |
| Revogação demorar a surtir efeito | Alcance recalculado por requisição; nenhuma autorização confiada a cache persistente. |

## Estado da descoberta

Onda 1 concluída em modo somente leitura. Nenhum arquivo da origem foi alterado.
