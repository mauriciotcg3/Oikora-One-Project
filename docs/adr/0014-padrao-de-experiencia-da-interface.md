# ADR-0014: Padrão de experiência da interface sobre MUI

**Status:** Aceito — após confirmação do usuário (2026-11-20)
**Data:** 2026-11-20
**Contexto do projeto:** Oikora One — plataforma da denominação (3 igrejas, ~520 membros) que substitui o inChurch módulo a módulo, com plano de atender outras igrejas.
**Identificação:** Produto `OIK-ADR-0014` · Governança `ADR-0042`
**Fonte da verdade:** `oikora-one:docs/adr/0014-padrao-de-experiencia-da-interface.md`

## Contexto

O Oikora já decidiu em OIK-ADR-0006 por React, TypeScript e MUI e já possui tema próprio, orçamento de 350 KB gzip para a entrada inicial do painel e boundaries de frontend. Faltava decidir como transformar essa base técnica em um padrão de experiência reconhecível do produto, sem depender do desenho genérico da MUI nem acoplar o produto a um template administrativo.

O requisito validado em `docs/product/0001-fundacao-e-pessoas.md` é que uma pessoa sem conhecimento técnico consiga concluir tarefas complexas. Integrações como a conexão de um número do WhatsApp da Meta devem apresentar linguagem de tarefa, pré-requisitos, etapas guiadas, verificação automática e recuperação; tokens, webhooks, IDs e payloads permanecem detalhes avançados. A mesma regra se aplica à auditoria: o usuário autorizado precisa responder quem fez o quê, quando, em qual alvo e com qual resultado antes de abrir metadados técnicos.

