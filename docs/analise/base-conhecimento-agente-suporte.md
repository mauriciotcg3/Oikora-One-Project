# Base de conhecimento: suporte ao Painel inChurch

> **Uso:** base para um agente de IA que responde dúvidas de uso do painel administrativo inChurch (admin.inchurch.com.br, versão 20.0.0) e do app da igreja.
> **Fontes:** navegação real pelo painel em 12/10/2026 e Central de Ajuda oficial (centraldeajuda.inchurch.com.br). Regras vindas da Central estão marcadas com **(CA)**. Para indicar um artigo oficial, use `indice-central-de-ajuda.md` (293 artigos com link).
> Quando a nova plataforma entrar no ar, atualize as Partes C a F e mantenha as Partes A, B e G adaptadas.

---

## PARTE A: Instruções para o agente

### A1. Papel
Você é o assistente de suporte do painel da igreja. Ajuda secretaria, pastores, líderes de célula, tesouraria, equipe Kids, intercessão e comunicação a **encontrar telas e executar tarefas**.

### A2. Como responder
1. Identifique a tarefa e o perfil de quem pergunta.
2. Responda com **caminho de menu + passos numerados**. Exemplo: "Menu ☰ › Pessoas › Membros › **+ Novo**".
3. Cite a **busca rápida (Ctrl+K)** e o texto exato para digitar (ex.: "Cadastrar membro").
4. Quando existir, indique o **artigo da Central de Ajuda** (link do índice).
5. Seja breve. Português simples.
6. Se a pessoa não vê um menu ou botão, a causa provável é **permissão** (ver D12) ou o fato de **Preferências ser exclusiva do Administrador de Denominação** (CA).
7. Se parecer erro do sistema (tela em branco, campo que some, número estranho), registre tela, horário e o que foi feito, e encaminhe (A4).

### A3. Segurança e privacidade (obrigatório)
- **Nunca** peça nem aceite senhas, códigos de verificação, CPF completo, dados bancários ou cartões no chat.
- **Nunca** exponha dados de membros na conversa. Diga onde encontrá-los no painel.
- **Ações sem volta: sempre avise antes de orientar.**
  - Mesclar perfis.
  - "Importar todos" os usuários.
  - Mudar a organização das matrículas ou reordená-las.
  - Excluir pessoa, conta, inscrição ou função.
  - Enviar push.
  - Desvincular usuário de membro (pode excluir o perfil de membro, CA).
- **Dados sensíveis (LGPD):** saúde (alergias, PNE, tipo sanguíneo), sentimentos, pedidos de oração e atendimento pastoral. Trate com discrição. Pela LGPD, **a igreja é a controladora dos dados** (CA).

### A4. Quando encaminhar para um humano
- Erros do sistema.
- Cobrança, plano e recursos PRO.
- Restauração de dados.
- Estornos e problemas de pagamento ou conta de recebimento.
- Desbloqueio de usuário por "comportamento atípico".
- Pedidos de acesso ou permissão (o administrador da igreja decide).
- Integrações via API.

**Canais oficiais:**
- Central de Ajuda (artigos e o assistente "Salomão").
- **WhatsApp** e **Enviar solicitação**.
- Chat no painel (balão no canto inferior direito).
- **Portal do Cliente** (botão no topo do painel: boletos, base de conhecimento, novidades).

Horário: seg–sex 9h–18h · sáb 9h–13h · dom 8h–12h (Brasília). Às quintas, das 12h às 13h, só atendimento automático.

---

## PARTE B: Conceitos e glossário

| Termo | Significado |
|---|---|
| **Denominação / Regional / Igreja local** | Hierarquia da organização. O ID da igreja aparece no topo do painel |
| **Usuário** | Pessoa com conta no app ou site. **Não é necessariamente membro** (CA) |
| **Membro** | Faz parte oficialmente da membresia. **Único perfil com matrícula** (CA) |
| **Frequentador** | Participa com regularidade, em processo de integração (CA) |
| **Visitante** | Visitou a igreja pelo menos uma vez e tem cadastro (CA) |
| **Líder / Pastor** | **Não são perfis**: são marcações ("É líder?", "É pastor?") dentro do membro (CA) |
| **Novo convertido** | Marcou "Aceitou Jesus" (por culto, célula, live ou formulário). Fica na lista por **3 meses**. Marcar "batizado" marca "aceitou Jesus" automaticamente (CA) |
| **Vínculo usuário ↔ membro** | Feito pelo **mesmo e-mail** no app e no cadastro de membro. E-mails diferentes = cadastros desconectados (CA) |
| **Matrícula** | Número do membro (até 10 dígitos), automático ou manual; numeração por denominação ou por igreja local (CA) |
| **Perfis pendentes** | Cadastros e alterações aguardando aprovação da igreja |
| **Mesclagem** | Unir dois cadastros da mesma pessoa. **Não pode ser desfeita** (CA) |
| **Tipos de usuário do painel** | Usuário (só app) · Membro de Equipe (áreas liberadas) · Administrador Local, Regional ou da Denominação (tudo) (CA) |
| **Grupos** | Grupos de usuários (criados no V1) para segmentar conteúdo, gerar carteirinhas e importar pessoas (CA) |
| **Célula / Rede / Supervisor / Líder / Auxiliar** | Estrutura de pequenos grupos. Os nomes podem ser personalizados pela igreja |
| **Trilha / Jornada** | Caminho de integração e discipulado acompanhado por uma equipe |
| **Painel V1** | Painel antigo, **em descontinuação gradual** (CA). Ainda é usado para Usuários, permissões e Grupos |
| **inChurch Control** | App da equipe para check-in de eventos e Kids |

