# ADRs do Oikora One

Registros de decisão de arquitetura (ADR) do produto. **Esta pasta é a fonte da
verdade**: para mudar uma decisão, edite o ADR aqui (ou crie um novo que o
substitua).

- Numeração do produto: `OIK-ADR-NNNN`, na ordem em que a decisão passa a valer.
- Cada ADR declara também o ID na governança de desenvolvimento com IA
  (`agentic-development-framework`, `docs/adr/`), onde existe um espelho gerado
  automaticamente — esse espelho nunca é editado à mão.
- Status: `Aceito` quando confirmado pelo responsável pelo produto.

| ID | Título | Status |
|---|---|---|
| [OIK-ADR-0001](0001-stack-base-e-topologia.md) | Stack base e topologia (PHP/Laravel como API + React) | Aceito |
| [OIK-ADR-0002](0002-banco-e-isolamento-multi-organizacao.md) | Banco de dados e isolamento multi-organização (PostgreSQL + RLS por organização) | Aceito |
| [OIK-ADR-0003](0003-identidade-autenticacao-e-sessao.md) | Identidade, autenticação e sessão (conta global + Sanctum cookie/token) | Aceito |
| [OIK-ADR-0004](0004-autorizacao-recurso-acao-escopo-e-dados-sensiveis.md) | Autorização recurso × ação × escopo e dados sensíveis (modelo próprio + criptografia de documentos e saúde) | Aceito |
| [OIK-ADR-0005](0005-auditoria-historico-e-operacoes-desfaziveis.md) | Auditoria, histórico e operações desfazíveis (LGPD) (módulo próprio de auditoria + snapshot para desfazer) | Aceito |
| [OIK-ADR-0006](0006-frontend-web.md) | Frontend web — painel e área do membro (SPA Vite com duas entradas + MUI) | Aceito |
| [OIK-ADR-0007](0007-app-nas-lojas.md) | App nas lojas — área do membro em React Native com Expo | Aceito |
