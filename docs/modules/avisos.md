# Módulo: Avisos

**Última atualização:** 2026-11-20
**Arquivos principais:** `api/app/Modules/Avisos/**`, `api/config/oikora.php`, `compose.yaml` (serviços `worker-codigos` e `worker-geral`)

## Visão geral
Entrega mensagens para fora do sistema. Códigos de identidade (2FA e recuperação de senha) saem pela fila dedicada `codigos`, com adapters reais ligados por `OIKORA_CODIGOS_PROVEDORES=real`. Convites e avisos de serviço passam pela caixa de saída (`avisos`, `envios`), com regras de canal por categoria, janela diurna, reenvio pelo próximo canal, preferências e descadastro; os adapters reais de aviso (Postmark e o número de WhatsApp da organização na Meta) ligam com `OIKORA_AVISOS_PROVEDORES=real`. Com `falso` (padrão em dev e testes), nada sai para a rede.

## Regras de negócio ativas
| ID | Regra | Onde está implementada | Origem |
|---|---|---|---|
| RN-21 | Códigos vão pelo canal escolhido, com SMS de reserva. | `Application/EntregarCodigo.php` | OIK-ADR-0008 §11, §12 |
| RN-21.1 | `Avisos::registrar` grava o aviso e os envios na transação do fato; o job sai só depois do commit e repetir com a mesma (tipo, referência, destinatário, chave) não duplica. | `RegistrarAvisos`; `CaixaDeSaida::criarEnvio` | OIK-SPEC-0001 T-17; OIK-ADR-0008 §2, §4 |
| RN-21 (convite) | Convite sai na hora, em todos os canais com contato: e-mail e WhatsApp da organização (só com número ativo e celular marcado como WhatsApp). Sem número ativo, só e-mail: o número da plataforma é só de códigos. É obrigatório: não se desliga. | `CaixaDeSaida::canaisIniciais`; `TipoAviso` | OIK-SPEC-0001 T-18; OIK-ADR-0008 §10, §12 |
| RN-21 (serviço) | Aviso de serviço sai por **um** canal externo: o preferido da conta ou, por padrão, o WhatsApp da organização, senão o e-mail. Falha definitiva, ou WhatsApp só `enviado` há 30 min, gera envio pelo próximo canal (uma vez por canal). É obrigatório: a pessoa troca o canal, mas não desliga o último. | `CaixaDeSaida`; `ReenviarPeloProximoCanal`; `envios:varrer`; `PreferenciasDeAviso` | OIK-SPEC-0001 T-18; OIK-ADR-0008 §12, §13 |
| RN-21.3 | Serviço só sai para canal externo entre 8h e 21h no fuso da organização; fora disso o envio fica `agendado` para as 8h e o `envios:varrer` o solta. A regra vale também para nova tentativa e reenvio. Convite e código saem sempre na hora. | `JanelaDiurna`; `EntregarAviso`; `VarrerEnvios` | OIK-SPEC-0001 T-18; OIK-ADR-0008 §14 |
| RN-21.4 | Pessoa sem conta se descadastra pelo link assinado do e-mail (`POST /api/v1/descadastro`, RFC 8058) ou respondendo "SAIR" no WhatsApp; fica `descadastrado_em` no contato da ficha. O descadastro vale para tipos não obrigatórios: convite e serviço continuam saindo. E-mail não obrigatório leva `List-Unsubscribe` e `List-Unsubscribe-Post`. | `Descadastrar`; `ContatosDaPessoa`; `PostmarkAvisos` | OIK-SPEC-0001 T-18; OIK-ADR-0008 §9, §13 |
| RN-21.5 | O e-mail sai como "Nome da organização via Oikora" no endereço da plataforma, com responder-para no `email_contato` da organização; o template leva só o nome da organização e o link, nunca dado da ficha. | `PostmarkAvisos`; `TextosDeAviso`; `resources/views/emails/avisos/*` | OIK-SPEC-0001 T-18; OIK-ADR-0008 §9 |
| RN-21.2 | O job trava o envio e não chama o provedor de novo depois de `enviado`. Erro temporário: novas tentativas em 1 min, 5 min, 30 min e 2 h; na 5ª, ou em erro definitivo, `falhou_definitivo`. | `EntregarAviso`; `EnviarAviso` | OIK-SPEC-0001 T-17; OIK-ADR-0008 §3 |
| RN-22 | Com a organização `em_migracao`, o aviso é gravado, nenhum envio é criado e o log registra `envio.suprimido` com motivo `migracao`. | `RegistrarAvisos` | OIK-SPEC-0001 T-17; OIK-ADR-0009 §10 |
| RN-23 | Webhooks autenticados de Meta, Postmark e Twilio respondem antes do processamento e levam à fila `webhooks` somente o evento normalizado. A atualização é idempotente: um evento atrasado não regride o estado; envio desconhecido não produz efeito; falha definitiva marca o contato e reclamação de spam o descadastra. | `ReceberWebhook`; `ProcessarEventoWebhook`; `Infrastructure/Webhooks/*` | OIK-SPEC-0001 T-19; OIK-ADR-0008 §15 |
| — | Quem tem `avisos.whatsapp.gerir` liga o número oficial por OAuth redirecionado da Meta. `waba_id`, `phone_number_id`, nome e catálogo de templates ficam por organização; o token fica cifrado e é apagado ao desligar. Ligar/desligar são auditados sem credencial. | `WhatsappNumeroController`; `CadastroNumeroMeta`; `NumerosWhatsAppPostgres` | OIK-SPEC-0001 T-21 |
| — | O WhatsApp de código usa o número da plataforma e o template `authentication`. | `Application/EntregarCodigo.php` | OIK-ADR-0008 §10 |
| — | O SMS só é usado quando o WhatsApp falha de vez ou esgota as tentativas, e só para +55. | `Application/EntregarCodigo.php` | OIK-ADR-0008 §3, §11 |
| — | O SMS tem limite de 3 por hora e 10 por dia por número. | `Application/LimiteDeSms.php` | OIK-ADR-0008 §11 |
| — | Erro temporário tem até 2 tentativas em 2 minutos, com o mesmo código. E-mail esgotado vira `falhou_definitivo`. | `Application/Jobs/EnviarCodigo.php` | OIK-ADR-0008 §3 |
| — | O payload do job leva só o id. Código e destino são lidos na hora, pelo Contract de Identidade. | `Application/Jobs/EnviarCodigo.php` | OIK-ADR-0008 §3 |
| — | Cada adapter real classifica o erro do provedor: timeout, 429, 5xx e limites de taxa da Meta são temporários; número sem WhatsApp, e-mail inválido ou inativo, template rejeitado, credencial inválida e conta sem pagamento são definitivos. Credencial ausente é definitiva (`provedor_nao_configurado`), sem chamar a rede. | `Infrastructure/Canais/{Postmark,MetaWhatsApp,Twilio}/*Codigos.php` | OIK-SPEC-0001 T-11; OIK-ADR-0008 §3 |
| — | Caixa de avisos (`GET /avisos`, `GET /avisos/nao-lidos`, `POST /avisos/{id}/lido`, `POST /avisos/lidos`): só os avisos da conta na organização ativa, paginados (25/50/100); o texto é montado na leitura (`TextosDaCaixa`, só com o nome da organização). Preferências em `GET|PUT /conta/preferencias-aviso`. | `CaixaAvisosController`; `PreferenciasAvisoController` | OIK-SPEC-0001 T-20; OIK-ADR-0008 §7, §13 |
| — | `uso_mensageria` soma cada envio aceito por mês (fuso da organização), canal e categoria, com custo pela tabela `oikora.avisos.precos` (zero por ora). Com `limite_whatsapp_mensal`, 80% avisa quem tem `organizacao.configurar` no escopo da organização (`servico.limite_whatsapp_alerta`) e 100% avisa o corte (`servico.limite_whatsapp_atingido`), um por mês; daí serviço e automáticas saem por e-mail e convite continua. Os avisos do limite só usam e-mail. Sem limite, não há corte. | `UsoDeMensageria`; `LimiteDeWhatsApp`; `CaixaDeSaida` | OIK-SPEC-0001 T-20; OIK-ADR-0008 §16 |