---

## PARTE C: Navegação geral

### C1. Onde fica cada coisa
- **Menu ☰:** Início, Igreja, Pessoas, Financeiro, Cuidado, Célula, Programação, Conteúdo, Kids, API, Preferências. No rodapé, **Inchurch Store** e **Painel V1**.
- **Busca rápida (Ctrl+K):** digite o que quer fazer ("Cadastrar membro", "Ver aniversários", "Enviar push").
- **Topo:** ID da igreja, **Portal do Cliente** e seu usuário.
- **Tema, cookies e sair:** Ctrl+K › "Alternar tema", "Gerenciar cookies", "Sair".
- **Navegador:** use o **Chrome**. O recorte de imagens não funciona no Safari. No iPhone e no iPad o painel tem recursos limitados, por exemplo não dá para recortar a imagem de uma notícia (CA).
- **Início (dashboard):** escolha a igreja local no topo. "Pessoas na igreja" soma membros, frequentadores e visitantes com os participantes de células, contando cada pessoa uma vez (CA).

### C2. Listas
Por padrão aparecem **6 itens por página**; use "Itens por página" no rodapé. Há **Pesquisar**, **Filtrar** (funil), **Exportar** (nuvem) e **+ Novo**. Muitas exportações **chegam por e-mail** (CA).

### C3. Como acessar o painel e redefinir a senha
1. Acesse admin.inchurch.com.br, informe e-mail e senha e clique em Entrar.
2. Se esqueceu a senha, clique em **Esqueci minha senha** e informe o e-mail do usuário.
3. Abra o link enviado por e-mail.

**Dicas:**
- Confira a pasta de spam.
- O link **expira** depois de um tempo.
- Se pediu mais de um e-mail, use **sempre o mais recente** (CA).

---

## PARTE D: Pessoas

### D1. Formas de cadastrar alguém (CA)
1. **Manual:** Pessoas › Membros (ou Visitantes) › **+ Novo**.
2. **Formulário público:** link ou QR Code em Pessoas › Geral › Importação de membros (D7).
3. **Pelo app:** a pessoa cria conta e se cadastra; o visitante pode escolher a igreja no app mesmo sem login.
4. **Importando usuários do app:** um grupo ou todos (D9).
5. **Planilha XLSX** (D8).

### D2. Cadastrar um membro
**Caminho:** Pessoas › Membros › **+ Novo** (Ctrl+K › "Cadastrar membro"). São **5 etapas**:
1. **Dados essenciais:**
   - Nome completo* e Igreja local* (já vem preenchida).
   - Perfil: Membro, Frequentador ou Visitante.
   - Situação: Ativo ou Inativo.
   - Se Ativo, Tipo de ativação (Batismo, Transferir, Aclamação, Reconciliação, Jurisdição) e Data. Se Inativo, Tipo e Data de inativação.
   - Observações.
   - Para membro, a **matrícula** pode ser **Automática** ou **Manual** (número de até 10 dígitos; se for manual, só avança com um número válido, CA).
2. **Dados pessoais:** Sexo, **Data de nascimento***, Necessidades especiais, Estado civil, Data do casamento, País do documento, CPF, RG.
3. **Contato:** **País*, CEP*, Endereço*, Bairro*, Estado*, Cidade***, Número, Complemento, Celular, É WhatsApp?, Telefone, E-mail.
4. **Dados eclesiásticos:** Aceitou Jesus? e data, Entrou por, Entrou em, Igreja de origem, É batizado?, Batizado por, Data do batismo, É líder?, É pastor?
5. **Dados adicionais:** Escolaridade, Profissão, Nacionalidade, Naturalidade, Tipo sanguíneo, Cadastrado por, Entrevistado por, Regional.

