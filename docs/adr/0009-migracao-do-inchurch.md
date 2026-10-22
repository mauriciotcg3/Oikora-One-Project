# ADR-0009: Migração do inChurch — módulo Migração com área de preparação e virada em lote

**Status:** Aceito — após confirmação do usuário (2026-10-21)
**Data:** 2026-10-21
**Contexto do projeto:** Oikora One — plataforma da denominação (3 igrejas, ~520 membros) que substitui o inChurch módulo a módulo, com plano de atender outras igrejas.
**Identificação:** Produto `OIK-ADR-0009` · Governança `ADR-0031`
**Fonte da verdade:** `oikora-one:docs/adr/0009-migracao-do-inchurch.md`

## Contexto

O produto (`docs/product/0001-fundacao-e-pessoas.md`) exige migrar a base de Pessoas do inChurch **unificando** membros, visitantes/frequentadores, usuários do app e usuários do painel (FR-025), com **relatório de conflitos** revisado pela secretaria antes da virada (FR-026) e **preservando matrícula, linha do tempo, funções com datas e consentimentos** (FR-027). O FLOW-007 cruza por e-mail, CPF, celular e nome + nascimento; os critérios de duplicidade são os da ASSUMPTION-003; as pessoas migradas mantêm o vínculo atual (ASSUMPTION-007). A contagem oficial é "pessoas ativas por vínculo em cada igreja" e a mesma definição confere a migração (BR-012). A virada de Pessoas só acontece junto com Células, Kids e Eventos (BR-014), e até lá o Oikora é só teste/homologação (BR-017). O critério de pronto é ensaio de migração aprovado e homologação pelas secretarias (§4). O §20 pede "forma de extração e ensaio de migração repetível". Pendências que tocam a migração: `OPEN-003` (campos obrigatórios), `OPEN-013` (bloqueia a virada) e `OPEN-016` (matrícula por denominação ou por igreja).

ADRs aceitos que restringem esta decisão:

- **OIK-ADR-0001:** cada caso de uso é uma Action única, chamada por todos os canais, **inclusive a migração**.
- **OIK-ADR-0002:** `organizacao_id` + RLS forçada; todo comando grava o contexto de organização; papel de plataforma com `BYPASSRLS` só em comando administrativo auditado; `application_name` por processo.
- **OIK-ADR-0003:** vínculo conta↔Pessoa confirmado pela secretaria, nunca deduzido por e-mail igual.
- **OIK-ADR-0004:** CPF/RG e saúde cifrados com chave própria; `cpf_hash` para busca e duplicidade; nenhum valor sensível em log.
- **OIK-ADR-0005:** carga da migração usa o mecanismo de `operacoes_desfaziveis` (snapshot 90 dias); consentimentos só de inclusão, com origem "migração do inChurch"; lixeira de 30 dias; o ensaio e a repetição da migração ficaram para este ADR.
- **OIK-ADR-0008 (item 18):** migrar e-mails, celulares com "é WhatsApp" e consentimentos de comunicação; importação e ensaios com avisos suprimidos.

Referência, não decisão (`docs/analise/`): o inChurch tem **API pública** REST com API Key e Secret (leitura de membros, visitantes, líderes, grupos, eventos, inscrições, financeiro e células) e a exportação **"em formato de importação"** (XLSX por e-mail); o plano sugere cliente de API só de leitura, tabela de mapeamento dos 48 campos, staging com o JSON original e "operação em paralelo por 2 a 4 semanas".

Regras de legado da governança: o inChurch é um SaaS de terceiros, sem acesso a código nem banco, então as regras de banco legado (backup/restore isolado, `LDB-001`–`006`) não se aplicam. Valem por analogia: **o legado é somente leitura** (nada é gravado no inChurch) e **o bruto extraído nunca vira carga direta**. A regra "nunca reescrita big bang" mira reescrita de código; aqui a substituição continua por módulo (Financeiro e Conteúdo depois) e só a virada de dados dos quatro módulos de BR-014 é conjunta.