## Interfaces / contratos expostos
| Contract | Uso |
|---|---|
| `Contracts/EnvioCodigo::enviar(int $codigoId)` | Identidade pede a entrega; o job vai para a fila `codigos`. |
| `Contracts/Avisos::registrar(TipoAviso, list<Destinatario>, Referencia, chave)` | Outros módulos registram convite e avisos de serviço na transação do fato. |
| `POST /api/v1/descadastro?o&p&c&signature` | Descadastro em um clique, sem sessão; responde 204 sempre que a assinatura vale. |
| `GET /api/v1/webhooks/meta` | Confirma a inscrição da Meta somente com o token de verificação configurado. |
| `POST /api/v1/webhooks/{meta\|postmark\|twilio}` | Recebe status sem sessão, valida a credencial do provedor e responde 200 após enfileirar o evento normalizado; credencial inválida responde 401. |

Cada canal de código implementa `CanalDeEmail`, `CanalDeWhatsApp` ou `CanalDeSms`; cada canal de aviso, `CanalDeAviso`. Todos classificam o erro em `FalhaTemporariaDeEnvio` ou `FalhaDefinitivaDeEnvio`. Portas internas: `NumerosWhatsApp` (número ativo da organização; até a T-21, `NumerosWhatsAppIndisponiveis`), `DestinosDeAviso` e `LinksDeDescadastro`.

## Dependências
- **Depende de:** Contracts `CodigosDeIdentidade` e `ContatosDaConta` (Identidade), `ConsultaOrganizacoes` e `PerfilDeEnvio` (Organização: nome, fuso e contato de resposta) e `ContatosDaPessoa` (Pessoas: contato da ficha, falha de entrega e descadastro).
- **É usado por:** Identidade (`EnvioCodigo`) e, nas próximas tasks, convite (T-22), exportação (T-31) e transferência (T-34), por `Avisos`.

