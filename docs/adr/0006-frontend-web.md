# ADR-0006: Frontend web — painel e área do membro (SPA Vite com duas entradas + MUI)

**Status:** Aceito — após confirmação do usuário (2026-10-19)
**Data:** 2026-10-19
**Contexto do projeto:** Oikora One — plataforma da denominação (3 igrejas, ~520 membros) que substitui o inChurch módulo a módulo, com plano de atender outras igrejas.
**Identificação:** Produto `OIK-ADR-0006` · Governança `ADR-0028`
**Fonte da verdade:** `oikora-one:docs/adr/0006-frontend-web.md`

## Contexto
O OIK-ADR-0001 fixou React com TypeScript em **duas entradas web** — painel administrativo, e área do membro com os formulários públicos — que compartilham componentes e cliente de API, consumindo **somente** `/api/v1`, num único repositório; descartou Filament, Livewire e Inertia e deixou para este ADR bundler, roteamento, server state, biblioteca de componentes/tabelas (o custo de montar tabelas, filtros e ações em massa sem Filament seria "mitigado pela biblioteca de componentes do OIK-ADR-0006"). O OIK-ADR-0003 fixou Sanctum em modo SPA (cookie `HttpOnly`, CSRF, nenhum token em JavaScript), a **elevação** do painel (senha + 2FA, 2h sem uso / 12h no total), a organização ativa **guardada no servidor** e painel, área do membro e API sob o mesmo domínio-pai. O OIK-ADR-0004 fixou que os API Resources montam a **projeção** pelo alcance e pelas categorias sensíveis, que a **política de campo** da área do membro é um serviço único (oculto, leitura, edição direta, edição com aprovação) para site e app, e que escrita recusada volta como **422 por campo**. O OIK-ADR-0005 deixou para cá as telas de auditoria, lixeira, desfazer e consentimento.

O documento de produto `docs/product/0001-fundacao-e-pessoas.md` traz o que as telas precisam suportar:

- **§5 / §13:** administrador e secretaria operam o painel; a pessoa com conta usa a área do membro (própria ficha e a de quem é responsável); o visitante anônimo só envia o formulário público. Pastores e demais papéis não operam o painel no MVP.
- **FR-014 / FR-033 / FR-034:** lista em tabela com 25/50/100 por página, filtros (aniversariantes do mês), ações em massa e exportação por download direto.
- **FR-006 / FR-007:** cadastro em uma única tela, "salvar e cadastrar outro", alerta de duplicidade durante o cadastro.
- **FR-008:** ficha por seções com percentual de completude conforme o vínculo; **FR-016:** mesclagem campo a campo.
- **FR-021 / FR-022:** configuração de cada campo para a área do membro; aprovação de alterações campo a campo.
- **FR-023:** formulários públicos ("visitante" e "aceitei Jesus") por link ou QR Code; **FR-035:** responsável vê e atualiza a ficha do dependente.
- **NFR-004:** pt-BR; **NFR-005:** área do membro funcionando bem no navegador do celular, e formulários públicos no celular; **NFR-006:** WCAG AA.
- `docs/analise/analise-e-plano-nova-plataforma.md` (§4, referência): no inChurch, listas de 6 cards por página, lixeira em cada card, Safari e iPhone limitados, sem alerta de duplicidade no cadastro.

Informado pelo usuário em 2026-10-19: **não há designer** — o visual vem de uma biblioteca pronta com ajuste de cor e logo; a área do membro e os formulários públicos mostram **logo e cor principal de cada organização**; o painel é usado **no computador**, e no celular basta consultar ficha e aprovar pendências; interface **só em pt-BR no MVP, mas com textos em arquivos de tradução** desde o início; a prévia do link do formulário público no WhatsApp **deve mostrar nome e logo da igreja**; **sem PWA nem offline** (o app das lojas cobre); a equipe já usou **MUI** e **Tailwind/shadcn**, mas tem **pouca experiência com React** e a IA escreverá boa parte do front. Equipe de 2–4 desenvolvedores + IA, sem plantão 24h, expansão para centenas de organizações em 2–3 anos.