Informado pelo usuário em 2026-10-21: **não se sabe** se a API está liberada no plano/perfil e **a exportação nunca foi usada**; este ADR decide o **mecanismo comum + Pessoas**, e Células, Kids e Eventos plugam no mesmo mecanismo quando tiverem documento de produto; **sem data** de virada; congelamento aceitável de **1–2 dias úteis**; ensaios **até dois seguidos limpos**; cada **secretaria** revisa conflitos e aprova a contagem da sua igreja e o **administrador** dá o aceite final; depois da abertura, **sem volta ao inChurch** (corrige no Oikora); possíveis duplicados decididos **antes da carga**, com a decisão reaplicada; acesso por **convite da secretaria** depois da virada; consentimento sem data e origem comprováveis **não migra**; vêm junto **fotos, inativos, lixeira do inChurch** (na lixeira do Oikora, 30 dias a partir da virada) e a **lista dos usuários do painel V1**. Equipe de 2–4 desenvolvedores + IA, sem plantão 24h (horário comercial + cultos), expansão para centenas de organizações em 2–3 anos.

## Opções consideradas

- **A. Carga única por planilha** — exporta o XLSX, um script converte e grava direto nas tabelas, uma vez.
- **B. Módulo Migração no monólito + virada em lote** — fonte por adaptador (API ou XLSX), área de preparação cifrada sob RLS, mapeamento e unificação determinísticos com decisões gravadas e reaplicadas, carga pelas Actions como operação desfazível, ensaios repetidos e virada única com congelamento de 1–2 dias úteis.
- **C. B + sincronização contínua até a virada** — a mesma esteira, puxando alterações do inChurch continuamente para congelar só algumas horas.

Sub-decisão da fonte: **API** × **exportação XLSX** × **ambas**, resolvida dentro de B pelo adaptador e pela sondagem (Decisão, itens 2–3).

## Comparação

| Pilar | A. Planilha única | B. Módulo + virada em lote | C. B + sincronização contínua |
|---|---|---|---|
| Desempenho | Bom — uma passada | Bom — ~520 membros + visitantes e contas: extrair e carregar tudo leva minutos; incremental não se paga | Bom — mas o delta depende de consulta "alterado desde", que não se sabe se a API oferece |
| Simplicidade de manutenção | Ótimo no 1º dia; Ruim do 2º ensaio em diante — refazer à mão, decisões perdidas | Bom — um módulo, um caminho (Actions), tudo repetível por comando | Ruim — detectar exclusões, reaplicar decisões a cada delta e agendador contra API de terceiro, para 2–4 devs |
| Boas práticas | Ruim — fura as Actions (0001), a RLS (0002) e o desfazer (0005); bruto vira carga direta | Ótimo — legado só leitura, bruto separado da carga, regras de domínio valendo, desfazer e auditoria | Bom nas regras; o ganho (horas a menos de congelamento) não paga o risco |
| Escalabilidade | Ruim — script por cliente | Ótimo — o mesmo módulo serve cada nova organização vinda do inChurch | Bom — com o custo do delta em cada cliente |
| Observabilidade / logs | Ruim — erro aparece como "contagem não bate", sem rastro | Bom — execução com fases, contagens por entidade e motivo de rejeição, relatório baixável | Aceitável — divergência pode vir de qualquer delta; mais difícil de explicar |

## Decisão

Adotar **B — módulo Migração no monólito, com área de preparação e virada em lote**, com as regras abaixo.

