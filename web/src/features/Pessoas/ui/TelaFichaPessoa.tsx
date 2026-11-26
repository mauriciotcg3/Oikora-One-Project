import Avatar from '@mui/material/Avatar'
import Box from '@mui/material/Box'
import Chip from '@mui/material/Chip'
import LinearProgress from '@mui/material/LinearProgress'
import Paper from '@mui/material/Paper'
import Tab from '@mui/material/Tab'
import Tabs from '@mui/material/Tabs'
import Typography from '@mui/material/Typography'
import { useQuery } from '@tanstack/react-query'
import { useNavigate, useParams, useSearch } from '@tanstack/react-router'
import { useId, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { EstadoCarregando, EstadoErro, LinkTexto, PaginaDoPainel } from '../../../shared/ui'
import { obterFicha } from '../api/pessoas'
import { AlteracoesFicha } from './AlteracoesFicha'
import { nomeDoCampo, ORDEM_DAS_SECOES, secaoDoCampo, useFormatarValor, useMensagemDeErro, type Secao } from './comum'
import { RegistrosFicha } from './RegistrosFicha'

type AbaRegistro = 'linhaDoTempo' | 'relacionamentos' | 'funcoes'
type Aba = Secao | AbaRegistro

const ABAS_DE_REGISTRO: readonly AbaRegistro[] = ['linhaDoTempo', 'relacionamentos', 'funcoes']
const TIPO_DO_REGISTRO = { linhaDoTempo: 'linha-do-tempo', relacionamentos: 'relacionamentos', funcoes: 'funcoes' } as const

function iniciais(nome: string): string {
  const partes = nome.trim().split(/\s+/)
  return `${partes[0]?.[0] ?? ''}${partes.length > 1 ? (partes.at(-1)?.[0] ?? '') : ''}`.toUpperCase()
}

/** Ficha por seções (T-42): só as seções que a API devolveu (RN-10), aba na URL e registros da pessoa. */
export function TelaFichaPessoa({ uuid }: { uuid: string }): ReactNode {
  const { t } = useTranslation('pessoas')
  const mensagemDeErro = useMensagemDeErro()
  const formatar = useFormatarValor()
  const navegar = useNavigate()
  const busca = useSearch({ strict: false }) as { secao?: unknown }
  const idAbas = useId()
  const ficha = useQuery({ queryKey: ['ficha', uuid], queryFn: () => obterFicha(uuid) })

  if (ficha.isPending) return <EstadoCarregando texto={t('ficha.carregando')} />
  if (ficha.isError) {
    return (
      <PaginaDoPainel titulo={t('ficha.indisponivel')} voltar={<LinkTexto to="/pessoas">{t('comum.voltarPessoas')}</LinkTexto>}>
        <EstadoErro titulo={t('ficha.indisponivel')} texto={mensagemDeErro(ficha.error)} rotuloTentar={t('comum.tentar')} aoTentarDeNovo={() => void ficha.refetch()} />
      </PaginaDoPainel>
    )
  }

  const pessoa = ficha.data.data
  const nome = String(pessoa.campos.nome ?? pessoa.campos['pessoais.nome'] ?? pessoa.nome ?? '')
  const completude = typeof pessoa.completude === 'object' && pessoa.completude ? pessoa.completude : { percentual: Number(pessoa.completude ?? 0), faltando: [] as string[] }
  const faltando = (completude.faltando ?? []).map((campo) => t(`campos.${nomeDoCampo(campo)}` as never, { defaultValue: nomeDoCampo(campo) }))
  const grupos = new Map<Secao, Array<[string, unknown]>>()
  for (const [chave, valor] of Object.entries(pessoa.campos ?? {})) {
    if (nomeDoCampo(chave) === 'foto_path') continue
    const secao = secaoDoCampo(chave)
    grupos.set(secao, [...(grupos.get(secao) ?? []), [chave, valor]])
  }
  const secoes = ORDEM_DAS_SECOES.filter((secao) => grupos.has(secao))
  const abas: readonly Aba[] = [...secoes, ...ABAS_DE_REGISTRO]
  const aba: Aba = abas.includes(busca.secao as Aba) ? (busca.secao as Aba) : (abas[0] ?? 'linhaDoTempo')
  const podeEditar = ficha.data.meta.acoes.includes('editar')
  const vinculo = pessoa.vinculo ? t(`vinculos.${pessoa.vinculo}` as never, { defaultValue: pessoa.vinculo }) : null
  const situacao = pessoa.situacao ? t(`situacoes.${pessoa.situacao}` as never, { defaultValue: pessoa.situacao }) : null

  const trocarAba = (nova: Aba): void => {
    void navegar({ to: '.', search: ((anterior: Record<string, unknown>) => ({ ...anterior, secao: nova === abas[0] ? undefined : nova })) as never, replace: true })
  }

  return (
    <PaginaDoPainel
      titulo={nome}
      voltar={<LinkTexto to="/pessoas">{t('comum.voltarPessoas')}</LinkTexto>}
      acoes={<AlteracoesFicha uuid={uuid} vinculo={pessoa.vinculo} situacao={pessoa.situacao} podeEditar={podeEditar} />}
    >
      <Paper variant="outlined" sx={{ p: { xs: 2, md: 3 }, borderRadius: 4, display: 'flex', flexWrap: 'wrap', gap: { xs: 2, md: 3 }, alignItems: 'center' }}>
        <Avatar
          src={pessoa.foto_url ?? undefined}
          alt={pessoa.foto_url ? t('ficha.foto', { nome }) : undefined}
          sx={{ width: 72, height: 72, fontSize: '1.5rem', fontWeight: 600, bgcolor: 'primary.main', color: 'primary.contrastText' }}
        >
          {iniciais(nome)}
        </Avatar>
        <Box sx={{ display: 'grid', gap: 1, flex: '1 1 14rem', minWidth: 0 }}>
          <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1 }}>
            {vinculo && <Chip label={vinculo} color="primary" variant="outlined" />}
            {situacao && <Chip label={situacao} color={pessoa.situacao === 'ativa' ? 'success' : 'default'} variant="outlined" />}
            {pessoa.igreja?.nome && <Chip label={pessoa.igreja.nome} variant="outlined" />}
          </Box>
        </Box>
        <Box sx={{ display: 'grid', gap: 0.75, flex: '1 1 16rem', minWidth: 0 }}>
          <Box sx={{ display: 'flex', justifyContent: 'space-between', gap: 1 }}>
            <Typography variant="body2" sx={{ fontWeight: 600 }} id={`${idAbas}-completude`}>
              {t('ficha.completude')}
            </Typography>
            <Typography variant="body2" sx={{ fontWeight: 600 }}>
              {completude.percentual}%
            </Typography>
          </Box>
          <LinearProgress
            variant="determinate"
            value={completude.percentual}
            aria-labelledby={`${idAbas}-completude`}
            aria-valuetext={t('ficha.completudeValor', { percentual: completude.percentual })}
            color={completude.percentual >= 100 ? 'success' : 'primary'}
          />
          {faltando.length > 0 && (
            <Typography variant="body2" color="text.secondary">
              {t('ficha.faltando', { campos: faltando.join(', ') })}
            </Typography>
          )}
        </Box>
      </Paper>

      <Box sx={{ display: 'grid', gap: 2.5, minWidth: 0 }}>
        <Tabs value={aba} onChange={(_, nova: Aba) => trocarAba(nova)} variant="scrollable" scrollButtons="auto" allowScrollButtonsMobile aria-label={t('ficha.secoesRotulo')}>
          {abas.map((item) => (
            <Tab key={item} value={item} label={t(`ficha.secoes.${item}`)} id={`${idAbas}-aba-${item}`} aria-controls={`${idAbas}-painel`} />
          ))}
        </Tabs>
        <Box role="tabpanel" id={`${idAbas}-painel`} aria-labelledby={`${idAbas}-aba-${aba}`}>
          {(ABAS_DE_REGISTRO as readonly Aba[]).includes(aba) ? (
            <RegistrosFicha uuid={uuid} tipo={TIPO_DO_REGISTRO[aba as AbaRegistro]} podeEditar={podeEditar} />
          ) : (
            <Paper variant="outlined" sx={{ p: { xs: 2, md: 3 }, borderRadius: 4 }}>
              <Box
                component="dl"
                sx={{ m: 0, display: 'grid', gap: 2.5, gridTemplateColumns: 'repeat(auto-fill, minmax(min(100%, 14rem), 1fr))' }}
              >
                {(grupos.get(aba as Secao) ?? []).map(([chave, valor]) => (
                  <Box key={chave} sx={{ minWidth: 0 }}>
                    <Typography component="dt" variant="body2" color="text.secondary">
                      {t(`campos.${nomeDoCampo(chave)}` as never, { defaultValue: nomeDoCampo(chave) })}
                    </Typography>
                    <Typography component="dd" sx={{ m: 0, fontWeight: 500 }}>
                      {formatar(valor)}
                    </Typography>
                  </Box>
                ))}
              </Box>
            </Paper>
          )}
        </Box>
      </Box>
    </PaginaDoPainel>
  )
}

export function TelaFichaPessoaRoteada(): ReactNode {
  const { t } = useTranslation('pessoas')
  const { uuid } = useParams({ strict: false })
  if (uuid) return <TelaFichaPessoa uuid={uuid} />
  return (
    <PaginaDoPainel titulo={t('ficha.semIdentificador')} voltar={<LinkTexto to="/pessoas">{t('comum.voltarPessoas')}</LinkTexto>}>
      <EstadoErro titulo={t('ficha.semIdentificador')} texto={t('ficha.semIdentificadorTexto')} />
    </PaginaDoPainel>
  )
}
