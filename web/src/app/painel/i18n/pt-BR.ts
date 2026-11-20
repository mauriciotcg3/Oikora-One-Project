// Namespace `painel`: textos da raiz da entrada (OIK-ADR-0006 §13).
export const painelPtBR = {
  inicio: {
    titulo: 'Painel da igreja',
    texto: 'Você entrou. As telas do painel chegam nas próximas tasks da OIK-SPEC-0001.',
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
