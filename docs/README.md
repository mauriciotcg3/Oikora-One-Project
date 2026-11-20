# Documentação do Oikora One

Este diretório é a fonte compartilhada de contexto do produto. Uma pessoa que
clona o repositório deve conseguir compreender o problema, as decisões, o plano
e o comportamento implementado sem depender de arquivos particulares de outra
máquina.

## Por onde começar

1. [Produto](product/0001-fundacao-e-pessoas.md) — objetivos, atores,
   requisitos, regras de negócio e critérios de sucesso.
2. [Decisões de arquitetura](adr/README.md) — índice das ADRs aceitas; os
   arquivos desta pasta são a fonte da verdade do Oikora.
3. [Spec em execução](spec/0001-fundacao-e-pessoas-painel/spec.md) — escopo
   técnico aprovado. O [plano](spec/0001-fundacao-e-pessoas-painel/plan.md) e as
   [tarefas](spec/0001-fundacao-e-pessoas-painel/tasks.md) detalham a entrega em
   ondas.
4. [Módulos](modules/) — documentação viva do comportamento que o código já
   implementa.
5. [Ambiente de desenvolvimento](environment-setup.md) — preparação e
   validação do ambiente.

## Contexto complementar

- [Contexto estável para agentes](agents/project.md) — fase, tracker, convenção
  Git, pontos quentes e comandos já validados.
- [Análises de origem](analise/) — material de descoberta e referências que
  deram origem ao produto; não substitui os requisitos aprovados.
- [Modernização](modernizacao/) — descoberta e planos de substituição de
  sistemas legados.
- [Protótipos](prototipos/) — artefatos exploratórios, não necessariamente o
  comportamento atual do produto.
- [Issues do projeto](https://github.com/mauriciotcg3/Oikora-One-Project/issues)
  — estado compartilhado das tarefas em andamento.

## Fronteira com a governança `.ai`

- Requisito, ADR, spec, plano, documentação de módulo e contexto estável
  específicos do Oikora ficam neste repositório.
- A governança `.ai` mantém skills, regras, templates e scripts reutilizáveis
  entre projetos; esse conteúdo não deve ser copiado para cá. Desenvolvedores
  que usarem a automação de IA instalam separadamente o
  [framework de desenvolvimento](https://github.com/ThiagoAuquino/agentic-development-framework).
- As ADRs `*-oik-*` existentes no catálogo central da governança são espelhos
  gerados. A versão editável e revisada por pull request permanece em
  [`docs/adr/`](adr/).
- Handoffs particulares e credenciais nunca são fonte do produto. O estado das
  tarefas pertence às issues e segredos ficam fora do Git.

