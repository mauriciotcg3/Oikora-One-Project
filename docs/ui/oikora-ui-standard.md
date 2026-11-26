# Oikora UI Standard

Este documento é o contrato prático da interface do painel. O MUI fornece os primitivos; o Oikora define linguagem, composição, estados e comportamento. A galeria local em `/painel/galeria` é o catálogo executável em desenvolvimento.

Decisões de origem: [experiência da interface](../adr/0014-padrao-de-experiencia-da-interface.md), [frontend web](../adr/0006-frontend-web.md) e [rascunhos de jornada no servidor](../adr/0016-rascunhos-de-jornada-no-servidor.md).

## Tokens e temas

Use apenas o tema e os `tokens` exportados por `shared/ui`. As cores têm função semântica e nunca são a única forma de comunicar estado. Espaçamento, tipografia, raios e elevação vêm do tema; uma feature não cria um segundo sistema visual. Claro, escuro e preferência do sistema devem preservar contraste, foco visível e hierarquia.

O painel segue `prefers-color-scheme` por padrão; o seletor do cabeçalho troca para claro, escuro ou sistema e o navegador guarda a escolha (`CHAVE_MODO_DE_COR`, preferência de quem usa). A cor primária da organização (`GET /painel/organizacao`) entra pelo `criarTemaOikora` e passa por `corLegivel`, que escurece ou clareia o tom até ter 4,5:1 sobre o papel e o fundo de cada esquema; cor inválida volta ao café/mel do Oikora. A tela de login usa o mesmo tema e é a referência visual: título forte, texto de apoio secundário, botões arredondados e Poppins.

## Layout e reflow

Toda tela autenticada usa `PaginaDoPainel` e funciona de 320 px CSS (zoom de 400% em 1280 px) a monitores ultrawide, sem rolagem horizontal da página (WCAG 1.4.10). O menu lateral fica fixo a partir de 900 px e vira gaveta abaixo disso; o conteúdo tem largura máxima (`estreita`, `media`, `larga`) e fica centrado. Grades usam `minmax(min(100%, Nrem), 1fr)` para nunca exigir mais largura que a tela; só uma faixa de abas pode rolar dentro dela, com botões de rolagem.

## Componentes públicos

- `AjudaContextual`: explicação curta e opcional, fora do fluxo principal.
- `ConfirmacaoIrreversivel`: confirmação modal para ação destrutiva; foco inicial na opção segura e Escape cancela.
- `DetalheEmCamadas`: resumo de negócio primeiro e informações técnicas somente sob demanda.
- `DetalhesTecnicos`: request ID, trace ID e códigos copiáveis; nunca dados pessoais, segredos ou conteúdo sensível.
- `EstadoCarregando`: comunica uma espera sem simular conteúdo disponível.
- `EstadoVazio`: explica por que não há conteúdo e oferece, quando aplicável, um próximo passo.
- `EstadoSucesso`: confirma o resultado e seu efeito em linguagem da tarefa.
- `EstadoAviso`: condição que merece atenção, mas não impede a continuidade.
- `EstadoErro`: falha recuperável, ação de tentar novamente e correlação técnica recolhida.
- `EstadoIndisponivel`: serviço temporariamente inacessível, distinguido de erro de validação.
- `FiltrosNaUrl`: filtros validados e reproduzíveis; aplicar e limpar voltam à primeira página.
- `PaginaDoPainel`: moldura da tela — título de nível 1, descrição, ações, link de volta e largura máxima.
- `BotaoLink` e `LinkTexto`: navegação dentro do painel pelo `Link` do roteador (sem `href` cru, `window.location` ou `pushState`).
- `TabelaDeRegistros`: tabela paginada no servidor, navegável por teclado; falha usa texto e ícone além da cor. Abaixo de 900 px vira lista de cartões com o rótulo de cada coluna; aceita seleção de linhas que sobrevive à troca de página e um texto curto visível no botão da linha (`textoAbrir`).
- `JornadaGuiada`: valida cada etapa, preserva respostas ao voltar e confirma antes de concluir.

Hooks e utilitários (`useFiltrosDaUrl`, `usePaginacaoDaUrl`, `lerFiltros`, `lerPaginacao`) sustentam esses componentes e não são elementos visuais.

## Contratos de comportamento

Estados de carregamento, vazio, sucesso, aviso, erro e indisponibilidade devem orientar a pessoa sobre o que ocorreu e o próximo passo. Mensagens de domínio ficam na feature e passam por i18n. Detalhes internos ficam fechados por padrão: um código de erro da API (`operacao_expirada`) vira texto da feature, e código desconhecido cai numa mensagem pelo status HTTP, nunca no código cru. Toda ação que não muda de tela confirma o resultado (sucesso anunciado como `status`) e mostra o erro junto dela.

Filtros, página e tamanho da página vivem na URL. Dados remotos ficam no React Query; dados de formulário ficam no React Hook Form; seleção efêmera fica no componente. Não se cria estado global para conveniência.

Tabelas consultam apenas a página visível. Uma linha abre detalhe por controle com nome acessível. Jornadas longas salvam rascunhos no servidor, nunca em local storage; conflito entre abas exige recarregar a versão atual, sem merge implícito.

Todo fluxo funciona por teclado, mantém ordem de foco previsível, associa rótulos aos campos e não depende apenas de cor, posição ou ícone. Conteúdo originado de pessoas é renderizado como texto, sem HTML bruto.

## Jornadas de referência

1. **Pessoas** — cadastro por etapas, validação junto ao campo, confirmação e retorno claro de sucesso ou erro.
2. **Avisos** — filtros compartilháveis, tabela paginada no servidor e detalhe em camadas.
3. **WhatsApp** — operação longa salvável e retomável; credenciais e tokens de provedor são proibidos no rascunho.
4. **Auditoria** — filtros na URL, destaque textual de falha, trilha por alvo e correlação técnica somente sob demanda.

## Evolução do catálogo

Um novo componente compartilhado precisa, na mesma mudança, de export público, exemplo real na galeria, descrição neste documento e teste de comportamento visível. Antes de promover uma composição de feature para `shared/ui`, confirme uso real em mais de uma feature e remova qualquer regra de domínio.
