# Módulo: Pessoas

**Última atualização:** 2026-11-08
**Arquivos principais:** `api/app/Modules/Pessoas/**`, `api/app/Shared/Cifra/**`, `api/config/oikora.php`

## Visão geral
O módulo Pessoas é dono do cadastro-base de pessoas, contatos e endereços de cada organização. Nesta primeira entrega ele define o catálogo dos campos da ficha, persiste o schema inicial e protege documentos e saúde com uma chave independente da aplicação. Ainda não expõe endpoints nem casos de uso de cadastro.

## Regras de negócio ativas
| ID | Regra | Onde está implementada | Origem |
|---|---|---|---|
| RN-10.1 | CPF, RG, país do documento, necessidades especiais e tipo sanguíneo são cifrados antes de chegar ao banco, usando chave própria e aceitando chaves anteriores durante rotação. | `Pessoa::$casts`; `CampoSensivelEncrypter` | OIK-SPEC-0001 T-09; OIK-ADR-0004 §8 |
| RN-10.2 | O CPF normalizado somente com dígitos gera um HMAC-SHA256, único por organização inclusive para pessoa na lixeira. | `Pessoa::booted`; migration `create_pessoas_tables` | OIK-SPEC-0001 T-09; OIK-ADR-0002 §7 |
| RN-10.3 | O nome de busca é gravado em minúsculas e sem acentos e possui índice trigram. | `Pessoa::booted`; índice `pessoas_nome_busca_trgm` | OIK-SPEC-0001 T-09; OIK-ADR-0002 §10 |
| RN-10.4 | Cada campo configurável da ficha pertence a uma categoria e a uma seção; documentos, saúde, contatos/endereço e fé/conversão permanecem separados. | `CampoFicha::categoria`; `CampoFicha::secao` | OIK-SPEC-0001 T-09; RN-10; OIK-ADR-0004 §10 |
| RN-10.5 | Pessoas, contatos e endereços pertencem a uma organização e são isolados pela RLS; referências entre essas tabelas usam organização e identificador. | migration `create_pessoas_tables` | OIK-SPEC-0001 T-09; OIK-ADR-0002 §4 e §6 |
| RN-09 | Toda consulta de Pessoa passa pelo filtro global de alcance (`pessoas.ver` na igreja atual da pessoa); sem alcance no contexto, a consulta falha com `authz.context_missing`. Ficha fora do alcance: 403; inexistente: 404. | `PessoaEscopoAlcance`; `LerFicha` | OIK-SPEC-0001 T-14; OIK-ADR-0004 §5 |
| RN-09.1 | A busca na denominação (nome, CPF exato, e-mail ou telefone) é a única consulta que sai do filtro e seleciona só `uuid`, `nome`, igreja e vínculo. | `BuscaDenominacaoPostgres`; guard `EscopoAlcanceTest` | OIK-SPEC-0001 T-14; OIK-ADR-0004 §6 |
| RN-10 | Campos de saúde, documentos, contatos/endereço e fé/conversão só entram no SELECT e no JSON da ficha para quem tem `pessoas.sensiveis.<categoria>.ver` na igreja da pessoa; sem `contatos.ver`, contatos e endereços nem são lidos. | `LerFicha` | OIK-SPEC-0001 T-14; OIK-ADR-0004 §7 |

## Interfaces / contratos expostos
Não há endpoint, Contract ou evento público nesta entrega. A interface operacional disponível é o comando `oikora:chaves:recifrar`, que regrava os campos sensíveis com a chave atual.

## Dependências
- **Depende de:** infraestrutura compartilhada `Shared/Cifra`, contexto de organização de `Shared/Tenancy` e tabelas `organizacoes`, `igrejas` e `acessos` já aprovadas na Spec.
- **É usado por:** nenhum outro módulo por interface pública nesta entrega; `acessos` recebe a FK composta para a Pessoa da mesma organização.

## Decisões arquiteturais relevantes
| ADR | Como afeta este módulo |
|---|---|
| OIK-ADR-0002 | PostgreSQL, RLS por organização, FKs compostas, unicidade do CPF por HMAC e busca trigram. |
| OIK-ADR-0004 | Categorias sensíveis e cifra de campo com chave separada da `APP_KEY`. |

## Observabilidade — o que o suporte deve saber
- **Eventos logados:** início, sucesso e falha da recifra por organização, com módulo, ação, organização, estado, duração e quantidade; nenhum dado de Pessoa é registrado.
- **Métricas expostas:** ainda não há backend de métricas definido; `duration_ms` e `count` permitem acompanhar a operação pelos logs estruturados.
- **Erros comuns e o que significam:** ausência ou formato inválido de `OIKORA_CHAVE_CAMPOS`; ausência de `OIKORA_CHAVE_HMAC_CPF`; falha ao decifrar indica que nenhuma chave configurada reconheceu o valor persistido.

## Pontos em aberto / riscos conhecidos
- ⚠️ A autorização e a projeção por categoria serão implementadas nas tasks de casos de uso e API; esta entrega oferece somente catálogo, persistência e cifra.
- ⚠️ A rotação é transacional por organização e pode concluir organizações anteriores antes de falhar em uma posterior; o comando é reexecutável.

## Changelog
| Data | Mudança | Origem (task/spec/adr) |
|---|---|---|
| 2026-11-08 | Criação inicial com schema, catálogo, HMAC, busca e rotação da cifra. | OIK-SPEC-0001 T-09 |
| 2026-11-10 | Filtro global de alcance, busca na denominação, ficha com projeção de sensíveis (`GET /painel/pessoas/{id}`, `GET /painel/busca`); rotinas de sistema por `AlcanceAtual::comoSistema`. | OIK-SPEC-0001 T-14 |
