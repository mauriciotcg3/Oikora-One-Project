# Análise do painel inChurch e plano para a nova plataforma

**Data da análise:** 12/10/2026 (revisão 2: completada com a Central de Ajuda)
**Sistema analisado:** admin.inchurch.com.br (Painel v20.0.0 "V2" + Painel V1 legado)
**Conta usada:** Igreja Transformados Pelo Amor (ID 27031), perfil com acesso a todos os módulos
**Foco pedido:** cadastro de membros e usuários, passando por todas as telas

> **Fontes**
> 1. **Navegação no painel**, somente leitura, por todas as telas de consulta, formulários e configurações. Nada foi salvo, enviado, importado ou excluído. As etapas do cadastro de membro foram vistas pela tela de **edição** de um membro existente, sem salvar. Nenhum dado pessoal de membros foi copiado para este documento.
> 2. **Central de Ajuda oficial** (centraldeajuda.inchurch.com.br): 387 artigos no total, dos quais lemos os 38 de Pessoas e outros 259 de módulos relevantes. Regras que vieram de lá estão marcadas com **(CA)**. O índice com links está em `indice-central-de-ajuda.md`.

---

## 1. Resumo executivo

O inChurch é amplo: 11 módulos, cerca de 160 páginas e ações na busca rápida, além de um painel antigo (V1) que **está sendo descontinuado aos poucos (CA)** mas ainda é o único lugar para dar acesso ao painel. O ponto fraco está na **gestão de pessoas, que é a base de tudo e hoje está fragmentada**:

1. **Usuário e membro são cadastros diferentes**, ligados apenas por **ter o mesmo e-mail (CA)**. Uma pessoa pode existir como membro, visitante/frequentador, usuário do app, usuário do painel e criança (Kids). Quando os e-mails não batem, nada se conecta: o líder não vê sua célula no app, a foto não sincroniza, os dados enviados pelo app não chegam.
2. **Dar acesso ao painel só é possível no V1 legado.** O tipo de usuário precisa ser salvo antes de liberar as permissões. As permissões têm nível de ação (ler, adicionar, editar, excluir), mas **não dá para restringir dentro de uma área** (CA). Por exemplo, quem acessa o Financeiro vê todas as contas.
3. **Regras de obrigatoriedade inconsistentes** entre a configuração (15 campos obrigatórios), o assistente de cadastro, o formulário público, a planilha de importação e o cadastro de visitante. Os campos personalizados **não aparecem no formulário público nem na planilha modelo** (CA).
4. **Risco de perda e duplicação de dados:** a Cidade abre vazia na edição do contato. Importar planilha com a mesclagem em "Manual" **duplica os cadastros** (CA). "Importar todos" e a mesclagem **não podem ser desfeitos** (CA). Desvincular um usuário pode **excluir o perfil de membro** (CA).
5. **Números que parecem errados e são regras escondidas:** o Início mostra 434 membros, a lista mostra 466 e há "513 pessoas registradas". Segundo a CA, cada quadro tem um critério diferente (igreja local × denominação, pessoas em células contadas juntas), e nada disso está explicado na tela.
6. **Produtividade baixa:** 6 itens por página em todas as listas, edição fragmentada em blocos, aniversariantes só por dia (o mês inteiro só por exportação enviada por e-mail, CA).

**Recomendação central:** construir a nova plataforma em torno de uma **entidade única "Pessoa"**. A conta de login (app ou painel), os vínculos (visitante → frequentador → membro) e os papéis de acesso passam a ser **atributos da mesma pessoa**, ligados por identificador e não por coincidência de e-mail. Junto com isso:
- **cadastro rápido** com checagem de duplicidade em tempo real;
- **ficha completável**, inclusive pelo próprio membro no app, com aprovação;
- **acesso ao painel por convite**, com papéis, escopo e permissões por ação e por recurso.

---

## 2. Mapa da plataforma atual

> Rotas relativas a `https://admin.inchurch.com.br`. A busca rápida (**Ctrl+K**) lista quase todas as telas e ações. Algumas só aparecem no menu lateral. **Preferências** (todas as configurações) é **restrita ao Administrador de Denominação (CA)**.

### 2.1 Início
| Tela | Rota | Conteúdo |
|---|---|---|
| Painel inicial | `/inicio` | Ranking "missão inChurch", Sob cuidados, Em acompanhamento, Aceitaram Jesus; **Pessoas na igreja** (membros, frequentadores e visitantes somados aos participantes de células, contados uma vez só, CA); Jornada; Células; Sentimentos; Movimentações do mês; Relatórios de células pendentes (com "enviar mensagem" ao líder, CA); Mídias mais visualizadas. É preciso escolher a igreja local no topo (CA) |

