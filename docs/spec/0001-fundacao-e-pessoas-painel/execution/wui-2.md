# Checkpoint — OIK-SPEC-0001 WUI-2

**Branch:** `feat/t-50` (merge da branch da spec em andamento, sem commit)
**Tasks:** T-54, T-55, T-56 e T-57
**Atualizado em:** 2026-11-24

## Progresso

- Dependências confirmadas: PR #80 fechado (inclui T-22), PR #81 fechado (inclui T-32) e issues #19, #22, #29 e #34 fechadas.
- Branch `origin/feat/spec-0001-fundacao-pessoas` integrada; conflitos de `InicioPainel.tsx` e `roteador.tsx` resolvidos preservando o shell e a navegação por capacidades da WUI-1, além de login, convite, 2FA, seletor de organização, Acessos e Papéis da W7.
- T-54, T-55, T-56 e T-57 concluídas na árvore de trabalho da branch `feat/t-50`, sem novo commit ou push nesta sessão.
- Revisão consolidada aplicada: achados HIGH de T-55/T-57 corrigidos e achados complementares de segurança da busca, documentação e cobertura executável fechados.

## Decisões e desvios

### T-55 sobre a Auditoria entregue pela T-40

A T-55 **estende a feature existente** `web/src/features/Auditoria`, entregue pela T-40. A implementação existente já é dona da chamada HTTP, i18n, consulta, tabela e testes; a T-55 acrescenta os filtros na URL, paginação no servidor, resultado, trilha, resumo e detalhe progressivo do UI Standard. O placeholder `PaginaAuditoria` da WUI-1 será substituído na mesma rota pela única `TelaAuditoria`, carregada sob demanda e dentro do shell. Não será criada uma segunda tela.

### Alterações fora do escopo

- O link de convite emitido pelo backend (`/convites/{token}`) não pertence às T-54–T-57. A rota pública do painel continua sendo `/painel/convites/{token}`; alinhar a URL gerada fica registrado como pendência, sem alteração nesta onda.
- Além da resolução necessária do merge descrita acima, `app/painel/__tests__/shell.test.tsx` passou a consultar especificamente a navegação acessível “Menu do painel”. O seletor genérico confundia o menu responsivo com a navegação legítima de atalhos da página inicial trazida pela W7; o comportamento do shell não foi alterado.
- As traduções da Auditoria são registradas junto com o carregamento lazy da feature. Isso evita que a importação estática do namespace pelo shell anule o chunk por rota, sem abrir uma entrada paralela à Public API da feature.

### Correções da revisão consolidada (2026-11-24)

- **HIGH — T-55:** o filtro de busca passou a existir na tela, na URL e na chamada da API; o backend trata `_`, `%` e `\\` como literais para não ampliar a consulta por curingas SQL.
- **HIGH — T-57:** o primeiro salvamento concorrente do mesmo rascunho é serializado por chave transacional; o segundo escritor observa a versão atual e recebe o conflito 409 previsto, em vez de uma violação de unicidade/500.
- **HIGH — T-57:** o adaptador HTTP saiu de `shared/ui` para `shared/jornadas`; `JornadaGuiada` voltou a ser um componente sem I/O, dependente apenas do contrato de persistência injetado.
- **HIGH — T-57:** falha ao descartar o rascunho depois de uma ação de negócio já concluída não transforma sucesso em falha nem induz repetição potencialmente destrutiva; o descarte falho gera evento técnico próprio.
- **T-56:** a documentação aponta para a rota real `/painel/galeria`, e o teste da galeria renderiza e verifica semanticamente as seções e estados do catálogo.

## Plano de banco e rollback (antes das migrations)

### T-54 — extensão de `auditoria`

