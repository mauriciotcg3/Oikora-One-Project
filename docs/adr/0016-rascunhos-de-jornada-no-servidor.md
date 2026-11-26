# ADR-0016: Rascunhos de jornada guiada no servidor

**Status:** Aceito — após confirmação do usuário (2026-11-20)
**Data:** 2026-11-20
**Contexto do projeto:** Oikora One — plataforma da denominação (3 igrejas, ~520 membros) que substitui o inChurch módulo a módulo, com plano de atender outras igrejas.
**Identificação:** Produto `OIK-ADR-0016` · Governança `ADR-0044`
**Fonte da verdade:** `oikora-one:docs/adr/0016-rascunhos-de-jornada-no-servidor.md`

## Contexto

O NFR-007 exige que jornadas complexas, como conectar um número do WhatsApp da Meta, sejam guiadas, salváveis, retomáveis e recuperáveis. A OIK-ADR-0014 pede "salvar e retomar" para operações longas, mas nenhum ADR diz onde o rascunho vive. O painel não guarda credencial no navegador (OIK-ADR-0003) e dado pessoal local não acompanha a pessoa entre dispositivos.

## Opções consideradas

- **A. Rascunho no servidor** — recurso por conta e organização, sob RLS.
- **B. Rascunho no navegador** — armazenamento local do navegador.
- **C. Sem rascunho** — a jornada vale só dentro da sessão.

## Comparação

| Pilar | A. Servidor | B. Navegador | C. Sem rascunho |
|---|---|---|---|
| Desempenho | **Bom** — um `PUT` pequeno por etapa. | **Ótimo** — sem rede. | **Ótimo**. |
| Simplicidade de manutenção | **Aceitável** — tabela, endpoint e job de expiração. | **Bom** — só frontend, mas com limpeza e versões por navegador. | **Ótimo**. |
| Boas práticas | **Ótimo** — isolamento por RLS, cifra e expiração controlados no servidor. | **Ruim** — dado pessoal ou segredo em disco local sem controle. | **Ruim** — descumpre o NFR-007. |
| Escalabilidade | **Bom** — volume pequeno e limitado por expiração. | **Bom**. | **Ótimo**. |
| Observabilidade / logs | **Ótimo** — início, retomada, conclusão e abandono por etapa, no servidor. | **Ruim** — o suporte não vê o estado de uma pessoa travada. | **Ruim** — não há retomada para diagnosticar. |

## Decisão

Adotar a **opção A**.

1. Recurso `rascunhos_jornada` por conta e organização ativa: tipo de jornada (catálogo versionado), etapa atual, dados já informados, `versao`, `atualizado_em`, `expira_em`. RLS por organização (OIK-ADR-0002).
2. API em `/api/v1/painel/rascunhos/{tipo}`: ler, gravar (idempotente, com `versao`; conflito devolve 409 e o cliente recarrega, como na OIK-ADR-0006 §11) e descartar. Só o dono da conta lê o próprio rascunho; a tela decide o que mostrar, o servidor revalida o acesso à jornada (OIK-ADR-0004).
3. **Conteúdo:** o rascunho guarda apenas campos da própria jornada. Credenciais, tokens e segredos do provedor nunca entram; a jornada os pede de novo ou os obtém no passo final. Campos sensíveis (OIK-ADR-0004) são cifrados na aplicação com a chave de campos.
4. **Ciclo de vida:** concluir a jornada ou descartar remove o rascunho; um job por organização remove os expirados. O rascunho expira **30 dias** depois da última gravação (informado pelo usuário em 2026-11-20).
5. **Fora da auditoria e dos logs:** nenhum conteúdo de rascunho vai à auditoria, ao log técnico ou à telemetria; só o tipo, a etapa, o resultado e a correlação. A conclusão da jornada é auditada como ação normal (OIK-ADR-0005).
6. A T-52 entrega etapas, validação e confirmação; uma nova task de API e a integração no frontend entregam o salvar e retomar.

## Justificativa

O servidor já é a autoridade de autorização, isolamento e retenção; o navegador não é. A opção A mantém dado pessoal sob RLS e cifra, permite retomar em outro dispositivo e dá ao suporte um estado diagnosticável sem expor conteúdo. O custo é uma tabela e um job, proporcional ao volume de ~520 membros e poucos administradores.

## Consequências

**Positivas:**
- Retomar a jornada em outro dispositivo e depois de sessão expirada.
- Nenhum dado pessoal ou segredo em armazenamento local do navegador.

**Negativas / trade-offs aceitos:**
- Nova tabela, endpoint e job de expiração a manter, com contrato OpenAPI.
- Rascunhos expirados e abandonados exigem política de prazo.
- Conflito entre duas abas na mesma jornada resolve-se com 409 e recarga, não com mesclagem.

## Observabilidade — o que precisa ser instrumentado

- **Eventos a logar:** rascunho criado, retomado, concluído, descartado, expirado e conflito (tipo, etapa, resultado, `request_id`, `trace_id`), sem conteúdo.
- **Métricas a expor:** retomadas e abandonos por tipo e etapa; conflitos 409; rascunhos expirados por job.
- **Alertas a configurar:** falha do job de expiração; crescimento anormal de rascunhos por organização. Limiares na OIK-ADR-0010.
- **Cenário de incidente validado:** às 3h, uma secretaria diz que perdeu o progresso ao conectar o WhatsApp. O plantão filtra por organização e `request_id`, vê a etapa e o resultado do último `PUT` (conflito, expiração ou falha de gravação) sem ver o conteúdo.

## Lacunas conhecidas

- A primeira jornada a usar o rascunho é a conexão do número de WhatsApp (T-21/T-39); ela depende das contas Meta, e até lá os testes usam adapters falsos (OIK-ADR-0014).
- A cifra cobre só os campos sensíveis (OIK-ADR-0004), conforme a decisão 3.
- Métricas de funil agregadas dependem de decisão de coleta (OPEN-UI-04).
