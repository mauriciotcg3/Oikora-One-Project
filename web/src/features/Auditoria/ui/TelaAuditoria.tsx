import Box from '@mui/material/Box'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useMemo, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { ErroHttp } from '../../../shared/http'
import { DetalheEmCamadas, EstadoErro, FiltrosNaUrl, TabelaDeRegistros, useFiltrosDaUrl, usePaginacaoDaUrl, type CampoDeFiltro, type ColunaDaTabela, type ItemTecnico } from '../../../shared/ui'
import { consultarAuditoria, type FiltroAuditoria, type LinhaAuditoria } from '../api/auditoria'

export function TelaAuditoria(): ReactNode {
  const { t, i18n } = useTranslation('auditoria')
  const campos = useMemo<readonly CampoDeFiltro[]>(() => [
    { chave: 'de', rotulo: t('de') }, { chave: 'ate', rotulo: t('ate') }, { chave: 'evento', rotulo: t('evento') },
    { chave: 'ator', rotulo: t('ator') }, { chave: 'alvo_tipo', rotulo: t('tipoAlvo') }, { chave: 'alvo', rotulo: t('alvo') },
    { chave: 'igreja', rotulo: t('igreja') }, { chave: 'busca', rotulo: t('busca') },
    { chave: 'resultado', rotulo: t('resultado'), opcoes: [{ valor: 'sucesso', rotulo: t('sucesso') }, { valor: 'falha', rotulo: t('falha') }] },
  ], [t])
  const filtros = useFiltrosDaUrl(campos)
  const paginacao = usePaginacaoDaUrl()
  const [selecionada, setSelecionada] = useState<LinhaAuditoria>()
  const filtro: FiltroAuditoria = { ...filtros, pagina: paginacao.pagina, por_pagina: paginacao.porPagina }
  const consulta = useQuery({ queryKey: ['auditoria', filtro], queryFn: () => consultarAuditoria(filtro), placeholderData: keepPreviousData })

  const traduzirEvento = (evento: string): string => t(`eventos.${evento.replaceAll('.', '_')}`, { defaultValue: evento })
  const formatarData = (iso: string): string => new Intl.DateTimeFormat(i18n.language, { dateStyle: 'short', timeStyle: 'short' }).format(new Date(iso))
  const colunas: readonly ColunaDaTabela<LinhaAuditoria>[] = [
    { chave: 'quando', rotulo: t('quando'), valor: (linha) => formatarData(linha.ocorrido_em) },
    { chave: 'evento', rotulo: t('evento'), valor: (linha) => traduzirEvento(linha.evento) },
    { chave: 'ator', rotulo: t('ator'), valor: (linha) => linha.ator.nome ?? linha.ator.acesso ?? t('sistema') },
    { chave: 'alvo', rotulo: t('alvo'), valor: (linha) => `${linha.alvo.tipo} ${linha.alvo.id}` },
    { chave: 'igreja', rotulo: t('igreja'), valor: (linha) => linha.igreja?.nome ?? '—' },
  ]
  const erro = consulta.error instanceof ErroHttp ? consulta.error : undefined
  const itensTecnicos: ItemTecnico[] = selecionada ? [
    { rotulo: t('campos'), valor: selecionada.campos.length ? selecionada.campos.join(', ') : '—' },
    { rotulo: t('metadados'), valor: JSON.stringify(selecionada.metadados) },
    { rotulo: t('canal'), valor: selecionada.canal ?? '—' },
    ...(selecionada.codigo_falha ? [{ rotulo: t('codigoFalha'), valor: selecionada.codigo_falha }] : []),
    ...(selecionada.request_id ? [{ rotulo: 'request_id', valor: selecionada.request_id }] : []),
    ...(selecionada.trace_id ? [{ rotulo: 'trace_id', valor: selecionada.trace_id }] : []),
  ] : []

  return (
    <Box component="main" sx={{ p: 3, maxWidth: 1400, mx: 'auto' }}><Stack spacing={2}>
      <Typography variant="h1" sx={{ fontSize: '1.75rem' }}>
        {filtros.alvo_tipo && filtros.alvo ? t('tituloTrilha', { tipo: filtros.alvo_tipo, alvo: filtros.alvo }) : t('titulo')}
      </Typography>
      <FiltrosNaUrl campos={campos} rotuloAplicar={t('aplicar')} rotuloLimpar={t('limpar')} rotuloGrupo={t('filtros')} rotuloTodos={t('todos')} />
      {consulta.isError && <EstadoErro titulo={t('erroTitulo')} texto={t('erro')} rotuloTentar={t('tentar')} aoTentarDeNovo={() => { void consulta.refetch() }} detalhes={(erro?.corpo.request_id || erro?.traceId) ? {
        rotulo: t('detalhesTecnicos'), rotuloCopiar: t('copiar'), itens: [
          ...(erro.corpo.request_id ? [{ rotulo: 'request_id', valor: erro.corpo.request_id }] : []),
          ...(erro.traceId ? [{ rotulo: 'trace_id', valor: erro.traceId }] : []),
        ],
      } : undefined} />}
      {!consulta.isError && <TabelaDeRegistros
        rotulo={t('titulo')} colunas={colunas} linhas={consulta.data?.itens ?? []} textoFalha={t('falha')}
        rotuloAbrir={(linha) => t('verDetalhes', { evento: traduzirEvento(linha.evento) })} aoAbrir={setSelecionada}
        paginacao={{ pagina: paginacao.pagina, porPagina: paginacao.porPagina, total: consulta.data?.total ?? 0, aoMudar: paginacao.mudar, carregando: consulta.isFetching }}
        textos={{ linhasPorPagina: t('linhasPorPagina'), exibindo: (de, ate, total) => t('exibindo', { de, ate, total }), paginaAnterior: t('paginaAnterior'), proximaPagina: t('proximaPagina'), semLinhas: t('semRegistros') }}
        textoCarregando={t('carregando')}
      />}
    </Stack>
    <DetalheEmCamadas aberto={Boolean(selecionada)} titulo={selecionada ? traduzirEvento(selecionada.evento) : ''} resumo={selecionada ? [
      { rotulo: t('quando'), valor: formatarData(selecionada.ocorrido_em) },
      { rotulo: t('ator'), valor: selecionada.ator.nome ?? selecionada.ator.acesso ?? t('sistema') },
      { rotulo: t('alvo'), valor: `${selecionada.alvo.tipo} ${selecionada.alvo.id}` },
      { rotulo: t('igreja'), valor: selecionada.igreja?.nome ?? '—' },
      { rotulo: t('resultado'), valor: selecionada.resultado === 'falha' ? t('falha') : t('sucesso') },
    ] : []} tecnico={{ rotulo: t('detalhesTecnicos'), rotuloCopiar: t('copiar'), itens: itensTecnicos }} rotuloFechar={t('fechar')} aoFechar={() => setSelecionada(undefined)} />
    </Box>
  )
}