## Opções consideradas
- **A — Um projeto Vite (SPA) com duas entradas** (`painel` e `membro`) num único pacote `web/`, em workspace com um pacote de contrato da API; MUI 7 + MUI X DataGrid (MIT).
- **B — Duas SPAs Vite em pacotes separados** (painel e membro), com pacote de UI compartilhado.
- **C — Framework com SSR (Next.js)** para área do membro e formulários públicos, e SPA para o painel.

Subdecisão de biblioteca de componentes, avaliada dentro de A: **MUI 7 + DataGrid MIT** × **shadcn/ui + TanStack Table + Tailwind** × **Mantine**. Fuse React (ADR-0017 da linha Elev) não entra: licença de template paga, que num SaaS multicliente pode exigir a estendida, e Redux no núcleo.

## Comparação

| Pilar | A — Vite, duas entradas | B — Duas SPAs separadas | C — SSR (Next.js) + SPA |
|---|---|---|---|
| Desempenho | Bom: entradas separadas mantêm o DataGrid fora do bundle do membro; o primeiro acesso no celular é mais lento que SSR, mitigado por split por rota e cache longo de assets. | Bom: igual a A. | Ótimo no primeiro acesso do membro (HTML pronto); toda página passa por um servidor Node a mais. |
| Simplicidade de manutenção | Ótimo: um `package.json`, um `tsconfig`, um lint e uma versão de cada biblioteca. | Aceitável: duas configurações a manter em sincronia e um pacote de UI a versionar. | Ruim: segundo runtime a operar sem plantão; cookie e CSRF atravessando um BFF; dois modelos (componentes de servidor e de cliente) para uma equipe com pouca experiência em React. |
| Boas práticas | Ótimo: segue o Frontend Standard (front único, feature = módulo Laravel) e o OIK-ADR-0001. | Bom: o FE-SCALE-001 desaconselha múltiplos pacotes sem métrica que justifique. | Aceitável: SSR é legítimo, mas reabre o OIK-ADR-0001 (servidor renderizando tela) e tira parte da sessão do Laravel. |
| Escalabilidade | Ótimo: arquivos estáticos em CDN, que não crescem com o número de organizações. | Ótimo: idem. | Aceitável: servidores Node escalam com o tráfego do membro. |
| Observabilidade / logs | Bom: um client HTTP envia `traceparent` e versão do build; erro do navegador correlacionado ao trace da API. | Bom: idem, em dois builds. | Aceitável: terceiro ponto de falha (renderização no Node) a instrumentar. |

| Pilar | MUI 7 + DataGrid MIT | shadcn/ui + TanStack Table + Tailwind | Mantine |
|---|---|---|---|
| Desempenho | Aceitável: a mais pesada (runtime Emotion); DataGrid só no bundle do painel. | Ótimo: Tailwind sem runtime. | Bom. |
| Simplicidade de manutenção | Ótimo: paginação no servidor (25/50/100, o limite da versão MIT), seleção para ações em massa e colunas configuráveis prontas — o FR-014. | Aceitável: componentes viram código próprio; tabela, filtros e seleção montados à mão, o que pesa com pouca experiência em React. | Bom: equipe não conhece. |
| Boas práticas (WCAG AA) | Bom: componentes acessíveis prontos. | Bom: Radix acessível, mas a acessibilidade do que for montado é responsabilidade própria. | Bom. |
| Escalabilidade (tema por organização) | Ótimo: tema criado em tempo de execução por organização. | Ótimo: variáveis CSS. | Bom. |
| Observabilidade / logs | Neutro. | Neutro. | Neutro. |

## Decisão
Adotar a opção **A** com **MUI 7 + MUI X DataGrid (MIT)**, com as regras abaixo.