**Dicas**
- **Continuar** fica desabilitado até preencher o Nome.
- Campos marcados como "(Opcional)" podem ficar em branco; os demais são obrigatórios (CA).
- **Preencha o e-mail igual ao do app da pessoa.** É ele que liga a ficha ao usuário (CA).
- Antes de cadastrar, pesquise o nome em Membros e Visitantes para não duplicar.

### D3. Abrir e editar a ficha
1. Pessoas › Membros (ou Visitantes) › clique no nome, ou em **⋮ › Ver detalhes** (CA).
2. Abas: **Perfil** (resumo, células, trilhas), **Pessoais** (dados, Família, Contas vinculadas), **Contatos**, **Igreja**, **Linha do tempo** e **Mais** (dados adicionais, Documentos, histórico).
3. Para editar, use o botão **Editar …** de cada aba e depois **Salvar**.
4. **Nome, perfil e situação:** clique no **lápis ao lado da etiqueta do nome** (CA).
5. **Foto:** clique na área da foto › Adicionar foto › recorte › salvar (CA).
   - Se o membro já tem foto na ficha, a foto que ele colocar no app **não substitui** a da ficha.
   - Se a ficha não tem foto, a foto do app entra (CA).
   - O filtro "Perfil tem foto?" ajuda a encontrar quem está sem foto.
6. **Matrícula:** caixa de edição no canto superior direito da ficha. Se o número já pertence a outra pessoa, o sistema avisa e **oferece mesclar** os dois perfis (CA). ⚠️ Mesclar não tem volta.
7. **Família:** aba Pessoais › **Adicionar familiar** (nome e parentesco; se o familiar já é membro, ele aparece na busca; se não, aceita texto livre, CA).
8. **Funções:** atribuídas ao membro; pode ter mais de uma; as mudanças ficam no histórico (CA).

⚠️ **Problema conhecido:** em **Editar contato**, a **Cidade pode aparecer vazia** mesmo preenchida na ficha. **Selecione a cidade de novo antes de salvar.**

### D4. Inativar alguém ou mudar Visitante → Membro (CA)
1. Abra a ficha (Membros ou Visitantes).
2. Clique no **lápis ao lado da etiqueta do nome**.
3. Para **mudar de perfil**, altere o "Perfil na igreja" (ex.: Visitante → Membro) e salve.
4. Para **inativar**, mude a Situação para **Inativo**, informe o **motivo** e a **data**, e salve.

Prefira inativar a excluir. A linha do tempo registra a inativação e, depois, a reativação, também com motivo (CA).

### D5. Cadastrar visitante ou frequentador
Pessoas › Visitantes › **+ Novo**:
- **Tipo de perfil***, Visitou em, **Cadastrado por*** (busque quem está cadastrando), Motivo (Pedido de oração, Deseja ser membro, Reconciliação, Outros).
- **Nome***, **Data de nascimento***, E-mail, Celular (recomendado).
- Aceitou Jesus? (se "Sim", a **Data** é obrigatória) e Funções.
- **Salvar**.

Métricas de visitantes ficam em **Pessoas › Métricas** (a tela "Relatórios de visitantes" está vazia).

### D6. Aprovar cadastros, alterações, duplicados e exclusões
**Pessoas › Geral › Perfis pendentes** (CA):
- **Novos perfis** e **Alterações de perfil** (abas Membros, Frequentadores e Visitantes). É possível aprovar ou recusar tudo, ou **campo a campo**, e mudar o tipo de perfil.
- Ao **recusar um dado enviado pelo app**, escreva uma explicação. O membro recebe **push e e-mail** pedindo a correção e pode **contestar** (CA).
- **Ver perfis duplicados › Analisar perfil:** mesclar ou manter separado. ⚠️ A mesclagem não pode ser desfeita.
- **Pedidos de exclusão de dados:** aparecem quando a exclusão está configurada como manual.

A aprovação ser automática ou manual depende de D13.

### D7. Formulário de auto-cadastro (link e QR Code)
Pessoas › Geral › aba **Importação de membros**. Há três links, cada um com Copiar link e Ver QR CODE:
- **URL da denominação** (a pessoa escolhe a igreja).
- **URL da sua igreja.**
- **URL para quem aceitou Jesus.**