### 2.2 Pessoas (foco)
| Tela | Rota | O que faz |
|---|---|---|
| Geral › Perfis pendentes | `/pessoas/membros/geral` | Aprovar ou recusar **novos perfis** e **alterações de perfil** (inclusive dados enviados pelo app, campo a campo, com justificativa que volta ao usuário por push e e-mail, CA); **Ver perfis duplicados** e mesclar (CA); pedidos de **exclusão de dados** (CA) |
| Geral › Importação de membros | `/pessoas/membros/geral` (aba) | 3 links e QR Codes de **auto-cadastro** (denominação, que permite escolher a igreja; igreja local; "aceitou Jesus"); importar um **grupo do V1** ou **toda a base** de usuários com o perfil escolhido; **planilha XLSX** (zip com modelo e manual em PDF; relatório por e-mail com sucesso, incompleto e erro, mais planilha de erros, CA) |
| Membros (lista) | `/pessoas/membros/lista` | Cards, busca (também por matrícula, CA), filtros avançados, exportar (inclusive **"em formato de importação"** para atualização em massa, CA), Novo, seleção múltipla, lixeira por card |
| Novo membro | `/pessoas/membros/novo-membro` | Assistente de 5 etapas (seção 3); matrícula automática ou manual, com até 10 dígitos (CA) |
| Ficha | `/pessoas/membros/detalhe/{id}` | Abas Perfil, Pessoais, Contatos, Igreja, Linha do tempo e Mais. **Lápis ao lado do nome** para editar nome, perfil e situação (é por aqui que se inativa ou se muda visitante para membro, CA); edição de matrícula; foto (clicar no avatar, com recorte; a foto do app só entra se o membro não tiver foto, CA) |
| Editar membro | `/pessoas/membros/editar-membro/{id}?step=personal\|contact\|ecclesiastical\|additional` | Mesmo assistente, aberto numa etapa |
| Visitantes | `/pessoas/visitantes/lista`, `/novo-visitante` | Lista e formulário de página única. O visitante também pode se cadastrar pelo app sem login, buscando a igreja (CA) |
| Relatórios de visitantes | `/pessoas/visitantes/relatorios` | **Tela vazia**; a CA indica Pessoas › Métricas |
| Novos convertidos | `/pessoas/membros/novos-convertidos` | Quem marcou "Aceitou Jesus" (por culto, célula, live ou formulário); **fica só 3 meses** na lista; marcar "batizado" marca "aceitou Jesus" automaticamente (CA); e-mail semanal às segundas para administradores e supervisores (CA) |
| Datas comemorativas | `/pessoas/datas-comemorativas/{aniversarios\|batismos\|casamentos}` | Exibição **diária**; lista do mês e etiquetas com nome, matrícula e endereço por exportação (CA). Mensagens automáticas em Conteúdo › Mensagens do sistema |
| Histórico de membros | `/secretariado/historico/lista` e `/relatorios` | Registros manuais da secretaria; quem tem permissão na área edita ou exclui (CA) |
| Linha do tempo (ficha) | — | Registro automático de entrada, mudança de perfil ou tipo, inativação e reativação (sempre com motivo), além de registros manuais com data retroativa (CA) |
| Usuários (do app) | `/pessoas/membros/usuarios` | Tabela; ações Detalhe, **Importar como membro** ou **como visitante**; **Importar usuários** em lote ou todos, **irreversível** (CA) |
| Contas a excluir | `/pessoas/membros/excluir-contas` | Pedidos de exclusão vindos do app (LGPD) |
| Métricas | `/pessoas/membros/relatorios` | Gráficos acumulados; **o total é da igreja local, os gráficos são da denominação (CA)** |
| Preferências de Pessoas | `/preferencias/configuracoes-mem` | Ver 3.5 |

### 2.3 Células
Lista, Nova célula, Redes, Supervisores (com hierarquia, CA), Administradores (**tela vazia**), Participantes, Visitantes de células, Indicados, Material de apoio, Métricas, Relatórios (pendentes de 30 dias; solicitar um ou todos; ignorar; exportar, CA) e Hierarquia (só no menu). **Buscador de células** com link público e no app (CA). Regras (CA):
- O visitante vira participante depois de **presença em reuniões seguidas** (os artigos divergem entre 2 e 3 semanas).
- Quantas células um membro pode ter é configurável: 1 no total, 1 por rede, até N ou ilimitado.
- Recesso: inativar a célula ou registrar "Não houve encontro".
- Mudança de supervisor exige aprovação do supervisor de rede.
- O líder preenche o relatório no painel ou no app ("Minhas Células").