1. **Módulo e execução.** Módulo `Migracao` no monólito (OIK-ADR-0001), operado por comandos Artisan e por telas do painel (relatórios e conflitos). Toda execução é **de uma organização**: roda com o **papel da aplicação** (sem `BYPASSRLS`), grava o contexto de organização da RLS antes de qualquer consulta e usa `application_name = oikora-inchurch`. Só o provisionamento da organização é comando de plataforma (OIK-ADR-0002). Permissões novas do catálogo (OIK-ADR-0004): `migracao.gerir` (administrador: credenciais, execuções, mapeamento de valores, go/no-go) e `migracao.conflitos.resolver` (secretaria, com o alcance da sua igreja e a projeção de sensíveis de sempre).
2. **Fonte por adaptador; o inChurch é somente leitura.** Interface `FonteInchurch` com duas implementações: **API pública** (cliente de API criado com permissão só de leitura; API Key/Secret da organização cifrados e fora do código; paginação com ponto de retomada; respeito ao limite de requisições com espera e nova tentativa) e **exportação XLSX** (arquivo enviado pelo administrador no painel, nunca encaminhado por e-mail para a plataforma, com hash do arquivo registrado). Nenhuma chamada de escrita ao inChurch existe no código.
3. **Sondagem antes do 1º ensaio.** Comando de sondagem que, sem gravar dado pessoal, lista por entidade e por campo o que cada fonte entrega (contagem, campos presentes, ids estáveis, datas de linha do tempo, funções, consentimentos, fotos, matrícula). Regra: **API é a fonte principal** se a sondagem confirmar ids estáveis e cobertura dos campos de FR-027; o **XLSX é reserva** (fonte inteira) ou **complemento por campo**, declarado no mapeamento. O resultado da sondagem e a escolha ficam registrados na execução e na Spec.
4. **Área de preparação.** Tabelas `migracao_*` no mesmo PostgreSQL, com `organizacao_id` e RLS forçada:
   - `migracao_execucoes` (tipo `sondagem|ensaio|virada`, fonte, versão do mapeamento, fases com horário e estado, contagens, operador);
   - `migracao_registros` (execução, entidade de origem, id de origem, **payload bruto cifrado** com a chave de campos do OIK-ADR-0004, hash do payload);
   - `migracao_conflitos` e `migracao_decisoes` (item 7);
   - `migracao_origem` — o **de-para** `(organizacao_id, entidade_origem, id_origem) → id no Oikora`, sem dado pessoal.
   Fotos baixadas vão para um prefixo privado do armazenamento de arquivos (OIK-ADR-0011). **Retenção:** payload bruto, fotos não usadas e registros de execução de ensaio são apagados por job **90 dias após a virada** (mesmo prazo do snapshot de desfazer) ou quando a migração da organização é cancelada; o de-para fica enquanto a organização existir (os módulos seguintes o usam). Pedido LGPD durante a migração também apaga o bruto da pessoa. **Dado real só em produção:** nunca é copiado para desenvolvimento ou homologação.
5. **Organização "em migração" em produção.** A organização real nasce em produção no estado `em_migracao`: formulários públicos, área do membro, app, convites e avisos **fechados** (BR-017). Só administrador e secretarias entram no painel, para revisar conflitos e conferir ensaios. A homologação de uso (BR-017, §4) roda no ambiente de homologação com dados fictícios (OIK-ADR-0011). A abertura (item 11) muda o estado para `ativa`.
6. **Mapeamento e transformação determinísticos.** Mapeamento de campos (os 48 do inChurch, com os duplicados) **versionado no código**; mapeamento de valores (motivos, tipos de membro, funções, vínculos) por organização, editado pelo administrador e versionado. Mesma fonte + mesma versão de mapeamento + mesmas decisões = mesmo resultado. Normalização: celular em E.164 com "é WhatsApp", e-mail em minúsculas, CPF validado (inválido não entra e vira pendência), datas com fuso da organização. Valor sem mapeamento **não é descartado em silêncio**: o registro é rejeitado na fase de mapeamento com motivo e aparece no relatório.
7. **Unificação e conflitos — nada se mescla sem decisão (BR-008).** Os perfis do inChurch (membro, visitante/frequentador, usuário do app, usuário do painel) formam **grupos candidatos** pelos critérios de ASSUMPTION-003 e FLOW-007 (CPF; e-mail; celular; nome completo + nascimento). Não há unificação automática por e-mail (famílias costumam dividir e-mail). Cada grupo vira um conflito com sugestão (forte: mesmo CPF válido + mesmo nome e nascimento) e é decidido por uma pessoa: **mesma pessoa** (escolhendo o valor de cada campo divergente) ou **pessoas diferentes**. Sugestões fortes podem ser aceitas em lote, sempre por quem decide. O conflito pertence à secretaria da igreja do vínculo; grupo com vínculos em igrejas diferentes (BR-015) vai ao administrador. A decisão fica em `migracao_decisoes`, presa à **impressão digital do grupo** (conjunto de ids de origem + hash dos campos decididos), e é **reaplicada** em cada ensaio e na virada; se o grupo mudar (id novo, campo decidido alterado no inChurch), a decisão cai e o conflito volta como novo. Pendências de qualidade (CPF inválido, celular sem DDD, obrigatórios de BR-011 ausentes, matrícula repetida) aparecem no mesmo relatório, sem bloquear a carga (ASSUMPTION-006), salvo matrícula repetida (item 9).
8. **Carga pelas Actions, como operação desfazível.** A carga chama as **mesmas Actions** do domínio, num **contexto de migração** que: mantém o vínculo atual (ASSUMPTION-007) e as datas retroativas de origem; grava autor "migração do inChurch" + operador; registra na `auditoria` (OIK-ADR-0005) uma ação de importação por organização com contagens, sem valores. Cada carga é **uma operação desfazível** do tipo `migracao` (snapshot 90 dias); a carga é **idempotente** pelo de-para (registro já carregado é atualizado, não duplicado). Ordem: Pessoas e família/responsáveis, vínculos e matrícula, funções, linha do tempo, consentimentos, fotos; Células, Kids e Eventos entram depois, no mesmo mecanismo, quando tiverem mapeamento (BR-014).
9. **Preservação (FR-027).**
   - **Matrícula:** mantida como está. Se `OPEN-016` mostrar matrícula por igreja com números repetidos, o administrador define a regra de renumeração antes do 1º ensaio; ela é aplicada de forma determinística e a matrícula de origem fica no de-para e como evento na linha do tempo.
   - **Linha do tempo:** cada evento de origem vira evento com data de ocorrência original, motivo e origem `inchurch`; mais um evento "migrado do inChurch" na data da carga.
   - **Funções:** com início e fim de origem; sem data vira pendência no relatório.
   - **Consentimentos:** só migram com finalidade, data e origem comprováveis no inChurch, como registro de inclusão com origem "migração do inChurch" e a data original (OIK-ADR-0005). Sem prova, a pessoa fica **sem consentimento** e é convidada a aceitar o termo da organização depois da abertura; as automáticas que dependem de consentimento (OIK-ADR-0008) só saem depois do aceite.
   - **Contatos:** e-mails e celulares com "é WhatsApp" migram (OIK-ADR-0008); preferências de aviso começam no padrão.
   - **Inativos** entram inativos com motivo; **lixeira do inChurch** entra na lixeira do Oikora com o relógio de 30 dias contando a partir da virada (BR-022), e depois segue o removedor.
   - **Fotos:** baixadas da fonte e guardadas como a foto da ficha.