**Regras (CA):**
- A pessoa deve usar **o mesmo e-mail do app ou site**.
- O formulário tem **4 etapas**. Para visitante, são obrigatórios nome, sexo, nascimento, estado civil e "aceitou Jesus?", e é preciso **aceitar os Termos de Uso**.
- **As perguntas não podem ser personalizadas**, e os **campos personalizados não aparecem** no formulário.
- O cadastro pode precisar de **aprovação** (D6).
- Dica: divulgue o link numa notícia ou banner.

### D8. Importar por planilha (XLSX)
Pessoas › Geral › Importação de membros:
1. Clique em **Baixar planilha modelo**. Vem um **.zip** com a planilha e o **Manual de Importação (PDF)**.
2. Preencha seguindo o manual. **Não altere o cabeçalho.** Os valores precisam ser **exatamente iguais** ao manual, com as mesmas maiúsculas, acentos e sinais (ex.: `S`/`N`, `Batismo`, `Transferência`).
3. Clique em **Importar XLSX** e selecione o arquivo. Aparecem as barras de envio e de processamento.
4. Chega um **e-mail** com o resultado: **Sucesso**, **Incompleto** (algum campo opcional com erro) e **Erro** (campo obrigatório com problema), com uma planilha indicando os erros.

**Regras importantes (CA)**
- **Não há limite de linhas.** Arquivos grandes só demoram mais. A tela mostra o limite de 10 MB por arquivo.
- ⚠️ **Antes de importar, deixe "Mesclar perfis duplicados" em AUTOMÁTICO.** Em Manual, a importação **duplica** os cadastros.
- No modo automático, o sistema compara **e-mail e CPF**: se encontrar, **sobrescreve** com os dados da planilha; se não encontrar, cria um cadastro novo.
- **Campos personalizados** não vêm na planilha modelo; inclua as colunas seguindo o manual.
- **Para atualizar em massa quem já está cadastrado:** Membros (ou Visitantes) › Exportar › **Exportar em formato de importação**. Chega por e-mail uma planilha pronta para editar e reimportar.
- Dica: edite a planilha no Google Planilhas para facilitar.

### D9. Importar usuários do app para a membresia (CA)
- **Por grupo:** Pessoas › Geral › Importação › **Importar grupo**. Escolha o grupo (criado no Painel V1) e o perfil (Membro, Frequentador ou Visitante).
- **Toda a base:** **Importar todos** e escolha o perfil. ⚠️ **Não pode ser revertido.**
- **Selecionando pessoas:** Pessoas › **Usuários** › **Importar usuários** › marque as pessoas (ou a página, ou todos) › Importar como **membro** ou **visitante**.
- **Uma pessoa:** na linha do usuário, **⋮ › Importar como membro / Importar como visitante**.

### D10. Ligar usuário do app ao membro (Contas vinculadas)
O vínculo é automático quando o **e-mail é o mesmo**. Se a pessoa usou outro e-mail no app (CA):
1. Abra a ficha do membro › aba **Pessoais** › **Contas vinculadas**.
2. Clique em **Vincular | Mesclar pessoa** e busque por nome, CPF ou e-mail. Só aparecem usuários da mesma igreja local.
3. Selecione e clique em **Vincular**. Se aquele usuário já estiver ligado a outro membro, o sistema avisa e mostra o outro perfil.

⚠️ **Desvincular:** se a ficha de membro não tiver nada além dos dados do usuário, **o perfil de membro será excluído**. Oriente com cautela.

**Pelo app (CA):** o próprio usuário pode responder "**Esse perfil é seu?**" ou ir em Editar meu perfil e completar ou atualizar os dados (barra de %). Isso vai para aprovação conforme D13.

### D11. Outras telas de Pessoas
- **Aniversários, batismos e casamentos:** Pessoas › Aniversários. A exibição é **por dia**. Para ver o mês inteiro ou gerar etiquetas (nome, matrícula, endereço), use a **exportação**, que chega por e-mail (CA). Mensagens automáticas: Conteúdo › Mensagens do sistema (dá para personalizar, inclusive em inglês e espanhol, CA).
- **Novos convertidos:** Pessoas › Novos Convertidos (3 meses). Administradores e supervisores recebem um **e-mail semanal às segundas**; líderes de célula não recebem, por LGPD (CA).
- **Linha do tempo:** na ficha. Registra automaticamente entrada, mudança de perfil ou tipo, inativação e reativação. Aceita **registros manuais com data retroativa** (CA).
- **Histórico de membros (secretaria):** Pessoas › Histórico de Membros, ou na ficha › Mais, no fim da página. Quem tem permissão na área pode incluir, editar e excluir (CA).
- **Métricas:** Pessoas › Métricas. Use **Filtros** à direita e o período abaixo dos gráficos (7 dias, 30 dias, 3, 6 ou 12 meses). ⚠️ O total é da **igreja local** e os gráficos são da **denominação**, acumulados, então não compare os dois (CA).
- **Atendimento pastoral:** Cuidado › Atendimento Pastoral › + Novo. Só registra atendimentos **já realizados** (não aceita data futura). **Só quem atendeu vê, edita ou exclui as observações** (CA).

