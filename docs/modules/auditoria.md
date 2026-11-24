# Módulo: Auditoria

**Última atualização:** 2026-11-17
**Arquivos principais:** `api/app/Modules/Auditoria/**`

## Visão geral

Registra fatos relevantes da aplicação na mesma transação da alteração de negócio. A trilha é somente de inclusão, particionada por mês e isolada por organização; ela guarda nomes de campos e metadados catalogados, nunca os valores alterados.

## Regras de negócio ativas

| ID | Regra | Onde está implementada | Origem |
|---|---|---|---|
| RN-01 | Um evento só pode ser registrado dentro de uma transação ativa. | `Auditoria::registrar` | OIK-SPEC-0001 T-07; OIK-ADR-0005 |
| RN-02 | Campos auditados são apenas nomes válidos; valores e texto livre não entram na trilha. | `Auditoria::validarCampos` e `validarMetadados` | OIK-SPEC-0001 T-07; OIK-ADR-0005 |
| RN-03 | Cada evento aceita somente o conjunto de metadados definido no catálogo. | `EventoAuditoria::metadadosPermitidos` | OIK-SPEC-0001 T-07 |
| RN-04 | A aplicação não pode atualizar nem excluir auditoria; a retenção é feita pelo papel dono. | migration de Auditoria e `AplicarRetencaoAuditoria` | OIK-SPEC-0001 T-07; OIK-ADR-0005 |
| RN-15 (consulta) | Só quem tem `auditoria.ver` consulta. Com a permissão no escopo organização vê tudo da organização ativa (RLS); em escopo menor, só eventos das igrejas do alcance, sem os de governança (sem igreja). A lista é paginada (25, 50 ou 100), do mais recente para o mais antigo, filtrável por período (datas no fuso da organização), evento, ator e alvo, e devolve nomes de campo e metadados codificados. | `ConsultarAuditoria`; `AuditoriaController` | OIK-SPEC-0001 T-46; OIK-ADR-0005 §3; CA-12 |
| RN-05 | Partições são mensais, criadas antecipadamente e removidas após cinco anos por padrão. | comandos `auditoria:criar-particoes` e `auditoria:aplicar-retencao` | OIK-SPEC-0001 T-07; OIK-ADR-0005 |

## Interfaces / contratos expostos

- `Auditoria::registrar(...)` registra um `EventoAuditoria` na transação corrente.
- `#[AuditaCom(EventoAuditoria::...)]` declara o evento esperado em Actions de escrita.
- `GET /api/v1/painel/auditoria?de=&ate=&evento=&ator=&alvo_tipo=&alvo=&pagina=&por_pagina=`: `data[]` com `ocorrido_em`, `evento`, `ator.acesso` (uuid), `alvo`, `igreja`, `campos`, `metadados`, `canal` e `request_id`; `meta` com `pagina`, `por_pagina` e `total`. Sem `auditoria.ver`, 403 `authz.denied`.
- Eventos novos: `organizacao.configurada` (com modo de matrícula antes e depois), `organizacao.igreja_criada` e `organizacao.igreja_atualizada`.
- Comandos operacionais: `auditoria:criar-particoes --meses=2` e `auditoria:aplicar-retencao --anos=5`.

## Dependências

- **Depende de:** contexto transacional e RLS de Tenancy; PostgreSQL; contexto de `request_id` do Laravel; na consulta, `AlcanceAtual` (Autorização), `AcessosDaOrganizacao` (Identidade) e `CatalogoIgrejas` e `ConsultaOrganizacoes` (Organização).
- **É usado por:** Actions de escrita dos módulos de negócio nas ondas seguintes.

## Decisões arquiteturais relevantes

| ADR | Como afeta este módulo |
|---|---|
| OIK-ADR-0002 | A tabela de auditoria usa isolamento por organização com RLS forçada. |
| OIK-ADR-0005 | Define registro transacional, imutabilidade, ausência de valores e retenção de cinco anos. |

## Observabilidade — o que o suporte deve saber

- **Eventos logados:** os valores catalogados em `EventoAuditoria`, com ator, alvo, canal e `request_id` quando disponível.
- **Métricas expostas:** nenhuma nesta entrega.
- **Erros comuns e o que significam:** `auditoria.exige_transacao` indica chamada fora da transação; `auditoria.metadado_fora_do_catalogo` ou `auditoria.texto_livre_proibido` indicam payload incompatível com o catálogo.

## Pontos em aberto / riscos conhecidos

- ⚠️ Os comandos devem ser agendados no ambiente de produção nas tasks operacionais correspondentes.

## Changelog

| Data | Mudança | Origem (task/spec/adr) |
|---|---|---|
| 2026-11-23 | `operacoes_desfaziveis` (snapshot cifrado com a chave de campos, 90 dias, `afetados` só com ids) e Contract `OperacoesDesfaziveis` (`registrar`, `porUuid`, `encerrar`, `protegidos`, `expirarDaPessoa`); metadados `operacao_id` e `acesso_movido` em `pessoa.mesclada`. | OIK-SPEC-0001 T-32, T-35 |
| 2026-11-08 | Criação do registro transacional, catálogo, partições, retenção e guard arquitetural. | OIK-SPEC-0001 T-07 |
| 2026-11-17 | Consulta paginada `GET /painel/auditoria` com filtros e alcance; eventos de configuração da organização e das igrejas. | OIK-SPEC-0001 T-46, T-24 |