10. **Avisos suprimidos.** Em qualquer execução de migração e enquanto a organização estiver `em_migracao`, o módulo Avisos (OIK-ADR-0008) não cria envios: registra `envio.suprimido` com motivo `migracao`, e convites, automáticas e avisos de serviço ficam desligados. Nada é enfileirado para depois: não há disparo represado na abertura.
11. **Ensaios, contagem oficial e virada.**
    - **Ensaio:** extração completa → mapeamento → unificação com as decisões já gravadas → carga na organização `em_migracao` → conferência. Para o próximo ensaio, a carga é **descartada** pelo caminho do removedor de Pessoas (OIK-ADR-0005), dentro da organização e sob RLS, preservando conflitos, decisões, mapeamento e de-para.
    - **Contagem oficial (BR-012):** o relatório da execução mostra, por igreja e vínculo, a contagem de pessoas ativas calculada **na fonte** e **no Oikora** com a mesma definição, e explica cada diferença (unificadas, rejeitadas com motivo, inativas, lixeira). Cada secretaria aprova a contagem da sua igreja; o administrador dá o aceite.
    - **Ensaio limpo:** contagem conferida e aprovada, nenhum conflito pendente, nenhum conflito novo em relação ao ensaio anterior e nenhuma rejeição sem motivo aceito. A virada só é marcada depois de **dois ensaios seguidos limpos** e da homologação de uso (§4).
    - **Virada (lote único dos módulos de BR-014):** dia útil longe dos cultos e do check-in do Kids. As secretarias **congelam** o cadastro no inChurch (combinado, já que o Oikora não grava no inChurch); extração final; carga; contagem; **go/no-go** do administrador. Antes do go, cancelar é seguro: o inChurch segue oficial e nada foi aberto. Depois do go, a organização passa a `ativa`, o inChurch fica só para consulta e **não há volta**. Correção é para frente, e o desfazer da carga continua disponível por 90 dias. Uma reextração rápida logo após a carga compara contagens e hashes com a final para apontar alteração feita no inChurch depois do congelamento. Congelamento-alvo: 1–2 dias úteis.