### D12. Dar acesso ao painel e permissões (Painel V1)
> Menu ☰ › rodapé › **Painel V1** › **USUÁRIOS**. O V1 está em descontinuação gradual, mas esta área ainda fica lá (CA).

1. Pesquise a pessoa. Se ela ainda não tem usuário, clique em **Adicionar Novo Usuário** e preencha Nome*, Regional*, Igreja*, Tipo*, Celular*, E-mail* e Senha*.
2. Em **Tipo de Usuário**, escolha:
   - **Usuário:** sem acesso ao painel.
   - **Membro de Equipe:** só as áreas liberadas.
   - **Administrador Local, Regional ou da Denominação:** acesso total, sem configurar permissões.
3. Clique no **Salvar** desse primeiro bloco.
4. Para Membro de Equipe, role até **Permissões** e, para cada **Seção**:
   - escolha a **Abrangência** (Denominação, Regional ou Igreja local);
   - opcionalmente, **Limitar a grupos** (ex.: só os pedidos de oração do Ministério de Louvor);
   - em **Autorizado a**, marque **ler, adicionar, editar e/ou excluir**.
5. Clique no **Salvar** do bloco de Permissões. A seção **Grupos** tem o seu próprio Salvar.

**Permissões mais usadas (CA)**
- **Controle de Membros:** membros, aniversários e usuários.
- **Controle de Visitantes e Frequentadores:** visitantes, frequentadores e novos convertidos.
- **Histórico de Pessoas:** histórico de membros.
- **Atendimentos:** atendimento pastoral.
- **Gestão de Células:** para quem cuida de **todas** as células, não para o líder de uma célula.
- **Pedidos de Oração:** equipe de intercessão.
- **Eventos + Controle de Acesso:** equipe de eventos (ingressos, cupons, push para inscritos).

**Limites e avisos**
- **Não é possível restringir a visualização dentro de uma área.** Por exemplo, quem tem Financeiro vê todas as contas (CA).
- **Preferências** (todas as configurações) são **exclusivas do Administrador de Denominação** (CA).
- O campo **Senha**, na edição, serve só para ajudar quem esqueceu a senha. O administrador **não vê** a senha atual (CA). Oriente o usuário a trocar a senha depois.
- Para eventos específicos, há a opção de **Equipe organizadora** dentro do evento, sem precisar dar permissão geral (CA).

### D13. Configurar Pessoas (só Administrador de Denominação)
**Caminho:** Preferências › Membros (Ctrl+K › "Configurar Pessoas").
- **Funções:** + Nova função. A função "Nenhum" é padrão e não pode ser editada nem removida. Excluir uma função move os perfis para "Nenhum" (CA).
- **Aprovar novos perfis:** Automático ou Manual, por Visitante, Membro e Frequentador.
- **Mesclar perfis duplicados:**
  - Automático ou Manual.
  - Critérios no mesmo "balão" funcionam como **E**; critérios separados funcionam como **OU**.
  - **Recomendação oficial (CA):** (Nome completo E Data de nascimento) OU CPF OU E-mail, com mesclagem **Manual**. Mas lembre de mudar para Automático durante importações de planilha (D8).
- **Gestão de matrículas:** quem pode alterar (todos com acesso à membresia ou usuários selecionados).
- **Organizar números de matrícula:**
  - Por denominação (padrão) ou por igreja local.
  - ⚠️ Mudar para "por igreja local" **renumera todos os membros da denominação, sem volta**. "Reordenar todas as matrículas" refaz a numeração por data de criação, também sem volta (CA).
- **Exclusão de contas:** Automático ou Manual (a recomendação é Manual) e quem aprova (CA).
- **Campos personalizados:** tipos múltipla escolha, telefone, data, texto curto etc. Aparecem só no painel, não no formulário nem na planilha modelo (CA).
- **Tipos de membro** (ex.: Batizado, Professo; um por vez, vira etiqueta e filtro), **Motivos de entrada e inativação** e **Atividades na linha do tempo**.

---

## PARTE E: Outros módulos (guia rápido)

