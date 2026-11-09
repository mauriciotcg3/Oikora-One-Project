# Módulo: Auditoria

**Última atualização:** 2026-11-08
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
| RN-05 | Partições são mensais, criadas antecipadamente e removidas após cinco anos por padrão. | comandos `auditoria:criar-particoes` e `auditoria:aplicar-retencao` | OIK-SPEC-0001 T-07; OIK-ADR-0005 |

## Interfaces / contratos expostos

- `Auditoria::registrar(...)` registra um `EventoAuditoria` na transação corrente.
- `#[AuditaCom(EventoAuditoria::...)]` declara o evento esperado em Actions de escrita.
- Comandos operacionais: `auditoria:criar-particoes --meses=2` e `auditoria:aplicar-retencao --anos=5`.

## Dependências

- **Depende de:** contexto transacional e RLS de Tenancy; PostgreSQL; contexto de `request_id` do Laravel.
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

- ⚠️ As Actions de negócio ainda serão criadas; o teste arquitetural passa a impedir novas Actions de escrita sem `#[AuditaCom]` quando elas existirem.
- ⚠️ Os comandos devem ser agendados no ambiente de produção nas tasks operacionais correspondentes.

## Changelog

| Data | Mudança | Origem (task/spec/adr) |
|---|---|---|
| 2026-11-08 | Criação do registro transacional, catálogo, partições, retenção e guard arquitetural. | OIK-SPEC-0001 T-07 |
