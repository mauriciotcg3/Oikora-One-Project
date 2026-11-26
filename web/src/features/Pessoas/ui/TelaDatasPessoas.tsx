import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Chip from '@mui/material/Chip'
import Paper from '@mui/material/Paper'
import Tab from '@mui/material/Tab'
import Tabs from '@mui/material/Tabs'
import ToggleButton from '@mui/material/ToggleButton'
import ToggleButtonGroup from '@mui/material/ToggleButtonGroup'
import Typography from '@mui/material/Typography'
import { useQuery } from '@tanstack/react-query'
import { useNavigate, useSearch } from '@tanstack/react-router'
import { useId, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { EstadoCarregando, EstadoErro, EstadoVazio, LinkTexto, PaginaDoPainel } from '../../../shared/ui'
import { listarRecurso, type RegistroGenerico } from '../api/pessoas'
import { formatarData, useMensagemDeErro } from './comum'
import { DialogoExportacao } from './DialogoExportacao'

type Aba = 'novosConvertidos' | 'datas'
type Periodo = 'dia' | 'semana' | 'mes'
const PERIODOS: readonly Periodo[] = ['dia', 'semana', 'mes']

interface NovoConvertido extends RegistroGenerico {
  nome: string
  conversao_data?: string | null
  conversao_canal?: string | null
}
/** `GET /painel/aniversarios`: uma linha por data (a mesma pessoa pode vir com nascimento, batismo e casamento). */
interface DataComemorativa extends RegistroGenerico {
  nome: string
  tipo: string
  data: string
}

/** Novos convertidos e datas comemorativas (T-44): aba e período na URL, lista legível e exportação com retorno. */
export function TelaDatasPessoas(): ReactNode {
  const { t, i18n } = useTranslation('pessoas')
  const mensagemDeErro = useMensagemDeErro()
  const navegar = useNavigate()
  const busca = useSearch({ strict: false }) as { aba?: unknown; periodo?: unknown }
  const aba: Aba = busca.aba === 'datas' ? 'datas' : 'novosConvertidos'
  const periodo: Periodo = PERIODOS.includes(busca.periodo as Periodo) ? (busca.periodo as Periodo) : 'mes'
  const [exportando, setExportando] = useState(false)
  const idAbas = useId()
  const caminho = aba === 'novosConvertidos' ? '/painel/novos-convertidos' : `/painel/aniversarios?periodo=${periodo}`
  const consulta = useQuery({ queryKey: ['pessoas', 'datas', caminho], queryFn: () => listarRecurso(caminho) })
  const data = (valor: string | null | undefined): string => (valor ? formatarData(valor, i18n.language) : t('datas.semData'))

  const mudarBusca = (novos: Record<string, string | undefined>): void => {
    void navegar({ to: '.', search: ((anterior: Record<string, unknown>) => ({ ...anterior, ...novos })) as never, replace: true })
  }

  return (
    <PaginaDoPainel titulo={t('datas.titulo')} descricao={t('datas.descricao')}>
      <Tabs value={aba} onChange={(_, nova: Aba) => mudarBusca({ aba: nova === 'datas' ? 'datas' : undefined })} variant="scrollable" allowScrollButtonsMobile aria-label={t('datas.abas')}>
        <Tab value="novosConvertidos" label={t('datas.novosConvertidos')} id={`${idAbas}-novosConvertidos`} aria-controls={`${idAbas}-painel`} />
        <Tab value="datas" label={t('datas.datas')} id={`${idAbas}-datas`} aria-controls={`${idAbas}-painel`} />
      </Tabs>

      <Box role="tabpanel" id={`${idAbas}-painel`} aria-labelledby={`${idAbas}-${aba}`} sx={{ display: 'grid', gap: 2, minWidth: 0 }}>
        <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1.5, alignItems: 'center', justifyContent: 'space-between' }}>
          {aba === 'datas' ? (
            <ToggleButtonGroup exclusive size="small" color="primary" value={periodo} aria-label={t('datas.periodo')} onChange={(_, novo: Periodo | null) => novo && mudarBusca({ periodo: novo === 'mes' ? undefined : novo })}>
              {PERIODOS.map((item) => (
                <ToggleButton key={item} value={item} sx={{ px: 2, textTransform: 'none', fontWeight: 600 }}>
                  {t(`datas.periodos.${item}`)}
                </ToggleButton>
              ))}
            </ToggleButtonGroup>
          ) : (
            <span />
          )}
          <Button variant="outlined" onClick={() => setExportando(true)} disabled={!consulta.isSuccess || consulta.data.data.length === 0}>
            {t('datas.exportar')}
          </Button>
        </Box>

        {consulta.isPending && <EstadoCarregando texto={t('datas.carregando')} />}
        {consulta.isError && <EstadoErro titulo={t('erros.tituloCarregar')} texto={mensagemDeErro(consulta.error)} rotuloTentar={t('comum.tentar')} aoTentarDeNovo={() => void consulta.refetch()} />}
        {consulta.isSuccess && consulta.data.data.length === 0 && (
          <Paper variant="outlined" sx={{ borderRadius: 4 }}>
            {aba === 'novosConvertidos' ? (
              <EstadoVazio titulo={t('datas.vazioConvertidos')} texto={t('datas.vazioConvertidosTexto')} />
            ) : (
              <EstadoVazio titulo={t('datas.vazioDatas')} texto={t('datas.vazioDatasTexto')} />
            )}
          </Paper>
        )}
        {consulta.isSuccess && consulta.data.data.length > 0 && (
          <Box component="ul" sx={{ listStyle: 'none', m: 0, p: 0, display: 'grid', gap: 1.5, gridTemplateColumns: 'repeat(auto-fill, minmax(min(100%, 18rem), 1fr))' }}>
            {aba === 'novosConvertidos' &&
              (consulta.data.data as NovoConvertido[]).map((item) => (
                <Paper component="li" key={item.uuid} variant="outlined" sx={{ p: 2, borderRadius: 4, display: 'grid', gap: 0.5 }}>
                  <LinkTexto to="/pessoas/$uuid" params={{ uuid: item.uuid }} sx={{ fontWeight: 600, justifySelf: 'start' }}>
                    {item.nome}
                  </LinkTexto>
                  <Typography variant="body2" color="text.secondary">
                    {item.conversao_data ? t('datas.convertidoEm', { data: data(item.conversao_data) }) : t('datas.semData')}
                  </Typography>
                  {item.conversao_canal && (
                    <Typography variant="body2" color="text.secondary">
                      {t('datas.canal', { canal: item.conversao_canal })}
                    </Typography>
                  )}
                </Paper>
              ))}
            {aba === 'datas' &&
              (consulta.data.data as DataComemorativa[]).map((item) => (
                // A mesma pessoa aparece uma vez por tipo de data: a chave junta os dois.
                <Paper component="li" key={`${item.uuid}-${item.tipo}`} variant="outlined" sx={{ p: 2, borderRadius: 4, display: 'grid', gap: 0.75 }}>
                  <LinkTexto to="/pessoas/$uuid" params={{ uuid: item.uuid }} sx={{ fontWeight: 600, justifySelf: 'start' }}>
                    {item.nome}
                  </LinkTexto>
                  <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1, alignItems: 'center' }}>
                    <Chip size="small" variant="outlined" color="primary" label={t(`datas.tipos.${item.tipo}` as never, { defaultValue: item.tipo })} />
                    <Typography variant="body2" color="text.secondary">
                      {data(item.data)}
                    </Typography>
                  </Box>
                </Paper>
              ))}
          </Box>
        )}
      </Box>

      <DialogoExportacao
        aberto={exportando}
        aoFechar={() => setExportando(false)}
        tipo={aba === 'novosConvertidos' ? 'novos_convertidos' : 'aniversarios'}
        // A API exporta datas por mês; com "hoje" ou "esta semana", o arquivo traz o mês inteiro e a tela avisa.
        filtro={aba === 'datas' ? { mes: new Date().getMonth() + 1 } : {}}
        texto={t('exportacao.textoTipo')}
        aviso={aba === 'datas' && periodo !== 'mes' ? t('exportacao.textoMes') : undefined}
      />
    </PaginaDoPainel>
  )
}