### E1. Células
- **Criar célula:** Célula › Células › **+ Novo › Célula**. Preencha nome, rede, categoria, líderes, limite de pessoas, endereço, dia e horário, frequência e primeiro encontro. Clique em **Concluir**.
- **Redes:** Célula › Redes › + Novo. **Supervisores:** precisam ter perfil de membro; podem ter supervisor acima (hierarquia) (CA).
- **Participantes e visitantes** entram pelo **relatório da reunião**: Célula › Geral › Minhas Células › **Cadastrar relatório** › escolha a reunião › marque presença, adicione visitantes, contribuições e observações (CA).
- O visitante vira **participante** depois de presença em reuniões seguidas (os artigos citam 2 ou 3 semanas, CA).
- **Relatórios pendentes:** Célula › Geral › "Relatórios pendentes (30 dias)" › **Solicitar relatório** (um ou todos), ignorar ou exportar (CA).
- **Recesso:** inative a célula (só é possível sem reuniões agendadas) ou, em cada reunião, marque **"Não houve encontro"** com o motivo "Outro – Recesso" (CA).
- **Adiar uma reunião:** mude a data e escolha se as próximas também mudam (CA).
- **Em quantas células um membro pode estar:** configurável (1 no total, 1 por rede, até N ou ilimitado) em Preferências › Células (CA).
- **Buscador de células:** Preferências › Células › Buscador; o link é público. No app: Mais › Encontre uma célula (CA).
- **Incluir pessoa numa célula pela ficha:** aba Perfil › **Adicionar Célula** (como Participante ou Visitante) ou **Indicar para um líder** (CA).

### E2. Eventos e programação
- **Criar evento:** Programação › Eventos › **+ Novo**. Preencha imagem, nome, categoria, descrição, datas e horários, frequência e local (presencial ou online).
- **Tickets:** dentro do evento › aba **Tickets** › Criar novo ticket. Defina quantidade, valor, lotes com data e hora, quem paga a taxa, **segmentação por regional ou igreja**, cupons, upgrade e ticket avulso. Também é possível **importar inscrições por planilha** (CA).
- **Pagamento:** aba de Opções de pagamento (Pix, boleto, cartão) e **conta de recebimento**. A taxa é de **4,99%** por inscrição paga. O cartão parcelado não tem juros, só taxas (CA).
- O **boleto some das opções** cerca de 11 dias antes do evento (validade configurada + 8 dias) (CA).
- **Quem não paga não recebe o QR Code.** A inscrição fica como pendente (CA).
- **Reabrir inscrições:** ajuste a data limite de inscrição, as datas do evento e a validade dos tickets (CA).
- **Cancelar inscrição:** Controle de Inscrições › ⋮ › Remover. ⚠️ **Cancelar não estorna o valor.** O estorno é feito pelo painel quando disponível, ou pelo gerente de contas, e leva até 45 dias (CA).
- **Check-in:** app **inChurch Control**, com câmera, lendo o QR Code do participante (CA).
- **Avisar os inscritos:** push de evento, que também vai por e-mail (CA).
- **Equipe organizadora:** evento › Informações › Adicionar usuário da equipe (CA).
- **Ao vivo:** cadastre o **Canal** (ID do canal do YouTube) e crie **Transmissões**. No YouTube, **permita a incorporação** dos vídeos. Os botões **Aceitar Jesus** e **Contribuir** podem aparecer na live (CA).

### E3. Conteúdo
- **Pregações:** link do YouTube no formato **"watch"** (não use link de live); áudio via SoundCloud; "Em destaque" (CA).
- **Notícias:** aceitam HTML e CSS, mas não JavaScript. **Banners:** link interno ou externo e período de exibição.
- **Push:** Conteúdo › Notificações Push › + Novo. Pode ser agendado, **recorrente** e segmentado por grupo. Também é possível enviar push automático ao publicar notícia, evento ou pregação (CA). ⚠️ O envio não pode ser desfeito.
- **Downloads:** PDF, PNG ou JPEG de **até 5 MB** (CA).
- **Planos de leitura:** + Novo e depois **Incluir leitura**, uma por vez. Há sugestões prontas (CA).
- **Atalhos:** **Plano PRO**.
- **Versão da Bíblia** e ordenação das pregações: Preferências › App.

### E4. Cuidado
- **Pedidos de oração:** abas por categoria, Em aberto e Histórico. O botão **Orei** move o pedido para o histórico e **notifica o membro**. **Enviar apoio** abre o WhatsApp, se a pessoa autorizou. Os pedidos podem ser anônimos. Exportar Excel envia por e-mail (CA).
- **Testemunhos:** cadastrar, publicar e celebrar.
- **Sentimentos:** o push "Como está se sentindo hoje?" sai pela manhã. Frequência e emojis são configuráveis em Preferências › Cuidado (CA).
- **Jornadas:**
  1. Crie a **Equipe**.
  2. Crie a **Trilha** (precisa de equipe vinculada).
  3. Em Jornadas › Pessoas, **conecte** as pessoas à trilha.

  O membro vê suas trilhas no app em Mais › Perfil › Jornadas (CA).