12. **Acesso depois da virada.** Senhas do inChurch não migram. Depois da abertura, a secretaria dispara **convites em lote** para fichas com e-mail ou celular. Como o convite parte da ficha, o vínculo conta↔Pessoa nasce confirmado pela secretaria (BR-002, OIK-ADR-0003). Pessoa sem convite segue o FLOW-002. Os usuários do painel V1 viram **uma lista** (pessoa, papéis de origem) para o administrador convidar com os papéis do Oikora (FR-002); nenhuma permissão é traduzida automaticamente.
13. **Outros clientes vindos do inChurch.** O mesmo módulo, com o mesmo fluxo de sondagem → ensaios → virada, atende cada nova organização que migrar do inChurch, sem script por cliente. O mapeamento de valores é por organização; o de campos, comum.
14. **Fora deste ADR — o que outros ADRs precisam deste.**
    - **OIK-ADR-0010:** ferramentas, painéis e limiares das métricas e alertas abaixo; testes do mapeamento com fixtures anonimizadas das duas fontes; teste de idempotência (carga duas vezes = mesmo resultado) e de reaplicação de decisões.
    - **OIK-ADR-0011:** cofre para as credenciais da API do inChurch por organização; armazenamento privado das fotos e do XLSX; cifra de disco/backup da área de preparação e a regra de que backups expirem o bruto no mesmo prazo; dados fictícios em homologação; processo da execução (fila `pesadas` ou comando supervisionado).
    - **Documentos de Células, Kids e Eventos:** mapeamento de cada módulo no mesmo mecanismo antes do 1º ensaio conjunto; migração das crianças pelo documento do Kids (`OPEN-012`).
    - **Spec:** nomes finais de tabelas e comandos, telas de conflitos e relatório, lista dos 48 campos e dos valores, regra de matrícula (`OPEN-016`), obrigatórios (`OPEN-003`), roteiro da virada (checklist e comunicação aos membros).

## Justificativa

- **Boas práticas:** B é a única opção que respeita os ADRs anteriores: carga pelas mesmas Actions (0001), sob RLS e sem `BYPASSRLS` (0002), vínculo de conta nunca deduzido (0003), CPF/saúde cifrados também no bruto (0004), desfazer e consentimento só de inclusão (0005), avisos suprimidos (0008). O inChurch fica somente leitura e o bruto nunca vira carga direta.
- **Simplicidade:** a repetição é o requisito central (§20, "até dois seguidos limpos"). Com decisões gravadas e reaplicadas, um ensaio novo custa um comando, não um retrabalho das secretarias. C acrescentaria detecção de exclusões e reaplicação a cada delta contra uma API que nem se sabe se está liberada, para ganhar horas num congelamento que o usuário aceita em 1–2 dias.
- **Desempenho:** com a base atual, a extração completa e a carga levam minutos; incremental não se justifica.
- **Escalabilidade:** o mesmo módulo atende cada organização vinda do inChurch, provável origem de muitos dos clientes previstos.
- **Observabilidade:** cada execução tem fases, contagens por entidade e motivo de cada rejeição; a contagem BR-012 é calculada dos dois lados e as diferenças são explicadas, o que ataca a desconfiança atual com números que não batem (434 × 466 × 513).
- **Discordâncias registradas:**
  - A "operação em paralelo por 2 a 4 semanas" da análise **não foi adotada**: contraria a BR-017 e o "sem volta"; digitação dupla gera divergência, a mesma dor do inChurch.
  - A virada em lote dos quatro módulos (BR-014) concentra risco numa única janela. Foi aceita porque evita manter Pessoas em duas bases; o risco é coberto por ensaios até dois seguidos limpos e pelo go/no-go antes da abertura.
  - Unificação automática por e-mail foi recusada mesmo sendo o vínculo do inChurch: famílias dividem e-mail, e a BR-008 exige decisão humana.

## Consequências

**Positivas:**
- Migração repetível e conferível: dois ensaios limpos dão evidência objetiva para a virada.
- Decisões das secretarias não se perdem entre ensaios; o trabalho de revisão é feito uma vez.
- Regras de domínio, RLS, cifra, auditoria e desfazer valem para dados migrados como para qualquer cadastro.
- Fonte trocável (API ou XLSX) sem mexer no restante; a dúvida sobre a API não bloqueia o desenho.
- O módulo vira ferramenta de onboarding de novos clientes vindos do inChurch.

