# Módulo: Organização

**Última atualização:** 2026-11-17
**Arquivos principais:** `api/app/Modules/Organizacao/**`, `api/app/Shared/Arquivos/**`, `api/database/migrations/*organizacoes*`, `api/database/migrations/*igrejas*`, `api/config/filesystems.php` (disco `arquivos`)

## Visão geral
Dono da organização (o cliente, com estado, fuso e marca), das regionais e das igrejas locais. Expõe ao painel a configuração da organização (nome, fuso, cor primária, contato de resposta, limite de WhatsApp, modo de matrícula e logo) e o cadastro e a edição das igrejas. O logo vai e volta só por URL assinada de curta duração.

## Regras de negócio ativas
| ID | Regra | Onde está implementada | Origem |
|---|---|---|---|
| — | A configuração da organização pede `organizacao.configurar` com escopo organização; o modo de matrícula pede `pessoas.configurar` (U-18). A leitura (`GET /painel/organizacao`) serve a todo o painel, para o tema. | `ConfigurarOrganizacaoAction`; `OrganizacaoController` | OIK-SPEC-0001 T-24; E-04 |
| — | A cor primária precisa de contraste AA (4,5:1) com o texto branco do tema; sem ele, 422 `contraste_insuficiente`. O fuso precisa ser um identificador IANA (422 `fuso_invalido`). | `Domain/CorPrimaria`; `Domain/Fuso` | OIK-ADR-0006 §7; OIK-ADR-0008 §14 |
| RN-05 | O modo `automatica` é aceito na configuração, mas passar a Membro continua só manual enquanto o formato estiver em aberto (U-07): Pessoas recusa com `matricula_automatica_indisponivel`. | `ConfigurarOrganizacaoAction`; Pessoas `AlterarVinculoAction` | OIK-SPEC-0001 T-24, T-28 |
| — | Logo: PNG, JPEG ou WebP até 1 MB, enviado pelo navegador direto ao armazenamento por URL assinada (5 min); só vale quando o caminho emitido para a organização volta no `PUT` e o arquivo existe. A leitura também é por URL assinada. | `PedirEnvioDoLogo`; `Shared/Arquivos/ArquivosNoDisco` | OIK-ADR-0011 §7 |
| — | Igrejas: criar e editar pedem `igrejas.gerir` com escopo organização; não existe exclusão. A regional, opcional, precisa ser da organização. | `CriarIgrejaAction`; `EditarIgrejaAction`; `IgrejasController` | P FR-001; OIK-SPEC-0001 T-24 |
| — | Toda mudança é auditada só com os nomes dos campos: `organizacao.configurada` (com modo de matrícula antes e depois), `organizacao.igreja_criada`, `organizacao.igreja_atualizada`. Nome e contato, texto livre, ficam fora. | `Governanca::auditar` | OIK-ADR-0005 §2 |
| RN-17 | Provisionamento (`php artisan oikora:organizacao:provisionar {identificador}`), pela conexão `pgsql_plataforma` numa transação: organização `ativa` (ou `em_migracao` com `--em-migracao`), papéis prontos Administrador (todas as permissões) e Secretaria (Pessoas sem `pessoas.configurar`, sensíveis documentos, contatos e fé), primeira igreja, Pessoa do administrador e convite dele (Administrador, escopo organização, sem emissor). Grava `auditoria_plataforma` (comando, operador, motivo, organização). O identificador é único: repetir não cria nada. O seeder de dev usa o comando. | `ProvisionarOrganizacao` (Application e Console) | OIK-SPEC-0001 T-23 |

## Interfaces / contratos expostos
- **Painel (`/api/v1/painel`):** `GET|PUT /organizacao`, `POST /organizacao/logo` (`tipo_conteudo`, `tamanho`; 201 com `url`, `metodo`, `cabecalhos`, `caminho`, `expira_em`), `GET|POST /igrejas`, `PUT /igrejas/{id}`.
- **Contract `ConsultaOrganizacoes`:** organizações disponíveis, migração, `perfilDeEnvio` (uuid, nome, fuso e `email_contato`, para os avisos), `idPorUuid`, `uuidDe`, `modoMatricula`.
- **Contract `CatalogoIgrejas`:** igrejas e regionais por id e uuid, com resumo (uuid e nome).
- **Porta compartilhada `Shared/Arquivos/ArquivosPrivados`:** URL assinada de envio e de leitura, usada pelo logo e pela foto da ficha.

## Dependências
- **Depende de:** `AlcanceAtual` (Autorização) e `Auditoria`; `Shared/Arquivos`.
- **É usado por:** Avisos (remetente, fuso e contato de resposta), Pessoas (modo de matrícula e uuid para o prefixo da foto), Auditoria (fuso e igrejas na consulta), Autorização e Tenancy.

## Decisões arquiteturais relevantes
| ADR | Como afeta este módulo |
|---|---|
| OIK-ADR-0002 | Organização é o tenant; igrejas e regionais sob RLS, com FK composta. |
| OIK-ADR-0006 | Marca por organização (logo e cor com contraste AA). |
| OIK-ADR-0008 | Fuso da janela diurna e contato de resposta dos e-mails. |
| OIK-ADR-0011 | Arquivos em bucket privado, só por URL assinada de curta duração. |

## Observabilidade — o que o suporte deve saber
- **Eventos logados:** `authz.denied` com a permissão que faltou.
- **Erros comuns:** `contraste_insuficiente`, `fuso_invalido`, `logo_invalido` (caminho fora do prefixo da organização ou arquivo ainda não enviado), `regional_inexistente`, `igreja_inexistente` (404).
- **Configuração:** `OIKORA_ARQUIVOS_DISCO` (padrão `arquivos`, disco local servido com URL assinada em dev e testes) e `OIKORA_ARQUIVOS_VALIDADE_MINUTOS` (padrão 5).

## Pontos em aberto / riscos conhecidos
- ⚠️ Produção precisa do disco S3 (pacote `league/flysystem-aws-s3-v3` e variáveis do bucket); hoje só o disco local com `serve` está configurado.
- ⚠️ A URL assinada de `PUT` não limita o tamanho no armazenamento: o limite declarado é conferido na emissão. Com S3, avaliar POST com política de tamanho.
- ⚠️ O tema do painel ainda não lê o logo (pendente da T-37/T-39).

## Changelog
| Data | Mudança | Origem (task/spec/adr) |
|---|---|---|
| 2026-11-23 | Provisionamento da organização; `organizacoes.identificador`; `ConsultaOrganizacoes::limiteWhatsappMensal`. | OIK-SPEC-0001 T-23, T-20 |
| 2026-11-07 | `organizacoes`, `regionais` e `igrejas` sob RLS; Contracts `ConsultaOrganizacoes` e `CatalogoIgrejas`. | OIK-SPEC-0001 T-03, T-08 |
| 2026-11-17 | Configuração da organização (com `email_contato`), logo por URL assinada, cadastro e edição de igrejas com auditoria; `PerfilDeEnvio` para Avisos. | OIK-SPEC-0001 T-24 (e T-18) |
