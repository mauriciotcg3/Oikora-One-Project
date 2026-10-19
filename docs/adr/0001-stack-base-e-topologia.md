# ADR-0001: Stack base e topologia (PHP/Laravel como API + React)

**Status:** Aceito — após confirmação do usuário (2026-10-19)
**Data:** 2026-10-19
**Contexto do projeto:** Oikora One — plataforma da denominação (3 igrejas, ~520 membros) que substitui o inChurch módulo a módulo, com plano de atender outras igrejas.
**Identificação:** Produto `OIK-ADR-0001` · Governança `ADR-0023`
**Fonte da verdade:** `oikora-one:docs/adr/0001-stack-base-e-topologia.md`

## Contexto
O documento de produto `docs/product/0001-fundacao-e-pessoas.md` (READY_FOR_ARCHITECTURE em 2026-10-19) pede painel administrativo para secretaria e administrador, área do membro como site (bom no navegador do celular) e como app nas lojas (NFR-005), formulários públicos "visitante" e "aceitei Jesus", acessibilidade WCAG AA (NFR-006) e um filtro de visibilidade por recurso × ação × escopo, com dados sensíveis, que atravessa todas as telas, buscas e exportações (§20). A virada é conjunta (Pessoas + Células + Kids + Eventos) e, até lá, o Oikora fica só em homologação (BR-014, BR-017).

O usuário definiu como restrição (2026-10-19) que a stack é PHP e React, a partir de `docs/analise/especificacao-tecnica-php.md`, que recomenda Laravel e propõe Filament (Livewire) no painel, citando "Inertia + Vue/React" como alternativa (§12.2). Na mesma data o usuário confirmou: React no painel administrativo, com Filament descartado; React na área do membro e nos formulários públicos; tecnologia do app decidida em ADR próprio. A escolha de linguagem não foi reaberta; este ADR decide onde e como PHP/Laravel e React se encaixam.

Contexto operacional informado pelo usuário: pequena equipe (2 a 4 desenvolvedores, além de agentes de IA); disponibilidade esperada em horário comercial e cultos, com parada curta aceitável fora dos picos e sem plantão 24h. A baseline da governança (ADR global ADR-0001 e `architecture-standard.md` §3–4, `frontend-standard.md` §3) já prevê PHP 8.4, Laravel 12+, React com TypeScript e monólito modular, com o frontend consumindo o backend só pela API HTTP.

## Opções consideradas
- **A. Laravel como API + React SPA** — backend expõe só a API HTTP versionada `/api/v1`; painel e área do membro são aplicações React + TypeScript que consomem essa API, assim como o app.
- **B. Laravel + Inertia + React** — telas web em React renderizadas a partir de rotas e props do Laravel via Inertia; o app consome uma API `/api/v1` paralela.
- **C. Filament no painel + React na área do membro** — painel em Filament/Livewire (recomendação original da especificação), área do membro em React sobre a API; mantida só para comparação, pois contraria a diretriz do usuário.

## Comparação

| Pilar | A. Laravel API + React SPA | B. Laravel + Inertia + React | C. Filament + React |
|---|---|---|---|
| Desempenho | Bom — JSON enxuto, bundle separado por público (o membro no celular não baixa o painel); folga ampla para ~520 membros | Bom — primeira renderização com menos idas ao servidor | Bom — Livewire faz uma ida ao servidor por interação; aceitável nesta escala |
| Simplicidade de manutenção | Bom — um único contrato de API para painel, site e app; regra de campo e de dados sensíveis num só lugar; custo: tabelas, filtros e ações em massa montados com biblioteca de componentes | Aceitável — dois caminhos de serialização (props do Inertia na web, API no app); o filtro de sensíveis precisa ser mantido nos dois | Ruim — duas stacks de front (Livewire e React) e dois caminhos de serialização para uma equipe pequena |
| Boas práticas | Ótimo — segue a baseline da governança (ADR global ADR-0001/0011) e o Frontend Standard (front consome só a API HTTP) | Aceitável — contraria em parte o Frontend Standard, que pede consumo exclusivo via API | Ruim — contraria a diretriz do usuário e o Frontend Standard |
| Escalabilidade | Ótimo — a mesma API atende app, outras igrejas e integrações; backend sem estado de tela; front estático pode ir para CDN | Bom — a API do app cresce em paralelo às rotas Inertia | Bom em carga; a API para o app precisa ser criada à parte de qualquer forma |
| Observabilidade / logs | Bom — cada ação é uma rota REST identificável com `request_id`, correlacionável com o erro do navegador | Bom — rotas reais aparecem no log | Aceitável — toda ação aparece como `POST /livewire/update`; o log não diz qual tela ou ação falhou |

## Decisão
Adotar a opção **A**:

1. **Backend:** PHP 8.4 ou superior e Laravel 12 ou superior (versões exatas fixadas no bootstrap do projeto e registradas na Spec; mudança de major exige ADR, conforme `ARCH-003`), organizado como monólito modular por domínio (`ARCH-004`, `MOD-001`). Cada caso de uso é uma Action única, chamada por todos os canais (painel, área do membro, formulário público, app, migração).
2. **Fronteira:** o backend é exposto aos clientes **somente** pela API HTTP JSON versionada `/api/v1`. Não há tela de produto renderizada no servidor; Blade fica restrito a e-mails e páginas técnicas.
3. **Frontend web:** React com TypeScript em **duas entradas web** — painel administrativo, e área do membro com os formulários públicos — que compartilham componentes de UI e o cliente de API. Bundler, roteamento, server state, biblioteca de componentes/tabelas e testes ficam no OIK-ADR-0006.
4. **App nas lojas:** baseado em React/TypeScript e consumindo a mesma `/api/v1`; a tecnologia (React Native ou web empacotado) fica no OIK-ADR-0007.
5. **Descartados:** Filament, Livewire e Inertia para telas do produto.
6. **Repositório:** um único repositório, `Oikora-One-Project`, com backend e frontends.
7. **Fora deste ADR:** banco de dados e isolamento multi-organização (OIK-ADR-0002), autenticação e sessão (OIK-ADR-0003), autorização e dados sensíveis (OIK-ADR-0004), filas e avisos (OIK-ADR-0008), pacotes citados na especificação (spatie/permission, activitylog etc.) — cada um entra no ADR do roteiro que o decide.

## Justificativa
- **Escalabilidade e manutenção:** o app nas lojas (NFR-005) obriga a existir uma API de qualquer forma. Fazer do painel e da área do membro clientes dessa mesma API evita um segundo caminho de dados (Inertia ou Livewire) e, com isso, evita manter duas vezes o filtro de visibilidade por escopo e dados sensíveis que o §20 exige em todas as telas, buscas e exportações. A mesma API serve à expansão para outras igrejas e a integrações futuras.
- **Boas práticas:** a opção segue a baseline já aprovada na governança (PHP/Laravel + React/TypeScript, monólito modular, front consumindo só a API) e respeita a diretriz do usuário sem exceção ao Frontend Standard.
- **Manutenção para uma equipe pequena:** uma só stack de front (React/TypeScript) para painel, site e — conforme o OIK-ADR-0007 — app; o custo de montar tabelas, filtros e ações em massa sem Filament é aceito e será mitigado pela biblioteca de componentes do OIK-ADR-0006.
- **Desempenho:** duas entradas web mantêm o bundle do membro pequeno para o celular; o volume (3 igrejas, ~520 membros) não pressiona nenhuma das opções.
- **Observabilidade:** rotas REST explícitas permitem identificar, no log, qual recurso e ação falharam e correlacionar com o erro do navegador, o que não acontece com o endpoint único do Livewire.
- **Disponibilidade:** a expectativa de horário comercial e cultos, sem plantão 24h, não justifica nada além de um monólito modular.

## Consequências

**Positivas:**
- Uma única fonte de regra e de serialização para todos os canais; o filtro de sensíveis é aplicado uma vez, na API.
- O app (OIK-ADR-0007) e futuras integrações reutilizam a API sem trabalho paralelo.
- Contratos de API testáveis isoladamente (testes de API no backend, mocks no front).
- Aderência direta aos padrões da governança, que já descrevem esta arquitetura.

**Negativas / trade-offs aceitos:**
- Perde-se o ganho de velocidade do Filament (tabelas, filtros, ações em massa e formulários prontos); essas peças serão montadas em React.
- Duas aplicações (backend e frontends) para construir, versionar e implantar; a topologia de implantação e domínios fica no OIK-ADR-0011 e a autenticação web (cookie vs token) no OIK-ADR-0003.
- Validação aparece no front (experiência) e no backend (regra definitiva); o backend é sempre a autoridade.
- A API precisa de versionamento e cuidado com compatibilidade desde o início, porque o app publicado nas lojas não é atualizado instantaneamente.

## Observabilidade — o que precisa ser instrumentado
- **Eventos a logar:** toda requisição à `/api/v1` com `request_id`, rota, método, status, duração, módulo, organização/igreja e id do usuário (sem dado pessoal no log); erros de Action com o nome do caso de uso; erros não tratados do navegador e do app com rota da tela, versão do build e o `request_id` da chamada que falhou.
- **Métricas a expor:** taxa de erro 5xx e latência p95 por rota da API; contagem de erros de front por tela e versão do build; versão do app em uso por cliente (cabeçalho), para saber quando uma versão antiga da API pode ser desligada.
- **Alertas a configurar:** taxa de 5xx acima do normal por rota em horário comercial ou de culto; pico de erros de front após um deploy; chamadas a versão de API marcada como obsoleta. Ferramentas e limiares no OIK-ADR-0010.
- **Cenário de incidente validado:** num domingo cedo a secretaria avisa que a lista de pessoas não carrega. O alerta de erros do navegador mostra `GET /api/v1/pessoas` → 500 com um `request_id`; filtrando o log do backend por esse `request_id`, o plantão vê módulo Pessoas, a igreja, o usuário e um timeout na consulta de busca, e sabe qual área e qual consulta falharam sem ler o código.

## Lacunas conhecidas
- Versões exatas de PHP e Laravel: fixadas no bootstrap do projeto (Spec), dentro do mínimo deste ADR.
- Tecnologia do app (OIK-ADR-0007), biblioteca de componentes/tabelas e tooling do front (OIK-ADR-0006), autenticação web vs app (OIK-ADR-0003) e topologia de implantação/domínios (OIK-ADR-0011) ainda não decididas.
- Ferramenta de logs, alerta de erros e limiares de alerta: OIK-ADR-0010.