### 2.4 Programação (Eventos, Cultos, Ao Vivo)
Agenda; Próximos, Últimos e Todos os eventos; Criar evento; Categorias; Informações complementares; Cultos; Transmissões, Canais (YouTube) e Rádios; Preferências. Detalhe do evento (CA): abas Informações (com **equipe organizadora**), **Tickets** (lotes com data e hora, segmentação por regional ou igreja, cupons, upgrade, ticket avulso, importação de inscrições por planilha), **Opções de pagamento** (Pix, boleto, cartão; conta de recebimento), **Financeiro** e **Controle de inscrições**. Também: check-in pelo app **inChurch Control**, push para inscritos, cancelamento (que não estorna) e **estorno** (até 45 dias), taxa de **4,99%** por inscrição paga. Nas transmissões há os botões **Aceitar Jesus** (envia para Novos convertidos) e **Contribuir**.

### 2.5 Conteúdo (Mídia)
Pregações (vídeo do YouTube no formato "watch", áudio via SoundCloud, destaque), Séries, Categorias, Notícias (HTML e CSS permitidos, sem JavaScript, CA), sugestões prontas, Banners (link interno ou externo, período), **Push** (agendado, recorrente, segmentado por grupo; push automático ao publicar, CA), Downloads (PDF, PNG ou JPEG até 5 MB, CA), Pastas, Atalhos (**Plano PRO**), Planos de leitura, Páginas multiuso, Mensagens do sistema, Métricas, Preferências do app (versão da Bíblia, ordenação das pregações).

### 2.6 Cuidado (Intercessão e Jornadas)
Geral, Sentimentos (push diário "Como está se sentindo?", configurável), Pedidos de oração (anônimos ou não; botão **Orei** notifica o membro; **Enviar apoio** por WhatsApp, CA), Testemunhos (publicar e celebrar), **Atendimento pastoral** (só registra atendimentos passados; **as observações só são vistas e editadas por quem atendeu**, CA), Jornadas (Trilhas, Pessoas/conexões, Avaliações, Equipes; trilha exige equipe vinculada, CA), Métricas.

### 2.7 Financeiro
Entradas e Saídas (lançar só o que acontece **fora** da plataforma; o que passa pela plataforma entra sozinho, CA), Histórico de lançamentos (auditoria), Anexos, Fornecedores (PF ou PJ; CPF/CNPJ, nome e telefone obrigatórios), Doações (link, QR Code, destaque, ativa ou inativa; doação sem login identificada pelo e-mail), Boletos (status pendente, pago ou expirado), Conciliação (OFX), Caixa em espécie, Exportação contábil, Relatórios (Resumo, Fluxo de caixa, DRE, Doações, Personalizados), Parceiros, Configurações (Contas: **InChurch Pay**/Safe2Pay ou **Conta Controle**; Categorias com vínculo ao DRE; Centros de custo, que só geram despesa; Unidades de negócio, que têm receita e lucro). Importação por planilha **CSV** (CA).

### 2.8 Kids
Crianças (cadastro com foto, responsáveis e restrições; **não há exclusão definitiva, só inativação**, CA), Turmas (base para as salas), Salas (únicas ou recorrentes; abrem para check-in cerca de 1h antes, CA), Métricas, Configurações (etiquetas; impressoras **Brother QL-800, 810, 810W e 820**; o programa da impressora precisa ficar aberto; registrar crianças em Pessoas como sempre visitante, sempre frequentador ou conforme o perfil do responsável, CA). Check-in e check-out por QR Code no app do responsável ou no inChurch Control; pedir ajuda aos pais pelo app.

### 2.9 Igreja, Resumo administrativo, API
Minha igreja, Denominação, Regionais, Igrejas; Resumo administrativo. **API pública** REST com API Key e Secret, webhooks e logs; documentação em `docs.inchurch.com.br`. Permite **criar, consultar, atualizar e excluir membros, visitantes e líderes**, além de grupos, push, pagamentos, eventos, financeiro e vínculos de células (CA). **É o melhor caminho para a migração** (seção 6).