1. **Estrutura.** Workspace pnpm na raiz do `Oikora-One-Project` com `api/` (Laravel), `web/` (as duas entradas) e `packages/api-contrato/`; o app das lojas entra como `app/` no OIK-ADR-0007. Nomes finais de pastas na Spec. `web/` é um único projeto Vite (React 19+, TypeScript strict; versões na Spec) com duas entradas HTML — `painel` e `membro` (área do membro + formulários públicos) — e duas raízes em `app/` (roteador, providers, Error Boundary de cada entrada). Features seguem o Frontend Standard (`features/<Modulo>/` = módulo Laravel, Public API em `index.ts`, `shared/` sem dono de negócio); a separação dos bundles vem do grafo de imports, e um orçamento de tamanho por entrada é verificado no CI (OIK-ADR-0010).
2. **SPA, sem SSR.** Nenhuma tela é renderizada no servidor. Exceção única e estreita: a URL do formulário público é servida pelo Laravel como a casca HTML da entrada `membro` com `<title>` e meta `og:` (nome e logo da igreja) preenchidos; o corpo renderiza no navegador. A rota/roteamento dessas URLs até o Laravel fica no OIK-ADR-0011.
3. **Roteamento:** TanStack Router. Filtros, página, tamanho de página e ordenação das listas ficam na URL, com parâmetros tipados e validados; links e "voltar" preservam a lista. Rotas carregam features por import dinâmico (FE-PERF-002).
4. **Dados:** TanStack Query para estado de servidor; chaves de consulta incluem a organização ativa. Sem Redux, Zustand ou estado global; Context só em `app/` para sessão, organização ativa e tema. Mutations invalidam as consultas da feature dona.
5. **Formulários:** React Hook Form + Zod, só para experiência (FE-SEC-001). Um helper de `shared/` converte o 422 do Laravel (`errors` por caminho, ex.: `contatos.0.valor`) em erro no campo, leva o foco ao primeiro erro e mostra num resumo os erros de campos que não estão na tela. Mensagens do servidor chegam no idioma da conta; as do cliente vêm dos arquivos de tradução.
6. **Componentes e acessibilidade:** MUI 7 e MUI X DataGrid na versão MIT (sem Fuse, sem licença Pro); primitivos de design em `shared/ui/` com um `index.ts` por componente. Meta WCAG 2.2 AA (NFR-006), com `eslint-plugin-jsx-a11y`; ferramenta de teste automatizado de acessibilidade no OIK-ADR-0010. Exclusão só em menu de ações, com confirmação (lixeira de 30 dias do OIK-ADR-0005).
7. **Marca por organização:** logo e cor primária vêm da API e o tema MUI é criado em tempo de execução na área do membro e nos formulários públicos; o backend recusa cor sem contraste AA com o texto do tema. O painel usa o tema Oikora com o logo da organização.
8. **Contrato tipado com a API:** o Laravel gera o OpenAPI com Scramble; o `openapi.json` é versionado no repositório; `openapi-typescript` gera os tipos e um cliente fino tipado (`openapi-fetch`) fica em `packages/api-contrato/`, sem React e sem DOM, envolvido por `shared/http/` (FE-COMM-001). Campos de projeção (sensíveis, política de campo) são opcionais nos tipos, porque podem não vir. Verificação de "contrato gerado = contrato versionado" no CI (OIK-ADR-0010).
9. **Autorização na tela — o servidor decide, o cliente reflete.** Cada Resource de recurso editável devolve `meta.acoes` (ações permitidas sobre aquele registro, ex.: `editar`, `excluir`, `mesclar`) e, na área do membro, `meta.campos` com o modo de cada campo (`leitura`, `edicao_direta`, `edicao_aprovacao`; campo oculto não vem). Campo com aprovação mostra o valor pendente. `GET /me` devolve as capacidades que montam menus e a organização ativa. O cliente nunca deduz permissão por nome de papel nem esconde dado que o servidor enviou como forma de proteção; botão escondido é só conforto, o servidor revalida.
10. **Sessão (OIK-ADR-0003).** Todo pedido sai por `shared/http/` com `credentials: include` e `X-XSRF-TOKEN` lido do cookie. **419:** renova o cookie CSRF e repete uma vez. **401:** limpa o cache e vai ao login com a URL de volta. **Elevação expirada** (403 com `codigo: elevacao_necessaria`): diálogo de senha + 2FA sobre a tela atual, sem perder o formulário, e repetição do pedido. **Troca de organização:** chama a API, limpa todo o cache e recarrega a entrada. **Várias abas:** todo pedido envia `X-Organizacao-Esperada`; se divergir da organização ativa no servidor, a API responde 409 `organizacao_trocada` sem executar nada e a aba recarrega; `BroadcastChannel` avisa as outras abas. O cabeçalho só confere, nunca escolhe a organização.
11. **Contrato de erro:** 422 no formato padrão do Laravel (`message`, `errors` por campo); demais erros com `codigo` estável (`elevacao_necessaria`, `organizacao_trocada` etc.); lista fechada na Spec.
12. **Celular (NFR-005):** área do membro e formulários públicos desenhados primeiro para celular; painel para desktop, com consulta de ficha e aprovação de pendências utilizáveis no celular. Sem PWA nem offline. Navegadores atuais, incluindo Safari do iOS (Browserslist na Spec). Falha ao carregar um pedaço do bundle após deploy recarrega a página uma vez.
13. **Idioma:** i18next com namespaces por feature; só `pt-BR` no MVP; nenhum texto fixo em componente; novo idioma = novo arquivo. Datas e números por `Intl`; telefone com `libphonenumber-js` (E.164, OIK-ADR-0003).
14. **Observabilidade:** OpenTelemetry no navegador conforme o Frontend Standard §15; todo pedido envia `X-Cliente: painel|membro@<versão do build>`; Error Boundary mostra e registra o `trace_id`. Exportador, coletor e retenção no OIK-ADR-0010.
15. **Para o OIK-ADR-0007 reaproveitar:** `packages/api-contrato` (tipos, cliente e códigos de erro), os arquivos de tradução, as regras de `meta.campos`/`meta.acoes`, o tratamento de 401/409/419 e o tema por organização. Se o 0007 escolher web empacotado, reaproveita a entrada `membro`; se escolher React Native, reaproveita o pacote de contrato e as traduções.
16. **Telas herdadas do OIK-ADR-0005** (auditoria, lixeira, desfazer com conflitos campo a campo, consentimentos na área do membro) seguem estas regras; desenho de cada tela na Spec.
17. **Fora deste ADR:** tecnologia do app (OIK-ADR-0007); runner de testes, lint/format, enforcement de boundaries, orçamento de bundle e coletor OTel (OIK-ADR-0010); hospedagem, CDN, domínios, CSP e roteamento das URLs `og:` (OIK-ADR-0011); versões exatas, rotas, nomes de pastas e lista de códigos de erro (Spec).

