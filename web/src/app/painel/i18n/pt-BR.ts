// Namespace `painel`: textos da raiz da entrada (OIK-ADR-0006 §13).
export const painelPtBR = {
  inicio: {
    titulo: 'Painel da igreja',
    texto: 'Você entrou. As telas do painel chegam nas próximas tasks da OIK-SPEC-0001.',
    atalhos: 'Gestão do painel',
    acessos: 'Acessos e convites',
    papeis: 'Papéis',
    auditoria: 'Auditoria',
  },
  shell: {
    titulo: 'Oikora One',
    navegacao: 'Menu do painel',
    abrirMenu: 'Abrir menu',
    pularParaConteudo: 'Ir para o conteúdo',
    carregando: 'Carregando o painel',
    erroTitulo: 'Não foi possível carregar o painel',
    erroTexto: 'Não conseguimos buscar o seu acesso. Tente de novo.',
    tentarDeNovo: 'Tentar de novo',
    ajuda: 'Ajuda',
    ajudaTexto: 'Use o menu para trocar de área. Se algo não aparecer, você pode não ter acesso a ela.',
    itens: { inicio: 'Início', auditoria: 'Auditoria' },
  },
  auditoria: {
    titulo: 'Auditoria',
    emBreve: 'A consulta de auditoria chega na próxima etapa.',
    negadoTitulo: 'Você não tem acesso a esta área',
    negadoTexto: 'Peça a quem administra a sua igreja a permissão para ver a auditoria.',
  },
  erro: {
    titulo: 'Algo deu errado',
    texto: 'A tela não pôde ser mostrada. Recarregue a página; se continuar, fale com o suporte.',
    codigo: 'Código para o suporte: {{traceId}}',
    recarregar: 'Recarregar',
  },
  formulario: {
    errosFora: 'Corrija também:',
  },
} as const