### 2.10 Painel V1 (legado, em descontinuação, CA), em `/v1/#/…`
Dashboard, **Usuários** (`/v1/#/users`, `/v1/#/user`: **o único lugar para criar usuários do painel e definir tipo, grupos e permissões**), Grupos (`/v1/#/smallgroups`: segmentação de conteúdo, carteirinhas virtuais, inscrição pelo app com ou sem aprovação, CA), Páginas multiuso, Banco de imagens. Multigestor, Datas comemorativas e Doações já foram removidos do V1 (CA).

---

## 3. Foco: cadastro de membros e usuários

### 3.1 Perfis e portas de entrada

**Perfis (CA):**
- **Usuário:** tem conta no app ou site; ainda não faz parte da membresia.
- **Visitante:** visitou a igreja pelo menos uma vez.
- **Frequentador:** está em integração.
- **Membro:** único perfil com **matrícula**.

"Líder" e "Pastor" **não são perfis**, e sim marcações dentro do membro.

| Porta de entrada | Onde | Obrigatórios observados | Observações |
|---|---|---|---|
| **Membro (manual)** | Pessoas › Membros › Novo, assistente de 5 etapas | Nome e igreja (etapa 1); nascimento (Pessoais); país, CEP, endereço, bairro, estado e cidade (Contato). A CA cita "nome, sexo, nascimento, país e endereço" | Também cria Frequentador e Visitante; matrícula automática ou manual |
| **Visitante (manual)** | Pessoas › Visitantes › Novo, página única | Tipo, Cadastrado por, Nome, Nascimento, Data (se aceitou Jesus) | Celular opcional |
| **Formulário público** | Link ou QR Code | Visitante: nome, sexo, nascimento, estado civil, "aceitou Jesus?" e aceite dos Termos (CA) | 4 etapas; **perguntas não personalizáveis**; **campos personalizados não aparecem** (CA); pode passar por aprovação |
| **App** | Cadastro no app; "Esse perfil é seu?" | — | O usuário completa ou atualiza a própria ficha (barra de %); vai para aprovação, e a igreja pode recusar campo a campo com justificativa (CA) |
| **Importação de usuários** | Geral › Importação, ou Usuários › Importar | Perfil a atribuir | "Importar todos" é irreversível (CA) |
| **Planilha XLSX** | Geral › Importação | Conforme o manual em PDF; cabeçalho imutável; valores **idênticos ao manual**, inclusive maiúsculas e acentos (CA) | Casa por **e-mail ou CPF** e **sobrescreve** o que já existe; **com a mesclagem em "Manual", duplica** (CA); campos personalizados precisam ser incluídos à mão |
| **Usuário do painel** | **Painel V1** › Usuários › Adicionar | Nome, Regional, Igreja, Tipo, Celular, E-mail, Senha | Permissões só depois de salvar o tipo |
| **Criança** | Kids › Crianças › Novo | Nome, nascimento, sexo, foto (configurável), responsável e parentesco | Vai para Pessoas conforme a configuração |

### 3.2 Assistente "Novo membro"

| Etapa | Rota de edição | Campos |
|---|---|---|
| 1. Dados essenciais | `novo-membro` | Nome completo* (até 150), Igreja local*, Perfil (Membro, Frequentador, Visitante), Situação (Ativo/Inativo), Tipo de ativação (Batismo, Transferir, Aclamação, Reconciliação, Jurisdição) ou de inativação, Data, Observações (até 250); matrícula automática ou manual (CA) |
| 2. Dados pessoais | `?step=personal` | Sexo, Data de nascimento*, Necessidades especiais, Estado civil, Data do casamento, País do documento, CPF, RG |
| 3. Contato | `?step=contact` | País*, CEP*, Endereço*, Número, Complemento, Bairro*, Estado*, Cidade*, Celular, É WhatsApp?, Telefone, E-mail |
| 4. Eclesiásticos | `?step=ecclesiastical` | Aceitou Jesus? e data, Entrou por, Entrou em, Igreja de origem, É batizado?, Batizado por, Data do batismo, É líder?, É pastor? |
| 5. Adicionais | `?step=additional` | Escolaridade, Profissão, Nacionalidade, Naturalidade, Tipo sanguíneo, Cadastrado por, Entrevistado por, Regional |

A ficha soma Família (busca o familiar entre os membros ou aceita texto livre), Contas vinculadas (Vincular / Mesclar pessoa, buscando por nome, CPF ou e-mail), Células, Trilhas, Linha do tempo, Documentos e Funções (várias por membro, com histórico, CA).

### 3.3 Acesso ao painel (V1)
- **Tipos (CA):**
  - **Usuário:** só o app.
  - **Membro de Equipe:** só as áreas liberadas.
  - **Administrador Local, Regional ou da Denominação:** tudo, sem precisar configurar permissões. As **Preferências** são exclusivas do Administrador de Denominação.
