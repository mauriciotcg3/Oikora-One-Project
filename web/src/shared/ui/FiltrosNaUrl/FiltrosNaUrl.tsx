import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import MenuItem from '@mui/material/MenuItem'
import TextField from '@mui/material/TextField'
import { useNavigate, useSearch } from '@tanstack/react-router'
import { useState, type FormEvent, type ReactNode } from 'react'
import { z } from 'zod'
import { registrarEventoUi } from '../../observabilidade'

export interface OpcaoDeFiltro {
  valor: string
  rotulo: string
}

export interface CampoDeFiltro {
  /** Nome do parâmetro na URL. */
  chave: string
  rotulo: string
  /** Com opções, o campo é uma lista; sem elas, é texto livre. */
  opcoes?: readonly OpcaoDeFiltro[]
}

export type ValoresDeFiltro = Readonly<Record<string, string | undefined>>

const TAMANHO_MAXIMO_TEXTO = 200

/** Valida a busca da URL campo a campo: valor desconhecido, repetido ou fora das opções é ignorado. */
export function lerFiltros(campos: readonly CampoDeFiltro[], busca: unknown): ValoresDeFiltro {
  const forma: Record<string, z.ZodType<string | undefined>> = {}
  for (const campo of campos) {
    const valido = campo.opcoes
      ? z.enum(campo.opcoes.map((opcao) => opcao.valor) as [string, ...string[]])
      : z.string().trim().min(1).max(TAMANHO_MAXIMO_TEXTO)
    forma[campo.chave] = valido.optional().catch(undefined)
  }
  const origem = typeof busca === 'object' && busca !== null ? busca : {}
  return z.object(forma).parse(origem)
}

export interface FiltrosNaUrlProps {
  campos: readonly CampoDeFiltro[]
  /** Textos já traduzidos pela feature (OIK-ADR-0006 §13). */
  rotuloAplicar: string
  rotuloLimpar: string
  rotuloGrupo: string
  /** Opção de lista que representa "sem filtro". */
  rotuloTodos: string
}

/** Valores atuais dos filtros, lidos da URL com validação; use na rota para consultar a API. */
export function useFiltrosDaUrl(campos: readonly CampoDeFiltro[]): ValoresDeFiltro {
  const busca = useSearch({ strict: false })
  return lerFiltros(campos, busca)
}

/**
 * Filtros que vivem na URL (OIK-ADR-0014): aplicar e limpar mudam a busca da rota, então o link
 * reproduz a lista e o botão Voltar funciona. Não consulta a API: a rota usa `useFiltrosDaUrl`.
 */
export function FiltrosNaUrl({ campos, rotuloAplicar, rotuloLimpar, rotuloGrupo, rotuloTodos }: FiltrosNaUrlProps): ReactNode {
  const atuais = useFiltrosDaUrl(campos)
  // Ao mudar a URL (Voltar, link), o formulário recomeça dos valores novos.
  return <Formulario key={JSON.stringify(atuais)} {...{ campos, atuais, rotuloAplicar, rotuloLimpar, rotuloGrupo, rotuloTodos }} />
}

interface PropsFormulario extends FiltrosNaUrlProps {
  atuais: ValoresDeFiltro
}

function Formulario({ campos, atuais, rotuloAplicar, rotuloLimpar, rotuloGrupo, rotuloTodos }: PropsFormulario): ReactNode {
  const navegar = useNavigate()
  const [rascunho, setRascunho] = useState<Record<string, string>>(
    Object.fromEntries(campos.map((campo) => [campo.chave, atuais[campo.chave] ?? ''])),
  )

  const mudarBusca = (novos: Record<string, string | undefined>): void => {
    // Mantém os demais parâmetros da rota (ex.: tamanho da página); aplicar e limpar voltam à página 1; `to: '.'` fica na rota atual.
    void navegar({ to: '.', search: ((anterior: Record<string, unknown>) => ({ ...anterior, ...novos })) as never })
  }

  const aplicar = (evento: FormEvent): void => {
    evento.preventDefault()
    const novos = Object.fromEntries(campos.map((campo) => [campo.chave, rascunho[campo.chave]?.trim() || undefined]))
    registrarEventoUi('ui.filtros_aplicados', { quantidade: Object.values(novos).filter(Boolean).length })
    mudarBusca({ ...novos, pagina: undefined })
  }

  const limpar = (): void => {
    registrarEventoUi('ui.filtros_limpos')
    mudarBusca({ ...Object.fromEntries(campos.map((campo) => [campo.chave, undefined])), pagina: undefined })
  }

  return (
    <Box component="form" role="search" aria-label={rotuloGrupo} onSubmit={aplicar} sx={{ display: 'flex', flexWrap: 'wrap', gap: 2, alignItems: 'flex-end' }}>
      {campos.map((campo) => (
        <TextField
          key={campo.chave}
          label={campo.rotulo}
          size="small"
          select={Boolean(campo.opcoes)}
          value={rascunho[campo.chave] ?? ''}
          onChange={(evento) => setRascunho({ ...rascunho, [campo.chave]: evento.target.value })}
          // Em tela estreita cada campo ocupa a linha; em tela larga, largura fixa lado a lado.
          sx={{ minWidth: { sm: 200 }, flex: { xs: '1 1 100%', sm: '0 1 240px' } }}
          slotProps={campo.opcoes ? { inputLabel: { shrink: true }, select: { displayEmpty: true } } : undefined}
        >
          {campo.opcoes && [
            <MenuItem key="todos" value="">
              {rotuloTodos}
            </MenuItem>,
            ...campo.opcoes.map((opcao) => (
              <MenuItem key={opcao.valor} value={opcao.valor}>
                {opcao.rotulo}
              </MenuItem>
            )),
          ]}
        </TextField>
      ))}
      <Button type="submit" variant="contained">
        {rotuloAplicar}
      </Button>
      <Button type="button" variant="outlined" onClick={limpar}>
        {rotuloLimpar}
      </Button>
    </Box>
  )
}
