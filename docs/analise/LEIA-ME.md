# Nova plataforma da igreja: pacote de documentos

Material produzido em 12 e 13/10/2026 a partir da análise do painel inChurch (admin.inchurch.com.br) e da Central de Ajuda oficial.

| Arquivo | Para quem | O que é |
|---|---|---|
| `analise-e-plano-nova-plataforma.md` | Liderança, gestão do projeto, equipe | **Comece por aqui.** Mapa de todas as telas do inChurch, análise detalhada do cadastro de membros e usuários, problemas por prioridade, proposta para a nova plataforma e plano em 7 fases, com a migração |
| `especificacao-tecnica-php.md` | Desenvolvedores | Como construir em PHP (Laravel + Filament): stack, arquitetura, banco de dados, código de referência, permissões, importação, API, migração, testes e CI |
| `base-conhecimento-agente-suporte.md` | Quem vai configurar o agente de suporte (IA) | Instruções do agente, regras de privacidade, glossário, passo a passo de cada tarefa e perguntas frequentes |
| `indice-central-de-ajuda.md` | Agente de suporte e equipe | 293 artigos da Central de Ajuda do inChurch, organizados por assunto e com link |
| `github/backlog.json` | Gestão do projeto | As 44 tarefas do plano, com fases, etiquetas e critérios de pronto |
| `github/criar-tarefas-github.ps1` | Quem administra o GitHub | Script que cria no GitHub as etiquetas, as 7 fases, as 44 tarefas e o quadro (Projects) |

## Como criar as tarefas no GitHub
1. Instale o GitHub CLI (https://cli.github.com) e faça login:
   - `gh auth login`
   - `gh auth refresh -s project`
2. No PowerShell, dentro da pasta `github`, rode:
   - `.\criar-tarefas-github.ps1 -Repo "organizacao/repositorio" -Owner "organizacao"`
   - Para só simular, sem criar nada, acrescente `-DryRun`.

## Observações
- Nenhum dado pessoal de membros foi incluído nestes documentos.
- Itens vindos da Central de Ajuda do inChurch estão marcados com **(CA)**.
- Os exemplos de código da especificação são um ponto de partida e devem ser revisados e testados pela equipe.