- **Fluxo:**
  1. Escolher o tipo e clicar no **Salvar** do bloco de dados.
  2. Na mesma página, em **Permissões**, marcar a seção, a abrangência (Denominação, Regional ou Igreja local), opcionalmente **Limitar a grupos**, e em **"Autorizado a"** escolher ler, adicionar, editar ou excluir.
  3. Clicar no **Salvar** do bloco de permissões.

  A página tem **3 botões Salvar** (dados, grupos e permissões).
- **Limites (CA):**
  - **Não é possível restringir a visualização dentro de uma área.** Por exemplo, o tesoureiro vê todas as contas.
  - "Gestão de Células" serve para quem cuida de todas as células. O líder de uma célula precisa da abrangência específica de Líder/Supervisor para ver a célula no app.
- **Mapeamento útil (CA):**
  - **Controle de Membros:** membros, aniversários e usuários.
  - **Controle de Visitantes e Frequentadores:** visitantes, frequentadores e novos convertidos.
  - **Atendimentos:** atendimento pastoral.
  - **Histórico de Pessoas:** histórico de membros.
- **Senha:** na criação é obrigatória (asterisco). Na edição, serve só para ajudar quem esqueceu a senha; o administrador não vê a senha atual (CA). Não encontramos troca obrigatória no primeiro acesso.

### 3.4 Problemas encontrados no cadastro, por prioridade

| # | Severidade | Problema | Fonte | Impacto |
|---|---|---|---|---|
| C1 | 🔴 Crítico | **Usuário e membro só se ligam por e-mail igual**, e desvincular pode **excluir o perfil de membro** se ele não tiver dados a mais que o usuário | CA | Líder sem acesso à célula no app, dados do app sem chegar, perda de cadastro |
| C2 | 🔴 Crítico | **Cidade abre vazia** na edição do Contato | Tela | Perda ou invalidação do endereço ao salvar |
| C3 | 🔴 Crítico | **Importar planilha com a mesclagem "Manual" duplica cadastros**; com "Automático", sobrescreve por e-mail ou CPF sem pré-visualização; "Importar todos" e a mesclagem **não têm volta** | CA | Base corrompida em massa sem possibilidade de desfazer |
| C4 | 🟠 Alto | **Acesso ao painel só no V1**, que está em descontinuação; o tipo precisa ser salvo antes das permissões; 3 botões Salvar | Tela + CA | Duas interfaces; risco de permissões não salvas; dependência de um sistema que vai sair |
| C5 | 🟠 Alto | **A senha inicial do usuário do painel é definida pelo administrador**, sem troca obrigatória visível | Tela | Senha conhecida por terceiros |
| C6 | 🟠 Alto | **Permissões sem granularidade dentro da área** (CA) e sem permissão separada para dados sensíveis | CA | Tesouraria, secretaria e intercessão veem mais do que deveriam |
| C7 | 🟠 Alto | **Números com critérios diferentes e sem explicação** (igreja local × denominação, células somadas, acumulado) | Tela + CA | A liderança desconfia dos relatórios |
| C8 | 🟠 Alto | **Obrigatoriedade inconsistente** entre configuração, assistente, visitante, formulário público e planilha; campos personalizados ausentes do formulário e da planilha | Tela + CA | Cadastros incompletos; campos da igreja nunca preenchidos pelo membro |
| C9 | 🟠 Alto | Celular e e-mail opcionais e **sem alerta de duplicidade durante o cadastro** (a detecção acontece depois, em "Ver perfis duplicados") | Tela + CA | Duplicatas entram facilmente |
| C10 | 🟡 Médio | Lista com **6 cards por página** (78 páginas) e lixeira em cada card | Tela | Lentidão e exclusão acidental |
| C11 | 🟡 Médio | Edição fragmentada: lápis no nome para perfil e situação, "Editar …" por aba para o resto | Tela + CA | Inativar ou mudar de perfil é difícil de achar |
| C12 | 🟡 Médio | Formulário público **não personalizável** | CA | Igrejas com processos próprios não conseguem coletar o que precisam |
| C13 | 🟡 Médio | Aniversariantes só por dia; o mês só por exportação enviada por e-mail; novos convertidos somem depois de 3 meses | CA | Rotina de secretaria e consolidação prejudicada |
| C14 | 🟡 Médio | Campos duplicados ou inconsistentes ("Motivo da/de inativação"; "Transferir" × "Transferência"; tipos "Não informado") | Tela | Dados sujos |
| C15 | 🟢 Baixo | "Eclesiáticos"; título "Aniversários" em todas as abas; Relatórios de visitantes vazio; rótulo "Observações sobre a entrada" quando o membro está Inativo | Tela | Acabamento |

