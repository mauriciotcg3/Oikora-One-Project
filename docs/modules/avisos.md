# Módulo: Avisos

**Última atualização:** 2026-11-07
**Arquivos principais:** `api/app/Modules/Avisos/**`, `api/config/oikora.php`, `compose.yaml` (serviço `worker-codigos`)

## Visão geral
Entrega mensagens para fora do sistema. Hoje cuida só dos códigos de identidade: 2FA e recuperação de senha, pela fila dedicada `codigos`. Os três canais usam adapters falsos, sem provedor real. Avisos de serviço e convites ainda não existem.

## Regras de negócio ativas
| ID | Regra | Onde está implementada | Origem |
|---|---|---|---|
| RN-21 | Códigos vão pelo canal escolhido, com SMS de reserva. | `Application/EntregarCodigo.php` | OIK-ADR-0008 §11, §12 |
| RN-21.1 | `Avisos::registrar` grava o aviso e um envio `pendente` por canal na transação do fato; o job sai só depois do commit e repetir com a mesma (tipo, referência, destinatário, chave) não duplica. Convite nasce em e-mail e WhatsApp. | `RegistrarAvisos`; `TipoAviso::canais` | OIK-SPEC-0001 T-17; OIK-ADR-0008 §2, §4 |
| RN-21.2 | O job trava o envio e não chama o provedor de novo depois de `enviado`. Erro temporário: novas tentativas em 1 min, 5 min, 30 min e 2 h; na 5ª, ou em erro definitivo, `falhou_definitivo`. | `EntregarAviso`; `EnviarAviso` | OIK-SPEC-0001 T-17; OIK-ADR-0008 §3 |
| RN-22 | Com a organização `em_migracao`, o aviso é gravado, nenhum envio é criado e o log registra `envio.suprimido` com motivo `migracao`. | `RegistrarAvisos` | OIK-SPEC-0001 T-17; OIK-ADR-0009 §10 |
| — | O WhatsApp de código usa o número da plataforma e o template `authentication`. | `Application/EntregarCodigo.php` | OIK-ADR-0008 §10 |
| — | O SMS só é usado quando o WhatsApp falha de vez ou esgota as tentativas, e só para +55. | `Application/EntregarCodigo.php` | OIK-ADR-0008 §3, §11 |
| — | O SMS tem limite de 3 por hora e 10 por dia por número. | `Application/LimiteDeSms.php` | OIK-ADR-0008 §11 |
| — | Erro temporário tem até 2 tentativas em 2 minutos, com o mesmo código. E-mail esgotado vira `falhou_definitivo`. | `Application/Jobs/EnviarCodigo.php` | OIK-ADR-0008 §3 |
| — | O payload do job leva só o id. Código e destino são lidos na hora, pelo Contract de Identidade. | `Application/Jobs/EnviarCodigo.php` | OIK-ADR-0008 §3 |

## Interfaces / contratos expostos
| Contract | Uso |
|---|---|
| `Contracts/EnvioCodigo::enviar(int $codigoId)` | Identidade pede a entrega; o job vai para a fila `codigos`. |

Cada canal implementa `CanalDeEmail`, `CanalDeWhatsApp` ou `CanalDeSms` e classifica o erro em `FalhaTemporariaDeEnvio` ou `FalhaDefinitivaDeEnvio`.

## Dependências
- **Depende de:** Contract `CodigosDeIdentidade`, de Identidade.
- **É usado por:** módulo Identidade, pelo Contract `EnvioCodigo`.

## Decisões arquiteturais relevantes
| ADR | Como afeta este módulo |
|---|---|
| OIK-ADR-0008 | Fila `codigos` com worker dedicado, tentativas, classificação de erros, canais e limites. |
| OIK-ADR-0010 | Adapters falsos nos testes, sem chamada a provedor real. |

## Observabilidade — o que o suporte deve saber
- **Estado do envio:** fica em `codigos_verificacao` (`envio_estado`, `envio_id_externo`, `envio_erro`), porque códigos não gravam `avisos` nem `envios`.
- **Eventos logados:** `avisos.codigo.envio`, com id, canal, estado e motivo, sem código nem destino. O `request_id` chega ao job pelo Context do Laravel.
- **Erros comuns:** `numero_sem_whatsapp`, `limite_sms`, `sem_destino`, `erro_inesperado`.
- **Métricas expostas:** nenhuma ainda (T-06).

## Pontos em aberto / riscos conhecidos
- ⚠️ Os adapters são falsos: sem os reais, nenhum código sai de verdade. Antes de qualquer deploy, é preciso ligar os provedores.
- ⚠️ `CanalFalso` grava o código em claro em `storage/app/private/codigos-dev.log`, só com `APP_ENV=local`.
- ⚠️ Avisos e Identidade dependem um do outro pelos Contracts: Identidade pede o envio, Avisos lê o código.
- ⚠️ O limite de SMS por IP do OIK-ADR-0008 §11 falta: o job não conhece o IP de quem pediu.

## Changelog
| Data | Mudança | Origem (task/spec/adr) |
|---|---|---|
| 2026-11-07 | Criação do módulo, com a entrega de códigos e adapters falsos. | OIK-SPEC-0001 T-11 (recorte) |
| 2026-11-10 | Caixa de saída transacional (`avisos`, `envios` particionada), catálogo `TipoAviso`, tentativas, supressão em migração e `envios:varrer`. | OIK-SPEC-0001 T-17 |
