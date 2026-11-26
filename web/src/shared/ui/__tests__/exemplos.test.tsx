// OIK-SPEC-0001 T-56: o catálogo executável e a documentação evoluem juntos.
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

const componentes = [
  'AjudaContextual', 'ConfirmacaoIrreversivel', 'DetalheEmCamadas', 'DetalhesTecnicos',
  'EstadoAviso', 'EstadoCarregando', 'EstadoErro', 'EstadoIndisponivel', 'EstadoSucesso',
  'EstadoVazio', 'FiltrosNaUrl', 'JornadaGuiada', 'TabelaDeRegistros',
] as const

const raiz = resolve(process.cwd(), '..')

describe('catálogo do Oikora UI Standard', () => {
  it('mantém todo componente público documentado e exemplificado na galeria', () => {
    const documentacao = readFileSync(resolve(raiz, 'docs/ui/oikora-ui-standard.md'), 'utf8')
    const galeria = readFileSync(resolve(process.cwd(), 'src/app/painel/galeria/Galeria.tsx'), 'utf8')

    for (const componente of componentes) {
      expect(documentacao, `${componente} sem documentação`).toContain(`\`${componente}\``)
      expect(galeria, `${componente} sem exemplo`).toMatch(new RegExp(`<${componente}(?:\\s|>)`))
    }
  })

  it('liga as jornadas do produto e as decisões que regem o padrão', () => {
    const documentacao = readFileSync(resolve(raiz, 'docs/ui/oikora-ui-standard.md'), 'utf8')
    expect(documentacao).toContain('../adr/0014-padrao-de-experiencia-da-interface.md')
    expect(documentacao).toContain('../adr/0016-rascunhos-de-jornada-no-servidor.md')
    expect(documentacao).toMatch(/Pessoas[\s\S]*Avisos[\s\S]*WhatsApp/)
  })
})