### 3.5 Configurações de Pessoas (Preferências › Membros; só Administrador de Denominação)
- **Funções:** a padrão "Nenhum" é fixa; excluir uma função move os perfis para "Nenhum"; várias funções por pessoa, com histórico.
- **Aprovação de novos perfis** e alterações: automática ou manual, por tipo.
- **Mesclagem de duplicados:** automática ou manual. Critérios combináveis: campos ligados por "E" no mesmo balão, regras alternativas por "OU". A CA recomenda **(Nome completo E Data de nascimento) OU CPF OU E-mail**, com mesclagem **manual**.
- **Matrícula:** quem pode alterar; numeração por denominação ou por igreja local (**renumera todos, sem volta**); reordenar por data de criação.
- **Exclusão de contas:** automática ou manual (a CA recomenda manual); quem aprova.
- **Campos personalizados:** tipos múltipla escolha, telefone, data, texto curto etc.
- **Tipos de membro:** por exemplo Batizado, Professo; um por vez; aparecem como etiqueta.
- **Motivos de entrada e inativação** e **atividades da linha do tempo**.

### 3.6 O que vale manter na nova plataforma (pontos fortes do inChurch)
- **Atualização de dados pelo próprio membro no app**, com barra de progresso, e **revisão campo a campo** pela secretaria, com justificativa e notificação de correção.
- **Linha do tempo automática** com motivo obrigatório em entrada, mudança de perfil, inativação e reativação.
- **Funções com histórico**, **tipos de membro**, **matrícula configurável**.
- **E-mail semanal de novos convertidos**, que já exclui líderes de célula por LGPD.
- **Atendimento pastoral confidencial** (só quem atendeu vê as observações).
- **Fluxo de exclusão de dados (LGPD)** iniciado pelo usuário.
- **API e webhooks**.

### 3.7 Proposta para a nova plataforma: cadastro de pessoas

**Princípio:** *uma pessoa, uma ficha, uma identidade.* A conta de login aponta para a Pessoa por **ID**, e não por e-mail igual.

**a) Cadastro rápido (1 tela, menos de 30 segundos)**
```
Nome completo*           [ Maria da Silva            ]
Celular* (ou e-mail*)    [ +55 (11) 9____-____       ]  ← máscara + validação
E-mail                   [                           ]
Vínculo*                 (•) Visitante ( ) Frequentador ( ) Membro
Igreja*                  [ Igreja Transformados… ▾ ]   ← já preenchida
Como chegou?             [ Culto ▾ ]  (opcional)
                                     [Cancelar]  [Salvar e abrir ficha]  [Salvar e cadastrar outro]
```
- **Duplicidade em tempo real**, usando as regras configuradas (por exemplo nome + nascimento, CPF ou e-mail): "Já existe *Maria da Silva* · Membro #123 → **Abrir ficha** / **É outra pessoa**".
- "Salvar e cadastrar outro" para recepção e integração em dia de culto.

**b) Ficha completável**
- Barra "**Perfil 60% completo**", com regras de obrigatoriedade **por vínculo**, as mesmas em todos os canais (painel, formulário, app, importação, API).
- **Edição inline** por seção; ações "Mudar vínculo" e "Inativar" visíveis no topo da ficha, sempre pedindo motivo (como já acontece na linha do tempo).
- CEP com preenchimento automático e cidade sempre carregada; validação de CPF, telefone e datas.
- **Campos personalizados** disponíveis também no formulário público, no app, na planilha e na API, com opção "visível ao membro".

**c) Jornada de vínculo (pipeline)**
Quadro **Visitante → Frequentador → Em integração → Membro**, ligado a trilhas e células, com ações em lote.

**d) Lista de pessoas**
Tabela densa (25, 50 ou 100 por página), colunas configuráveis, filtros salvos (por exemplo "aniversariantes do mês" direto na tela, sem e-mail), ações em massa, exclusão só no menu ⋯, com confirmação e **lixeira de 30 dias**.

