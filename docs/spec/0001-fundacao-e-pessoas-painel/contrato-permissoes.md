# Contrato backend/frontend — autorização

**Estado:** aprovado na Onda 2 em 2026-11-07
**Autoridade:** o backend sempre decide; o frontend representa capacidades recebidas e melhora a experiência.

## Decisão de modelagem

Nesta spec, o modelo aprovado continua sendo `papel + atribuição + escopo`, conforme a ADR-0004. O conceito de perfil encontrado no OmniPlus não será introduzido agora: suas combinações reutilizáveis são representadas por papéis, e exceções individuais usam concessões temporárias da ADR-0012. Adotar uma camada adicional de perfil exigirá necessidade própria e nova decisão arquitetural.

## Vocabulário

- **Permissão:** chave versionada `recurso.acao`, definida no enum do backend.
- **Papel:** conjunto nomeado de permissões do catálogo.
- **Atribuição:** vínculo entre acesso, papel e escopo organizacional.
- **Concessão temporária:** exceção individual aprovada, explícita e válida por até 72 horas.
- **Capacidade efetiva:** união de atribuições com concessões ainda válidas, limitada à organização ativa e ao escopo.

## Bootstrap da sessão

`GET /api/v1/me` fornece conta, organização ativa e capacidades efetivas:

```json
{
  "data": {
    "conta": { "id": "uuid", "nome": "Nome" },
    "organizacao_ativa": { "id": "uuid", "nome": "Organização" },
    "autorizacao": {
      "capacidades": [
        {
          "permissao": "pessoas.ver",
          "escopo_tipo": "igreja",
          "escopo_ids": ["uuid-da-igreja"]
        }
      ]
    }
  }
}
```

- trocar a organização ativa invalida o estado local e refaz `/me`;
- ausência de `autorizacao` é falha de contrato, nunca acesso total;
- o frontend não persiste capacidades em `localStorage`;
- toda operação protegida é revalidada no backend.

## Catálogo, papéis e atribuições

| Método e rota | Uso | Permissão |
|---|---|---|
| `GET /api/v1/painel/permissoes` | Catálogo agrupado, com marcação de categorias sensíveis e elegibilidade para solicitação | acesso ativo |
| `GET /api/v1/painel/papeis` | Listar papéis e suas permissões | `papeis.gerir` |
| `POST /api/v1/painel/papeis` | Criar papel com chaves do catálogo | `papeis.gerir` |
| `PUT /api/v1/painel/papeis/{id}` | Alterar papel com anti-escalada e travas do administrador | `papeis.gerir` |
| `POST /api/v1/painel/acessos/{id}/atribuicoes` | Criar atribuição de papel e escopo | `acessos.gerir` |
| `DELETE /api/v1/painel/acessos/{id}/atribuicoes/{atribuicao}` | Remover atribuição respeitando o último administrador | `acessos.gerir` |

O catálogo devolve `chave`, `rotulo`, `grupo`, `sensivel`, `categoria_sensivel` e `solicitavel`. A interface nunca reproduz o catálogo nem suas dependências localmente.

## Solicitações e concessões temporárias

| Método e rota | Uso |
|---|---|
| `POST /api/v1/painel/solicitacoes-permissao` | Solicitar permissões, escopo, justificativa e duração de 1 a 72 horas |
| `GET /api/v1/painel/solicitacoes-permissao` | Listar próprias solicitações; administrador pode pedir `visao=aprovaveis` |
| `POST /api/v1/painel/solicitacoes-permissao/{id}/cancelar` | Cancelar uma solicitação própria ainda pendente |
| `POST /api/v1/painel/solicitacoes-permissao/{id}/aprovar` | Aprovar integralmente a versão solicitada |
| `POST /api/v1/painel/solicitacoes-permissao/{id}/recusar` | Recusar com justificativa obrigatória |
| `POST /api/v1/painel/concessoes-permissao/{id}/revogar` | Revogar uma concessão antes do vencimento |

Corpo mínimo da solicitação:

```json
{
  "permissoes": [
    {
      "chave": "pessoas.sensiveis.saude.ver",
      "categoria_sensivel": "saude",
      "escopo_tipo": "igreja",
      "escopo_id": "uuid-da-igreja"
    }
  ],
  "duracao_horas": 24,
  "justificativa": "Atendimento temporário"
}
```

A decisão exige administrador, `permissoes.solicitacoes.aprovar` e teto suficiente para todas as permissões e escopos. Autoaprovação é proibida. Governança (`papeis.gerir`, `acessos.gerir`, `perfis.gerir`) e exportação não são solicitáveis. O aprovador não altera o pedido: aprova integralmente ou recusa. A decisão é atômica e idempotente; concorrência ou versão desatualizada retorna conflito.

## Erros estáveis

| HTTP | Código | Comportamento da interface |
|---|---|---|
| 401 | `nao_autenticado` | Encerrar sessão local e redirecionar ao login. |
| 403 | `sem_permissao` | Mostrar acesso negado; não converter em lista vazia. |
| 403 | `escalada_bloqueada` | Explicar que a concessão excede o teto do aprovador. |
| 403 | `autoaprovacao_negada` | Informar que outro administrador deve decidir. |
| 409 | `solicitacao_permissao_alterada` | Recarregar e pedir nova revisão; não repetir a mutation. |
| 422 | `permissao_nao_solicitavel` | Marcar a permissão recusada pelo catálogo. |
| 422 | `permissao_fora_do_teto` | Identificar a permissão ou o escopo fora do teto. |
| 422 | `duracao_concessao_invalida` | Exigir duração entre 1 e 72 horas. |
| 422 | `ultimo_administrador` | Preservar o último administrador. |

Todo erro inclui `request_id`; validações de campo seguem o formato 422 do Laravel.

## Comportamento frontend

A integração usa a camada compartilhada do projeto e uma API pequena:

```ts
can(permission: PermissionKey, scope?: ScopeRef): boolean
canAny(permissions: PermissionKey[], scope?: ScopeRef): boolean
canAll(permissions: PermissionKey[], scope?: ScopeRef): boolean
```

- `ProtectedRoute` controla a navegação e `Can` controla ações visuais;
- menus refletem as capacidades, sem constituir barreira de segurança;
- botões críticos podem ficar desabilitados com explicação;
- mutations sempre tratam 403/409/422;
- nenhuma regra depende de Fuse, nome de papel, rota ou posição de menu.

## Casos mínimos de contrato

1. catálogo identifica permissões não solicitáveis e categorias sensíveis;
2. ator não atribui papel, permissão ou escopo superior ao próprio;
3. revogar uma atribuição ou concessão muda `/me` e a próxima operação;
4. acesso direto por URL é negado no backend e representado no frontend;
5. troca de organização não reaproveita capacidades da anterior;
6. solicitação expirada ou revogada não participa do `Alcance`;
7. duas decisões concorrentes geram no máximo uma concessão;
8. solicitação, decisão, expiração e revogação produzem auditoria completa;
9. sensíveis aparecem por categoria explícita no pedido, revisão e auditoria;
10. justificativas e conteúdo sensível não aparecem em logs técnicos.