## Justificativa
- **Simplicidade de manutenção:** com 2–4 desenvolvedores, pouca experiência em React e a IA escrevendo boa parte do código, um projeto com uma configuração e bibliotecas de uso amplo (MUI, TanStack, React Hook Form) reduz o que a equipe precisa saber. O DataGrid MIT entrega o FR-014 (25/50/100, seleção, colunas) sem montar tabela à mão — o custo que o OIK-ADR-0001 aceitou ao descartar o Filament. Next.js (C) traria um runtime Node para operar sem plantão.
- **Boas práticas:** mantém o OIK-ADR-0001 (só `/api/v1`, nenhuma tela no servidor) com uma exceção limitada ao `<head>` do formulário público; segue o Frontend Standard (front único, feature = módulo, client HTTP único); a autorização e a política de campo continuam decididas uma vez, na API (OIK-ADR-0004), e o cliente só lê `meta.acoes`/`meta.campos`. A trava `X-Organizacao-Esperada` fecha o risco de uma aba antiga gravar na organização errada, consequência da organização ativa guardada no servidor (OIK-ADR-0003).
- **Escalabilidade:** o front é estático e não cresce com o número de organizações; o tema por organização é dado, não build; o contrato gerado e sem DOM serve ao app (OIK-ADR-0007) sem trabalho paralelo.
- **Desempenho:** duas entradas mantêm o bundle do membro, usado no celular, sem o DataGrid e as telas do painel; split por rota e cache de assets compensam a ausência de SSR. O volume (~520 membros) não pressiona nenhuma opção.
- **Observabilidade:** um único client HTTP propaga trace e versão do build; os desvios de sessão (419, 401, elevação, organização trocada) têm código e evento próprios, e o suporte separa "build antigo" de "erro de regra" sem ler código.

## Consequências

