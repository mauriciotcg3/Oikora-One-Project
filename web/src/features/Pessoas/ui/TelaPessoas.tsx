import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Chip from '@mui/material/Chip'
import Paper from '@mui/material/Paper'
import Typography from '@mui/material/Typography'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useNavigate } from '@tanstack/react-router'
import { useMemo, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import {
  BotaoLink,
  EstadoErro,
  FiltrosNaUrl,
  PaginaDoPainel,
  TabelaDeRegistros,
  useFiltrosDaUrl,
  usePaginacaoDaUrl,
  type CampoDeFiltro,
  type ColunaDaTabela,
} from '../../../shared/ui'
import { listarPessoas, listarRecurso, type PessoaResumo } from '../api/pessoas'
import { useMensagemDeErro } from './comum'
import { DialogoExportacao } from './DialogoExportacao'

type LinhaPessoa = PessoaResumo & { id: string }

/** Lista de pessoas (T-41): filtros e página na URL, tabela paginada no servidor e exportação com retorno. */
export function TelaPessoas(): ReactNode {
  const { t } = useTranslation('pessoas')
  const navegar = useNavigate()
  const mensagemDeErro = useMensagemDeErro()
  const [selecionadas, setSelecionadas] = useState<ReadonlySet<string>>(new Set())
  const [exportando, setExportando] = useState(false)
  const igrejas = useQuery({ queryKey: ['pessoas', 'igrejas'], queryFn: () => listarRecurso('/painel/igrejas'), staleTime: 60_000 })

  const campos = useMemo<readonly CampoDeFiltro[]>(() => {
    const opcoesIgreja = (igrejas.data?.data ?? []).map((igreja) => ({ valor: igreja.uuid, rotulo: String(igreja.nome ?? '') }))
    return [
      ...(opcoesIgreja.length > 1 ? [{ chave: 'igreja', rotulo: t('lista.igreja'), opcoes: opcoesIgreja }] : []),
      { chave: 'vinculo', rotulo: t('lista.vinculo'), opcoes: (['visitante', 'frequentador', 'membro'] as const).map((v) => ({ valor: v, rotulo: t(`vinculos.${v}`) })) },
      { chave: 'situacao', rotulo: t('lista.situacao'), opcoes: (['ativa', 'inativa'] as const).map((s) => ({ valor: s, rotulo: t(`situacoes.${s}`) })) },
    ]
  }, [igrejas.data, t])
  const filtros = useFiltrosDaUrl(campos)
  const paginacao = usePaginacaoDaUrl()
  const consulta = useQuery({
    queryKey: ['pessoas', 'lista', filtros, paginacao.pagina, paginacao.porPagina],
    queryFn: () => listarPessoas({ ...filtros, pagina: paginacao.pagina, por_pagina: paginacao.porPagina }),
    placeholderData: keepPreviousData,
  })

  const linhas: LinhaPessoa[] = (consulta.data?.data ?? []).map((pessoa) => ({ ...pessoa, id: pessoa.uuid }))
  const podeExportar = (linha: LinhaPessoa): boolean => linha.meta?.acoes?.includes('exportar') ?? false
  const semFiltros = Object.values(filtros).every((valor) => valor === undefined)

  const colunas: readonly ColunaDaTabela<LinhaPessoa>[] = [
    { chave: 'nome', rotulo: t('lista.nome'), valor: (linha) => linha.nome },
    { chave: 'igreja', rotulo: t('lista.igreja'), valor: (linha) => linha.igreja?.nome ?? t('comum.semIgreja') },
    {
      chave: 'vinculo',
      rotulo: t('lista.vinculo'),
      valor: (linha) => (linha.vinculo ? t(`vinculos.${linha.vinculo}` as never, { defaultValue: linha.vinculo }) : t('comum.naoInformado')),
    },
    {
      chave: 'situacao',
      rotulo: t('lista.situacao'),
      valor: (linha) =>
        linha.situacao ? (
          <Chip
            size="small"
            variant="outlined"
            color={linha.situacao === 'ativa' ? 'success' : 'default'}
            label={t(`situacoes.${linha.situacao}` as never, { defaultValue: linha.situacao })}
          />
        ) : (
          t('comum.naoInformado')
        ),
    },
  ]

  const vazioDeVerdade = consulta.isSuccess && consulta.data.meta.total === 0 && semFiltros

  return (
    <PaginaDoPainel
      titulo={t('lista.titulo')}
      descricao={t('lista.descricao')}
      acoes={
        <>
          <BotaoLink to="/pessoas/busca" variant="outlined">
            {t('lista.buscar')}
          </BotaoLink>
          <BotaoLink to="/pessoas/nova" variant="contained">
            {t('lista.cadastrar')}
          </BotaoLink>
        </>
      }
    >
      <Paper variant="outlined" sx={{ p: { xs: 2, md: 2.5 }, borderRadius: 4 }}>
        <FiltrosNaUrl campos={campos} rotuloAplicar={t('lista.aplicar')} rotuloLimpar={t('lista.limpar')} rotuloGrupo={t('lista.filtros')} rotuloTodos={t('lista.todos')} />
      </Paper>

      <Box
        role="status"
        sx={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 1, minHeight: 40 }}
      >
        {selecionadas.size > 0 ? (
          <>
            <Typography sx={{ fontWeight: 600, mr: 1 }}>{t('lista.selecionadas', { count: selecionadas.size })}</Typography>
            <Button variant="contained" size="small" onClick={() => setExportando(true)}>
              {t('lista.exportarSelecao')}
            </Button>
            <Button size="small" onClick={() => setSelecionadas(new Set())}>
              {t('lista.limparSelecao')}
            </Button>
          </>
        ) : (
          consulta.isSuccess &&
          consulta.data.meta.total > 0 && (
            <Button variant="outlined" size="small" onClick={() => setExportando(true)}>
              {t('lista.exportarLista')}
            </Button>
          )
        )}
      </Box>

      {consulta.isError && (
        <EstadoErro titulo={t('erros.tituloCarregar')} texto={mensagemDeErro(consulta.error)} rotuloTentar={t('comum.tentar')} aoTentarDeNovo={() => void consulta.refetch()} />
      )}
      {vazioDeVerdade && (
        <Paper variant="outlined" sx={{ p: 4, borderRadius: 4, textAlign: 'center', display: 'grid', gap: 1, justifyItems: 'center' }}>
          <Typography component="h2" variant="h2">
            {t('lista.vazioTitulo')}
          </Typography>
          <Typography color="text.secondary">{t('lista.vazioTexto')}</Typography>
          <BotaoLink to="/pessoas/nova" variant="contained" sx={{ mt: 1 }}>
            {t('lista.cadastrar')}
          </BotaoLink>
        </Paper>
      )}
      {!consulta.isError && !vazioDeVerdade && (
        <TabelaDeRegistros
          rotulo={t('lista.tabela')}
          colunas={colunas}
          linhas={linhas}
          textoFalha=""
          rotuloAbrir={(linha) => t('comum.abrirFichaDe', { nome: linha.nome })}
          textoAbrir={t('comum.abrirFicha')}
          aoAbrir={(linha) => void navegar({ to: '/pessoas/$uuid', params: { uuid: linha.uuid } })}
          paginacao={{ pagina: paginacao.pagina, porPagina: paginacao.porPagina, total: consulta.data?.meta.total ?? 0, aoMudar: paginacao.mudar, carregando: consulta.isFetching }}
          textos={{
            linhasPorPagina: t('lista.linhasPorPagina'),
            exibindo: (de, ate, total) => t('lista.exibindo', { de, ate, total }),
            paginaAnterior: t('lista.paginaAnterior'),
            proximaPagina: t('lista.proximaPagina'),
            semLinhas: t('lista.semRegistros'),
          }}
          selecao={{
            selecionadas,
            aoMudar: setSelecionadas,
            podeSelecionar: podeExportar,
            rotuloLinha: (linha) => t('lista.selecionarPessoa', { nome: linha.nome }),
            rotuloPagina: t('lista.selecionarPagina'),
            rotuloColuna: t('lista.selecionar'),
          }}
          textoCarregando={t('comum.carregando')}
        />
      )}

      <DialogoExportacao
        aberto={exportando}
        aoFechar={() => setExportando(false)}
        tipo="pessoas"
        filtro={selecionadas.size > 0 ? {} : Object.fromEntries(Object.entries(filtros).filter(([, valor]) => valor !== undefined))}
        selecionados={[...selecionadas]}
        texto={selecionadas.size > 0 ? t('exportacao.textoSelecao', { count: selecionadas.size }) : t('exportacao.textoFiltros')}
      />
    </PaginaDoPainel>
  )
}