**e) Acesso ao painel: convite e papéis (substitui o V1)**
- Na ficha: **Dar acesso ao painel** → Papel → Escopo → **Convite por e-mail**; a pessoa define a própria senha.
- **Papéis prontos e editáveis:** Administrador geral, Pastor, Secretaria, Tesouraria, Líder de célula, Supervisor, Ministério Kids, Comunicação, Intercessão, Recepção.
- **Permissão = recurso × ação (ver, criar, editar, excluir, exportar) × escopo (denominação, regional, igreja, grupo ou célula)**, com **restrição dentro da área** (por exemplo, contas financeiras específicas).
- Permissão separada para **dados sensíveis** (saúde, documentos, sentimentos, pedidos de oração, financeiro pessoal).
- **2FA** para administradores, expiração de convite, revogação imediata e **log de auditoria** (inclusive de exportações).

**f) Importação segura**
Pré-visualização → mapeamento de colunas (tolerante a maiúsculas e acentos) → **relatório na tela** de "novos, atualizados, conflitos e erros" → confirmação → **desfazer importação**. Sem depender da configuração de mesclagem.

### 3.8 Modelo de dados sugerido (núcleo de Pessoas)

```
Organizacao (denominação) 1─* Regional 1─* Igreja
Pessoa ─ id, nome, nascimento, sexo, foto, cpf, rg, estado_civil, ... , criado_por, criado_em
  ├─ Contato*        (tipo: celular|telefone|email, valor, é_whatsapp, principal, verificado)
  ├─ Endereco*       (cep, logradouro, número, complemento, bairro, cidade_id, uf, país)
  ├─ Vinculo*        (igreja_id, tipo: visitante|frequentador|membro, tipo_membro, status,
  │                   data_inicio, motivo_entrada, data_fim, motivo_saida, matrícula)
  ├─ DadosEclesiasticos (conversão + canal, batismo, batizado_por, igreja_origem, é_líder, é_pastor)
  ├─ Relacionamento* (pessoa_a, pessoa_b, tipo)   ← família, responsáveis do Kids
  ├─ Funcao*         (função, desde, até)
  ├─ ValorCampoPersonalizado* ── CampoPersonalizado (tipo, opções, obrigatório_por_vínculo,
  │                                                  visível_ao_membro, sensível)
  ├─ EventoLinhaDoTempo* (módulo, atividade, motivo, antes/depois, autor, data_ocorrência)
  ├─ AlteracaoPendente*  (campo, valor_proposto, origem: app|formulário, status, justificativa)
  ├─ Documento*, Consentimento* (finalidade, data, origem)
  └─ Conta (0..1)    (login app/painel, e-mail, 2FA, último acesso)  ← ligada por pessoa_id
        └─ AtribuicaoPapel* (papel_id, escopo_tipo, escopo_id)
Papel ─* Permissao (recurso, ação, filtro_de_registros, inclui_sensíveis)
```

---

## 4. Diagnóstico geral de UX (todas as telas)

| Tema | Situação atual | Recomendação |
|---|---|---|
| Paginação | 6 itens por página em todas as listas | 25 itens por padrão, com opção de 50 e 100 e preferência lembrada |
| Navegação | Menu com itens repetidos; telas só no menu; Preferências escondidas para quem não é administrador de denominação | Arquitetura de informação revisada; busca rápida cobrindo 100% das telas |
| Dois painéis | V1 ainda necessário para acessos e grupos | Uma única aplicação |
| Números | Critérios diferentes por quadro, sem legenda | Definição explícita em cada número ("membros ativos da igreja X") |
| Exportações | A maioria chega **por e-mail** (aniversariantes, listas, pedidos de oração) | Download direto, com fila apenas para arquivos grandes |
| Telas vazias | Relatórios de visitantes, Administradores de células, Boletos, Resumo administrativo | Estado vazio com explicação e próximo passo |
| Programação | Cultos recorrentes criados como eventos repetidos; módulo Cultos vazio | Um modelo de programação com recorrência |
| Inscrições | Perguntas duplicadas ("camisa" × "camiseta", "Pai/Mae" × "Pai/Mãe") | Biblioteca de perguntas e preenchimento a partir da ficha |
| Plano | "Atalhos" no menu bloqueia na tela | Selo "PRO" no menu |
| Ações destrutivas | Lixeira em cada card; "Excluir" ao lado de "Salvar"; várias ações sem volta | Menu secundário, confirmação, possibilidade de desfazer ou lixeira |
| Navegador | A CA recomenda Chrome; recorte de imagem não funciona no Safari; iPhone e iPad limitados | Compatibilidade com todos os navegadores e telas de celular |
| Acessibilidade | Itens de menu e ícones sem rótulo | WCAG AA |