**Negativas / trade-offs aceitos:**
- Dados reais ficam em produção antes da virada, numa organização fechada; exige que o estado `em_migracao` feche de fato todos os canais (teste obrigatório).
- Congelamento de 1–2 dias úteis sem cadastro, combinado e não imposto pelo sistema; alteração feita no inChurch depois do congelamento só é detectada pela reextração de conferência.
- Sem volta ao inChurch depois do go; um defeito descoberto depois é corrigido para frente.
- Pessoas da lixeira do inChurch somem do Oikora 30 dias depois da virada, se ninguém as recuperar.
- Consentimentos sem prova não migram: parabéns e outras automáticas por WhatsApp ficam paradas até as pessoas aceitarem o termo.
- Convites em lote depois da abertura concentram envio de e-mail/WhatsApp e dúvidas de acesso nos primeiros dias.
- O mapeamento de campos depende da sondagem; se a API não estiver liberada, o XLSX pode não trazer linha do tempo, funções com datas ou consentimentos, e FR-027 fica parcial (ver Lacunas).

## Observabilidade — o que precisa ser instrumentado

- **Eventos a logar:** `migracao.sondagem.concluida` (fonte, entidades, campos cobertos); `migracao.execucao.iniciada`/`concluida`/`falhou` (organização, execução, tipo, fase, fonte, versão do mapeamento); `migracao.fonte.erro` (fonte, entidade, página, código HTTP ou erro de leitura, tentativa); `migracao.fonte.limite` (espera por limite de requisições); `migracao.registro.rejeitado` (execução, entidade, id interno do registro na preparação, motivo, sem valores); `migracao.conflito.criado`/`decidido`/`invalidado` (grupo, critério, quem decidiu); `migracao.carga.concluida` (operação desfazível, contagens por entidade); `migracao.contagem.divergente` (igreja, vínculo, contagem fonte × Oikora, diferenças explicadas e não explicadas); `migracao.virada.go`/`no_go`/`cancelada` (operador); `migracao.alteracao_pos_congelamento` (entidade, quantidade); `migracao.bruto.purgado` (organização, registros); `envio.suprimido` com motivo `migracao` (OIK-ADR-0008). Todos com `request_id` e organização, **sem dado pessoal e sem id de origem do inChurch em log** (só o id interno da preparação, `OBS-002`).
- **Métricas a expor:** duração por fase e por execução; registros por entidade em cada fase (extraídos, mapeados, rejeitados, carregados); conflitos abertos por igreja e por critério; decisões reaplicadas × invalidadas; diferença de contagem por igreja e vínculo; erros e esperas da fonte por execução; tamanho da área de preparação por organização e idade do bruto mais antigo.
- **Alertas a configurar:** execução falhou ou ficou parada numa fase além do tempo esperado; contagem divergente sem explicação num ensaio ou na virada; rejeições acima do limiar; alteração após o congelamento na reextração de conferência; qualquer envio criado para organização `em_migracao` (indica falha da supressão); bruto além de 90 dias após a virada (purga falhou); erro de credencial da fonte. Ferramentas e limiares no OIK-ADR-0010.
- **Cenário de incidente validado:** no 2º ensaio, a secretaria da Igreja B vê 12 membros ativos a menos no Oikora do que na fonte. Sem abrir código, o administrador abre o relatório da execução: `migracao.contagem.divergente` mostra a diferença em "membro/ativo" da Igreja B, e as diferenças não explicadas apontam 12 `migracao.registro.rejeitado` na fase de mapeamento com motivo `tipo_membro_sem_mapeamento`, valor de origem "Membro em experiência". Ele adiciona o valor ao mapeamento da organização e roda novo ensaio: a contagem fecha e as decisões já tomadas são reaplicadas sem retrabalho. Como não há plantão, as execuções rodam em horário comercial com o operador acompanhando; a falha fica no relatório, não no log de madrugada.

## Lacunas conhecidas

- **Acesso e conteúdo da fonte:** não se sabe se a API está liberada no plano/perfil nem o que ela e o XLSX entregam (linha do tempo, funções com datas, consentimentos, fotos, matrícula, limite de requisições, ids estáveis); a documentação `docs.inchurch.com.br` não pôde ser lida nesta sessão. Resolvido pela sondagem (item 3) antes do 1º ensaio. Se nenhuma fonte trouxer algum dado de FR-027, volta ao Product Owner como pergunta (o que se aceita perder) antes da virada.
- `OPEN-016` (matrícula), `OPEN-003` (obrigatórios) e `OPEN-013` (bloqueia a virada) continuam abertas no produto.
- Mapeamento de Células, Kids e Eventos depende dos documentos de produto desses módulos.
- Prazo esperado de cada fase para os alertas de "execução parada" só será conhecido no 1º ensaio (OIK-ADR-0010).