**Positivas:**
- Uma só stack e configuração de front para painel, área do membro e formulários públicos.
- Tabelas, filtros e ações em massa prontas pelo DataGrid; listas com estado na URL.
- Tipos da API gerados do backend: mudança de contrato quebra a compilação do front, não o usuário.
- Regras de campo e ações refletidas da API, iguais no site e no app.
- Prévia do link do formulário com nome e logo da igreja, sem SSR.

**Negativas / trade-offs aceitos:**
- Primeiro acesso do membro no celular mais lento que com SSR; mitigado por split, cache e orçamento de bundle.
- MUI é mais pesada que Tailwind; o DataGrid fica restrito ao painel. Recursos Pro do DataGrid (fixar colunas, agrupamento, exportação no cliente) não estão disponíveis; exportação é no servidor (OIK-ADR-0004).
- A exceção do `og:` exige que o Laravel (ou a borda, no OIK-ADR-0011) sirva a casca HTML dessas URLs.
- A API precisa devolver `meta.acoes`/`meta.campos` e manter o OpenAPI do Scramble fiel às projeções condicionais; a Spec deve conferir os tipos gerados nas rotas com projeção.
- Cada pedido carrega `X-Organizacao-Esperada` e `X-Cliente`; a API precisa conferir o primeiro.

## Observabilidade — o que precisa ser instrumentado
- **Eventos a logar:** no navegador, `ui.elevation.requested`/`ui.elevation.completed`/`ui.elevation.failed`; `ui.csrf.retried` (após 419); `ui.session.expired` (401); `ui.org.switched` e `ui.org.mismatch` (409); `ui.chunk_load_failed` (com versão do build); `ui.form.rejected` (feature, ação, quantidade e nomes dos campos com erro, sem valores); erros do Error Boundary com `trace_id`. Na API, `api.org_mismatch` (organização esperada × ativa, conta, `X-Cliente`) e a versão do cliente em todo log de requisição. Sem senha, token, cookie ou valor de campo (FE-OBS-002).
- **Métricas a expor:** pedidos por `X-Cliente` e versão; taxa de 401/419/409/422 por entrada e rota; elevações pedidas e concluídas; `ui.chunk_load_failed` por versão; Web Vitals (LCP, INP, CLS) da entrada `membro` no celular; tamanho do bundle por entrada a cada build.
- **Alertas a configurar:** pico de `ui.chunk_load_failed` após deploy; taxa de 419 acima do normal (CSRF/domínio quebrado); qualquer aumento de `api.org_mismatch` além do esperado; LCP da entrada `membro` acima da meta; bundle de entrada acima do orçamento (bloqueia no CI). Ferramentas e limiares no OIK-ADR-0010.
- **Cenário de incidente validado:** de manhã, a secretaria diz que o "Salvar" da ficha não funciona desde ontem. O suporte filtra os logs pela conta e vê os pedidos com `X-Cliente: painel@1.4.0`, enquanto a versão publicada é a 1.5.0, recebendo 422 por campo em `apelido` (campo desconhecido), campo renomeado no deploy. A causa aparece sem ler código: aba aberta antes do deploy. Resposta: recarregar a página. Num segundo caso, uma secretaria com acesso a duas organizações diz que "a tela recarregou sozinha" ao salvar: o log mostra `api.org_mismatch` (esperada A, ativa B) logo depois de `ui.org.switched` em outra aba, e nada foi gravado — a trava funcionou como previsto.

## Lacunas conhecidas
- Desenho de cada tela (lista, ficha por seções, mesclagem, aprovação, configuração de campos, auditoria, lixeira, desfazer, consentimentos): Spec, sem designer; o visual segue os padrões da MUI.
- Meta numérica de desempenho (LCP, tamanho de bundle) e ferramenta de teste de acessibilidade: OIK-ADR-0010.
- CSP (estilos em tempo de execução da MUI exigem nonce ou `style-src` adequado), domínios e CDN: OIK-ADR-0011.
- Fidelidade do Scramble às projeções condicionais dos Resources: validar na Spec; se falhar, anotar os Resources manualmente.
- Outros idiomas: preparados, não pedidos; formato de datas e telefone por região entra quando houver o primeiro idioma novo.