**Oportunidades observadas nos dados (sem dados pessoais):** 450 membros fora de células; 3 células com cerca de 25 relatórios pendentes cada; muitos dados de saúde no Kids. A nova plataforma deve facilitar a indicação para células, lembrar os líderes automaticamente e proteger os dados sensíveis.

---

## 5. Melhorias por módulo (resumo)

- **Células:** hierarquia visual, relatório de reunião em 1 tela pelo celular, regra clara e única de quando o visitante vira participante, buscador público.
- **Eventos:** um fluxo único com modelo e recorrência; inscrição que puxa dados da ficha; check-in por QR Code; estorno integrado; taxas mostradas antes de publicar.
- **Conteúdo:** biblioteca única de mídia, com agendamento e segmentação; aceitar qualquer link do YouTube.
- **Cuidado:** caixa de entrada unificada (oração, sentimentos, testemunhos, atendimentos) com responsável e prazo; manter a confidencialidade do atendimento pastoral.
- **Financeiro:** manter Conta Controle, conciliação, DRE, centros de custo e unidades de negócio; permissão por conta; importação com pré-visualização.
- **Kids:** criança e responsáveis como Pessoas relacionadas; alerta de alergia; impressão de etiqueta sem programa local, se possível.
- **API:** API e webhooks desde o início (`pessoa.criada`, `vinculo.alterado`…).

---

## 6. Plano de construção e migração

| Fase | Entregas | Critério de pronto |
|---|---|---|
| **0. Descoberta** | Entrevistas (secretaria, pastores, tesouraria, líderes, Kids); campos mínimos por vínculo; política LGPD; inventário de dados | Requisitos aprovados |
| **1. Fundação** | Login (convite, recuperação, 2FA), Denominação/Regional/Igreja, papéis e permissões por recurso, auditoria, design system | Admin convida uma "Secretaria" com escopo de uma igreja |
| **2. Pessoas (MVP)** | Cadastro rápido com deduplicação, ficha completável, pipeline, família, campos personalizados em todos os canais, formulário público, atualização pelo app com revisão, importação segura, aniversários, linha do tempo, LGPD | Secretaria opera 1 semana só na nova plataforma |
| **3. Células e programação** | Redes, células, relatórios pelo celular, agenda, eventos, inscrições, check-in | Líderes enviam relatórios pela nova plataforma |
| **4. Cuidado e Kids** | Caixa de cuidado, trilhas, atendimento pastoral, check-in Kids | Um culto completo com Kids |
| **5. Financeiro** | Lançamentos, contas, categorias, centros de custo, conciliação, relatórios | Fechamento de um mês em paralelo bate |
| **6. Conteúdo, app e integrações** | Mídia, push, banners, planos de leitura, API e webhooks | App consumindo a nova API |

**Migração de dados**
1. **Extrair pela API pública do inChurch** (Mais › API › Clientes da API › Novo, com permissão só de leitura). Documentação em `docs.inchurch.com.br`. Ela permite ler membros, visitantes, líderes, grupos, eventos, inscrições, financeiro e vínculos de células. Como alternativa, usar a exportação de listas e **"Exportar em formato de importação"** (chega por e-mail em XLSX).
2. **Tabela de mapeamento** dos 48 campos (incluindo os duplicados) e dos valores (motivos, tipos de membro, funções).
3. **Unificar pessoas:** cruzar membros, visitantes, usuários do app, usuários do V1 e crianças por e-mail, CPF, celular e nome + nascimento. Gerar um relatório de conflitos para a secretaria revisar. Hoje o vínculo é por e-mail igual, então espere muitos casos.
4. Preservar **matrícula, linha do tempo, funções com datas, consentimentos e atendimentos pastorais**. Os atendimentos precisam continuar visíveis só para o autor.
5. **Ensaio de migração** com contagens conferidas por uma **definição única** (por exemplo, "membros ativos por igreja").
6. **Operação em paralelo** por 2 a 4 semanas no módulo Pessoas e depois virada por módulo. Comunicar aos membros a troca de app e login.

---

## 7. Pendências e próximos passos
- **Não testado na prática:** mensagens e e-mails disparados ao salvar. Vale fazer um cadastro "teste" autorizado no inChurch para registrar o comportamento real.
- Ler a documentação técnica da API (`docs.inchurch.com.br`) para dimensionar a extração.
- Validar com a equipe as prioridades da seção 3.4 e os papéis da seção 3.7e.
- Documentos relacionados: `base-conhecimento-agente-suporte.md` (agente de suporte) e `indice-central-de-ajuda.md` (293 artigos com link).