- **Classe e dono:** mudança aditiva compatível, pertencente ao módulo Auditoria, na tabela particionada existente.
- **Deploy:** migration antes do código. Adicionar `resultado` com padrão constante `sucesso`, `codigo_falha` anulável e `trace_id` anulável, com checks de consistência; o código antigo ignora as colunas e o novo código lê o padrão nas linhas existentes.
- **Risco operacional:** `ALTER TABLE` exige lock curto no pai e nas partições. Não há backfill; o padrão constante é metadata-only no PostgreSQL 17. Nenhum índice novo até existir evidência de volume/plano que o justifique.
- **Rollback:** primeiro voltar a aplicação; depois remover checks e colunas com a migration `down`. Como a remoção apagaria correlação/falhas já gravadas, em produção a preferência é forward-fix e manter as colunas; executar o `down` somente com aceite explícito da perda desses dados.
- **Observação:** falha da migration fica visível pelo comando de deploy; falha ao registrar auditoria de falha deve gerar log técnico sem valores pessoais.

### T-57 — `rascunhos_jornada`

- **Classe e dono:** tabela nova, aditiva e compatível, pertencente ao novo módulo Jornadas; RLS por organização e unicidade por organização, conta e tipo de jornada.
- **Deploy:** migration antes do código; não há backfill. Índices serão limitados aos caminhos de leitura por dono/tipo e expiração do job.
- **Risco operacional:** criação de tabela e índices não bloqueia tabelas de negócio existentes; o conteúdo é temporário e expira em 30 dias. Dados sensíveis ficam cifrados e segredos são recusados.
- **Rollback:** voltar a aplicação primeiro. Em produção, manter a tabela para preservar rascunhos e aplicar forward-fix; o `down` remove a tabela e todos os rascunhos, portanto só deve ser executado com aceite explícito dessa perda. Não usar `migrate:fresh` como rollback.
- **Observação:** logs estruturados registram criar, retomar, conflito, descartar e expirar apenas com tipo/etapa/resultado e correlação, nunca com conteúdo.

## Gate de saída

- T-54 — Red confirmado em `ConsultaAuditoriaTest` (3 falhas) antes do Green; depois 8 testes/64 assertions Green.
- T-54 — Red confirmado em `RegistroAuditoriaTest` (2 falhas) antes do Green; depois 6 testes/17 assertions Green.
- T-54 — OpenAPI e `packages/api-contrato` regenerados; geração estável, TypeScript e 2 testes do contrato Green.
- T-54 — gate completo da API Green: Pint, PHPStan, Pest (476 passed, 1 skipped, 3214 assertions) e Deptrac (0 violações/0 erros).
- Integração da branch — o primeiro gate do web reproduziu 1 falha no teste do shell: o seletor genérico de `navigation` capturava os atalhos da W7. Após restringi-lo ao nome acessível “Menu do painel”, teste focado 5/5 Green e gate completo do web Green (75/75 testes; painel 293,17 KB gzip, abaixo do limite de 350 KB).
- T-55 — três cenários de aceite da consulta Green; filtros/paginação ficam na URL, detalhe progressivo e acesso negado verificados. O build mantém a Auditoria fora do chunk inicial e o DataGrid em chunk próprio.
- T-56 — exemplos e documentação do UI Standard Green, com os componentes exportados cobertos e caminhos locais existentes.
- T-57 — Red observado na API antes da implementação; depois 4 testes/40 assertions Green. No web, 6 testes da jornada Green, incluindo retomada após recarga e conflito que recarrega o servidor sem merge automático.
- T-57 — OpenAPI e `packages/api-contrato` regenerados com GET/PUT/DELETE de `/painel/rascunhos/{tipo}`.
- WUI-2 — gate completo da API Green: Pint, PHPStan, Pest (480 passed, 1 skipped, 3260 assertions) e Deptrac (0 violações/0 erros). Gate completo do web Green; pacote de contrato validado separadamente.
- Revisão 2026-11-24 — testes focados Green: API com 12 testes/106 asserções; web com 11 testes para Auditoria, Jornada e Galeria.
- Revisão 2026-11-24 — gate completo da API Green: Pint, PHPStan, Pest (480 passed, 1 skipped, 3262 assertions) e Deptrac (0 violações/0 erros).
- Revisão 2026-11-24 — gate completo do web Green: typecheck, ESLint, 81 testes, build e `size-limit` em 206,72 KB gzip (limite 350 KB).

## Próximo passo

- WUI-2 concluída com as correções da revisão consolidada. Abrir uma nova thread antes de iniciar a próxima onda da OIK-SPEC-0001.