A pesquisa comparou projetos maduros e populares — [MUI](https://github.com/mui/material-ui), [shadcn/ui](https://github.com/shadcn-ui/ui), [Ant Design](https://github.com/ant-design/ant-design), [Ant Design Pro](https://github.com/ant-design/ant-design-pro), [Tabler](https://github.com/tabler/tabler), [React Admin](https://github.com/marmelab/react-admin) e [Refine](https://github.com/refinedev/refine) — e práticas de [aplicações complexas da Nielsen Norman Group](https://www.nngroup.com/articles/usability-heuristics-complex-applications/), [serviços com múltiplas tarefas do GOV.UK](https://design-system.service.gov.uk/patterns/complete-multiple-tasks/) e [WCAG 2.2](https://www.w3.org/WAI/WCAG22/Understanding/consistent-help). Estrelas serviram para encontrar referências amplamente usadas, não como critério isolado de arquitetura.

A tela de auditoria da branch `feat/mvp-controle-alugueis` do projeto privado `ThiagoAuquino/controle-de-alugueis` foi analisada como referência concreta. Ela usa busca e filtros, estado persistido na URL, lista virtualizada, destaque de falhas, trilha por entidade e detalhe em três camadas: resumo, alterações e dados técnicos. O Oikora deve preservar essa clareza e superá-la na correlação com suporte, respeitando OIK-ADR-0005: nomes de campos e metadados catalogados podem aparecer, mas valores pessoais anteriores e posteriores não entram na auditoria.

## Opções consideradas

- **Template ou framework administrativo completo** — adotar Fuse, Ant Design Pro, React Admin ou Refine como estrutura dominante do painel.
- **Oikora UI Standard sobre MUI** — manter a stack aceita e criar tokens, componentes, layouts e padrões de jornada próprios, usando projetos externos somente como referências.
- **Migrar para shadcn/ui e Tailwind** — substituir a base visual atual por componentes de código próprio e utilitários CSS.

## Comparação

| Pilar | Template ou framework completo | Oikora UI Standard sobre MUI | Migrar para shadcn/ui e Tailwind |
|---|---|---|---|
| Desempenho | **Aceitável** — entrega muitos recursos, mas traz abstrações e componentes não usados e reduz o controle sobre o bundle. | **Ótimo** — preserva o orçamento atual, permite carregamento por rota e introduz somente os padrões usados. | **Bom** — pode ser enxuto, mas a migração duplica temporariamente estilos e componentes. |
| Simplicidade de manutenção | **Aceitável** — acelera o início, porém o domínio passa a se adaptar às extensões, upgrades e convenções do fornecedor. | **Ótimo** — evolui a base existente, mantém um único sistema visual e deixa o comportamento documentado no próprio repositório. | **Ruim** — troca ampla da UI já implementada e aumenta o código que a equipe precisa manter diretamente. |
| Boas práticas | **Bom** — oferece padrões prontos, mas genéricos e nem sempre compatíveis com boundaries e linguagem do Oikora. | **Ótimo** — combina MUI, Frontend Standard, WCAG 2.2 e testes de comportamento em padrões específicos do produto. | **Bom** — oferece boa composição e acessibilidade, mas depende de reconstrução cuidadosa dos comportamentos. |
| Escalabilidade | **Bom** — possui muitos componentes, com risco de limites impostos pela arquitetura do template. | **Ótimo** — componentes transversais ficam em `shared/ui`, jornadas no módulo proprietário e novas telas crescem sem dependência de um template central. | **Bom** — permite crescer, mas exige formar e sustentar um design system maior desde o início. |
| Observabilidade / logs | **Aceitável** — estados de erro e telemetria teriam de ser adaptados às abstrações do produto externo. | **Ótimo** — estados visuais, ações relevantes e detalhes de suporte usam a correlação OpenTelemetry já decidida no Oikora. | **Bom** — é possível instrumentar, mas toda a integração e os padrões operacionais precisariam ser refeitos. |

## Decisão

Adotar o **Oikora UI Standard sobre MUI**, sem template administrativo como dependência estrutural. MUI fornece os primitivos; o Oikora define tokens, composição, linguagem, estados e comportamento dos componentes reutilizáveis.

O padrão aplica: linguagem de tarefa; divulgação progressiva; jornada guiada para processos complexos; pré-verificação e valores seguros; validação junto ao campo; confirmação antes de ação irreversível; salvar e retomar quando a operação for longa; recuperação clara de erro; ajuda contextual em posição consistente; acessibilidade WCAG 2.2 AA; ações condicionadas à permissão; e detalhes técnicos seguros disponíveis sob demanda.

A auditoria será a primeira referência operacional desse padrão: menu próprio, busca e filtros compartilháveis, visão resumida, destaque de falhas, trilha de um alvo e detalhe progressivo. A primeira camada é compreensível ao usuário; a camada técnica expõe somente identificadores, campos afetados, metadados catalogados, `request_id` e correlação permitida, sem valores pessoais nem segredos.

## Justificativa

A opção escolhida é a única que melhora a experiência sem reabrir a stack aceita em OIK-ADR-0006. Ela oferece melhor controle de desempenho, reduz migração e acoplamento, aplica acessibilidade e boundaries existentes, cresce incrementalmente por módulo e integra os estados visuais à observabilidade já definida em OIK-ADR-0010.

Templates e projetos populares continuam sendo fontes de padrões, não dependências automáticas. Isso permite adotar o que funciona — filtros, tabelas produtivas, navegação, feedback e detalhe progressivo — preservando a identidade, a linguagem e as restrições de privacidade do Oikora.

## Consequências

**Positivas:**
- A interface ganha identidade e comportamento consistentes sem migração de stack.
- Fluxos complexos podem esconder detalhes de implementação e ainda oferecer diagnóstico seguro ao suporte.
- Componentes reutilizáveis passam a ter contratos de comportamento, acessibilidade, estados e telemetria, não apenas aparência.
- A tela de auditoria pode aproveitar uma referência já validada pelo usuário sem copiar suas decisões de domínio ou armazenamento.

**Negativas / trade-offs aceitos:**
- O time passa a manter documentação, catálogo e testes do padrão visual próprio.
- A construção inicial de componentes e jornadas é mais lenta do que copiar telas prontas de um template.
- Melhorias visuais transversais exigem disciplina para não duplicar componentes dentro das features.
- A auditoria não mostrará diff de valores como algumas referências; essa limitação é intencional para respeitar privacidade e OIK-ADR-0005.

## Observabilidade — o que precisa ser instrumentado

- **Eventos a logar:** início, avanço, retomada, conclusão e falha das jornadas críticas; validação recusada; abertura do detalhe técnico; aplicação e limpeza de filtros de auditoria; consulta de trilha de um alvo. Registrar módulo, ação, resultado, duração, `request_id` e correlação, sem conteúdo de campo, credencial ou PII desnecessária.
- **Métricas a expor:** taxa de conclusão e abandono por etapa das jornadas críticas; falhas por etapa/código; duração até conclusão; consultas de auditoria por resultado e latência; percentual de falhas sem correlação técnica disponível.
- **Alertas a configurar:** aumento sustentado de falhas ou abandono numa etapa crítica; latência ou erro da consulta de auditoria acima dos limiares de OIK-ADR-0010; evento crítico sem `request_id`/correlação; falha ao carregar a própria trilha de auditoria.
- **Cenário de incidente validado:** às 3h, uma secretaria informa que não consegue concluir a conexão do WhatsApp. O plantão filtra a auditoria pela organização, período e resultado, encontra a etapa e o código legível da falha, abre os detalhes técnicos e usa o `request_id`/trace para localizar a chamada ao provedor, a permissão ausente e a versão do cliente. A causa e o próximo passo aparecem sem ler o código nem expor token, payload ou dado pessoal.

## Lacunas conhecidas

- Ainda não existem metas quantitativas de sucesso, abandono e tempo por jornada; a primeira medição em homologação definirá o baseline.
- O catálogo completo de componentes do Oikora, seus exemplos e critérios visuais será detalhado na Spec antes da implementação transversal.
- A jornada real do WhatsApp depende das contas Meta Business/Tech Provider e da validação do Embedded Signup; até lá, testes usam adapters falsos.
- Os limiares dos alertas e a ferramenta de análise de produto não foram definidos; não se introduzirá nova coleta de comportamento sem avaliação de privacidade e ADR quando ela alterar a stack de observabilidade.