### E5. Financeiro
- **Entradas e Saídas:** lance só o que acontece **fora** da plataforma. Doações e inscrições pagas pela plataforma entram sozinhas (CA).
- **Contas:**
  - **InChurch Pay** (cria a conta Safe2Pay, para receber pela plataforma). Para abrir, a razão social e o responsável no **QSA** precisam bater com o CNPJ e o estatuto.
  - **Conta Controle** (organização interna).
- **Categorias:** recebimento ou pagamento, com vínculo ao DRE e subcategorias.
- **Centros de custo:** áreas que só geram despesa (ex.: louvor).
- **Unidades de negócio:** áreas com receita e lucro (ex.: cantina) (CA).
- **Importação:** recebimentos e pagamentos por planilha **CSV**, sem alterar o cabeçalho (CA).
- **Boletos:** status Pendente (compensação em cerca de 3 dias), Pago ou Expirado.
- **Conciliação bancária** (OFX; confira se é a conta certa) e **Caixa em espécie** (exige uma conta Controle do tipo Caixa).
- **Relatórios:** Resumo, Fluxo de caixa, DRE, Doações e **Relatórios personalizados** (criar ou editar exige permissão de edição no Financeiro, CA).
- **Doações:** link e QR Code em Financeiro › Doações. A doação pode ser feita **sem login** e é identificada pelo e-mail. O membro vê o próprio histórico no app em Minhas Contribuições (CA).

### E6. Kids
- **Primeiro, as Turmas** (faixas etárias); depois as **Salas** (únicas ou recorrentes). A sala abre para check-in **cerca de 1h antes**, sem precisar abrir manualmente (CA).
- **Cadastrar criança:** Kids › + Novo. Preencha informações pessoais, responsáveis, restrições e observações. **Não dá para excluir definitivamente; só inativar** (com justificativa) (CA).
- **Impressora:** Brother **QL-800, QL-810, QL-810W ou QL-820**. O **programa da impressora precisa ficar aberto**, e cada computador tem sua impressora configurada (CA).
- **Check-in e check-out:** pelo app do responsável (Mais › Kids › Check-in, ler o QR Code da sala; o Check-out gera um QR Code) ou pelo app **inChurch Control** da equipe. Os voluntários podem **pedir ajuda aos pais** pelo inChurch Control (CA).
- **Registrar crianças em Pessoas:** Kids › Configurações › Sim e escolha "Sempre visitante", "Sempre frequentador" ou "Conforme o perfil do responsável" (CA).

### E7. Igreja, Grupos e API
- **Minha igreja / Denominação:** dados, logo, redes sociais, termos e privacidade, e-mail do "Fale conosco" do app.
- **Grupos (Painel V1):** Criar Novo Grupo › Salvar › adicionar usuários. Servem para **segmentar conteúdo**, gerar **carteirinhas virtuais** (no app: menu lateral › Minhas Carteirinhas) e permitir **inscrição pelo app**, com ou sem aprovação (CA).
- **API:** Mais › API › Clientes da API (API Key e Secret, permissões), Webhooks e Logs. Documentação técnica em docs.inchurch.com.br. Encaminhe dúvidas técnicas ao responsável de TI.

---

## PARTE F: Perguntas frequentes e solução de problemas

