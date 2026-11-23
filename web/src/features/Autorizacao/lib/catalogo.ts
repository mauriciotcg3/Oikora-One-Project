// Agrupa o catálogo de permissões (plan.md §3.3) para a tela de papéis. A lista vem do servidor; aqui só
// o agrupamento e a marcação das sensíveis por categoria (OIK-ADR-0004 §7).

export type Grupo = 'organizacao' | 'solicitacoes' | 'pessoas' | 'sensiveis'

export interface GrupoDePermissoes {
  grupo: Grupo
  /** Só nas sensíveis: categoria (`saude`, `documentos`, `contatos`, `fe`). */
  categoria?: string
  permissoes: string[]
}

const CATEGORIAS = ['saude', 'documentos', 'contatos', 'fe'] as const

export function categoriaSensivel(permissao: string): string | null {
  const partes = permissao.split('.')
  return partes[0] === 'pessoas' && partes[1] === 'sensiveis' ? (partes[2] ?? null) : null
}

export function agrupar(permissoes: string[]): GrupoDePermissoes[] {
  const organizacao: string[] = []
  const solicitacoes: string[] = []
  const pessoas: string[] = []
  const sensiveis = new Map<string, string[]>()

  for (const permissao of permissoes) {
    const categoria = categoriaSensivel(permissao)
    if (categoria !== null) sensiveis.set(categoria, [...(sensiveis.get(categoria) ?? []), permissao])
    else if (permissao.startsWith('permissoes.')) solicitacoes.push(permissao)
    else if (permissao.startsWith('pessoas.') && permissao !== 'pessoas.configurar') pessoas.push(permissao)
    else organizacao.push(permissao)
  }

  const grupos: GrupoDePermissoes[] = [
    { grupo: 'organizacao', permissoes: organizacao },
    { grupo: 'solicitacoes', permissoes: solicitacoes },
    { grupo: 'pessoas', permissoes: pessoas },
    ...CATEGORIAS.filter((c) => sensiveis.has(c)).map((categoria): GrupoDePermissoes => ({
      grupo: 'sensiveis',
      categoria,
      permissoes: sensiveis.get(categoria) ?? [],
    })),
  ]
  return grupos.filter((g) => g.permissoes.length > 0)
}