## Decisões arquiteturais relevantes
| ADR | Como afeta este módulo |
|---|---|
| OIK-ADR-0008 | Fila `codigos` com worker dedicado, tentativas, classificação de erros, canais e limites. |
| OIK-ADR-0010 | Adapters falsos nos testes, sem chamada a provedor real. |

## Observabilidade — o que o suporte deve saber
- **Estado do envio:** fica em `codigos_verificacao` (`envio_estado`, `envio_id_externo`, `envio_erro`), porque códigos não gravam `avisos` nem `envios`.
- **Eventos logados:** `avisos.codigo.envio`, com id, canal, estado e motivo, sem código nem destino. `avisos.provedor.chamada`, por chamada a provedor real: provedor, status HTTP, estado, motivo e duração em ms, sem destino, código ou token. O `request_id` chega ao job pelo Context do Laravel.
- **Eventos de aviso:** `envio.enviado`, `envio.falhou_temporario`, `envio.falhou_definitivo`, `envio.reenviado_pelo_proximo_canal`, `envio.sem_proximo_canal`, `envio.suprimido`, `envios.reenfileirados` e `avisos.descadastro` (origem `link` ou `whatsapp`), sem destino.
- **Eventos de webhook:** `webhook.assinatura_invalida` (provedor), `webhook.envio_desconhecido` (provedor e evento), `webhook.envio_atualizado` e `webhook.reclamacao_spam_processada`; nenhum inclui payload, destino ou credencial.
- **Erros comuns:** `sem_numero_whatsapp`, `numero_sem_whatsapp`, `limite_sms`, `sem_destino`, `erro_inesperado`, `provedor_nao_configurado`, `credencial_invalida`, `timeout`, `<provedor>_indisponivel` e `<provedor>_<código>` para erro definitivo sem motivo próprio.
- **Configuração:** `config/services.php` (`postmark`, `meta_whatsapp`, `twilio`), lida das variáveis `POSTMARK_*` (inclusive `POSTMARK_REMETENTE_AVISOS`), `META_*` e `TWILIO_*`; segredos só no ambiente. `OIKORA_AVISOS_PROVEDORES` liga os canais reais de aviso.
- **Métricas expostas:** nenhuma ainda (T-06).

## Pontos em aberto / riscos conhecidos
- ⚠️ Os adapters reais de código foram testados só com HTTP falso. Antes do deploy: credenciais em cada ambiente, template `authentication` aprovado na conta da plataforma e um envio de verificação por canal.
- ⚠️ `CanalFalso` grava o código em claro em `storage/app/private/codigos-dev.log`, só com `APP_ENV=local`.
- ⚠️ Avisos e Identidade dependem um do outro pelos Contracts: Identidade pede o envio, Avisos lê o código.
- ⚠️ O limite de SMS por IP do OIK-ADR-0008 §11 falta: o job não conhece o IP de quem pediu.
- ⚠️ Sem `whatsapp_numeros` (T-21), nenhuma organização tem número ativo: na prática, convite e serviço saem só por e-mail. A confirmação de entrega já entra pelo webhook; associar mensagens recebidas como "SAIR" ao número da organização depende da T-21.
- ⚠️ Os templates `convite_painel` e `servico_*` da Meta ainda precisam ser aprovados por número de organização.
- ✅ (2026-11-23) Rota `GET|PUT /conta/preferencias-aviso` entregue na T-20. Antes: preferências só com a regra; a rota ficava para a tela de conta.

## Changelog
| Data | Mudança | Origem (task/spec/adr) |
|---|---|---|
| 2026-11-25 | OAuth do número oficial de WhatsApp da organização, token cifrado, catálogo de templates e desligamento seguro. | OIK-SPEC-0001 T-21 |
| 2026-11-23 | Caixa de avisos, preferências, `uso_mensageria` e limite mensal de WhatsApp; Contract `AvisosDaPessoa` para o removedor. | OIK-SPEC-0001 T-20, T-35 |
| 2026-11-07 | Criação do módulo, com a entrega de códigos e adapters falsos. | OIK-SPEC-0001 T-11 (recorte) |
| 2026-11-10 | Caixa de saída transacional (`avisos`, `envios` particionada), catálogo `TipoAviso`, tentativas, supressão em migração e `envios:varrer`. | OIK-SPEC-0001 T-17 |
| 2026-11-17 | Adapters reais de código (Postmark, Meta WhatsApp, Twilio) com classificação de erro e log por chamada; escolha por `oikora.codigos.provedores`. | OIK-SPEC-0001 T-11 |
| 2026-11-17 | Regras de canal por categoria, janela diurna, reenvio pelo próximo canal, `preferencias_aviso`, descadastro (link RFC 8058 e "SAIR") e adapters reais de aviso (Postmark, WhatsApp da organização). | OIK-SPEC-0001 T-18 |
| 2026-11-20 | Webhooks autenticados de Meta, Postmark e Twilio, processamento idempotente na fila `webhooks`, efeitos de falha no contato e payload bruto descartado. | OIK-SPEC-0001 T-19 |