| Pergunta / sintoma | Resposta |
|---|---|
| "Não aparece o menu X para mim." | Falta permissão. O administrador libera em Painel V1 › Usuários › você › Permissões. Configurações (Preferências) são só para o Administrador de Denominação. |
| "Esqueci a senha do painel." | Login › **Esqueci minha senha** › e-mail. Veja o spam, use o e-mail mais recente e lembre que o link expira. Nunca compartilhe a senha no chat. |
| "O botão Continuar está cinza no cadastro." | Preencha o **Nome completo**. Com matrícula manual, informe um número válido. |
| "Editei o contato e a cidade sumiu." | Problema conhecido: selecione a **Cidade** de novo antes de salvar. Se o dado já se perdeu, encaminhe. |
| "Cadastrei a pessoa e ela não aparece." | Veja **Pessoas › Geral › Perfis pendentes** (a aprovação pode ser manual) e confira se ela está em Visitantes. |
| "O membro atualizou os dados no app e nada mudou." | As alterações vão para **Perfis pendentes › Alterações de perfil** e precisam de aprovação. Confira também se o **e-mail do app é o mesmo da ficha**. |
| "Como mudo um visitante para membro?" | Ficha › **lápis ao lado do nome** › Perfil na igreja = Membro › salvar. |
| "Como inativo alguém?" | Ficha › lápis ao lado do nome › Situação = Inativo › motivo e data › salvar. |
| "Tenho cadastros duplicados." | Pessoas › Geral › **Ver perfis duplicados** › Analisar, ou ficha › Contas vinculadas › Mesclar. ⚠️ Não tem volta. Configure os critérios em Preferências › Membros. |
| "Importei a planilha e duplicou todo mundo." | A mesclagem estava em **Manual**. Para as próximas importações, deixe em **Automático**. Para limpar, use "Ver perfis duplicados" e encaminhe ao suporte se forem muitos casos. |
| "A importação deu erro." | Leia o **e-mail de resultado** e a planilha de erros. Confira se o cabeçalho é o original e se os valores estão idênticos ao manual (maiúsculas e acentos). |
| "Posso personalizar as perguntas do formulário de cadastro?" | Não. Os campos personalizados também não aparecem no formulário; só no painel. |
| "Quero ver todos os aniversariantes do mês." | A tela mostra por dia. Use a **exportação**, que chega por e-mail. |
| "Por que o número de membros é diferente em cada tela?" | Cada quadro tem um critério: o total de Métricas é da igreja local, os gráficos são da denominação e acumulados, e "Pessoas na igreja" (Início) soma quem está em células. Para conferir, use a lista com filtros (Perfil = Membro, Situação = Ativo). |
| "O líder não vê a célula dele no app." | 1) O **e-mail** da ficha de membro precisa ser igual ao do app. 2) No V1, a permissão de Gestão de Células do usuário precisa ter a abrangência de **Líder/Supervisor**, com ver, adicionar, editar e excluir. |
| "Quero dar acesso só aos pedidos de oração de um ministério." | V1 › Usuários › Membro de Equipe › Permissão "Pedidos de Oração" › **Limitar a grupos** › escolha o grupo › Salvar. |
| "O tesoureiro só pode ver uma conta." | Hoje não é possível restringir dentro do Financeiro; a permissão vale para a área inteira. |
| "Usuário bloqueado por comportamento atípico." | Aconteceu por várias doações ou inscrições seguidas. A pessoa pode desbloquear pelo **captcha**; se não resolver, encaminhe ao suporte com o e-mail do usuário. |
| "Como o membro troca e-mail ou senha, ou exclui a conta?" | No app: Meu perfil › Configurações. Ao excluir a conta, ele escolhe se **mantém os dados na Gestão de Pessoas**. Se não mantiver, chega um pedido de exclusão para a igreja aprovar. |
| "O vídeo não toca no app." | No YouTube Studio, marque **Permitir incorporação**, deixe o vídeo **público** e use o link no formato "watch". |
| "Não consigo transmitir ao vivo pelo celular." | O YouTube exige pelo menos 1.000 inscritos para live pelo celular; use um encoder (software de transmissão). |
| "A etiqueta do Kids não imprime." | Verifique se a impressora é compatível e está ligada, se o **programa da impressora está aberto**, se a impressora certa está configurada naquele computador e se há etiquetas. |
| "A sala do Kids não aparece no check-in." | Ela abre cerca de 1h antes. Confira o cadastro, o dia e o horário, se está ativa e a faixa etária da criança. |
| "Excluí a inscrição, mas o valor não voltou." | Cancelar não estorna. Faça o estorno pelo painel, se disponível, ou com o gerente de contas (até 45 dias). |
| "O boleto sumiu das opções do evento." | Isso acontece perto da data do evento (validade do boleto + 8 dias antes do início). |
| "O recorte de imagem não funciona." | Use o **Chrome**. Safari, iPhone e iPad têm limitações. |
| "Aparece 'Atalhos indisponível para o seu plano'." | Recurso do **Plano PRO**. Encaminhe ao comercial (Portal do Cliente). |
| "O Painel V1 abriu." | É normal para Usuários e Grupos. Para voltar, use "Painel de Controle v2" no menu do V1. |

---

## PARTE G: Manutenção deste documento
- Revise depois de cada atualização do painel (versão atual: **v20.0.0 · 2026**) e quando o **Painel V1 for desligado**, porque os caminhos de D12 e Grupos vão mudar.
- Registre as perguntas que o agente não soube responder e amplie a Parte F.
- Mantenha `indice-central-de-ajuda.md` atualizado; a Central tem 387 artigos e muda com frequência.
