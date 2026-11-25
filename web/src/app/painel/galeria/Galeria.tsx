import { zodResolver } from '@hookform/resolvers/zod'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Stack from '@mui/material/Stack'
import TextField from '@mui/material/TextField'
import ToggleButton from '@mui/material/ToggleButton'
import ToggleButtonGroup from '@mui/material/ToggleButtonGroup'
import Typography from '@mui/material/Typography'
import { useColorScheme } from '@mui/material/styles'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useState, type ReactNode } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { z } from 'zod'
import { ErroHttp } from '../../../shared/http'
import {
  AjudaContextual,
  ConfirmacaoIrreversivel,
  DetalheEmCamadas,
  DetalhesTecnicos,
  EstadoAviso,
  EstadoCarregando,
  EstadoErro,
  EstadoIndisponivel,
  EstadoSucesso,
  EstadoVazio,
  FiltrosNaUrl,
  JornadaGuiada,
  TabelaDeRegistros,
  useFiltrosDaUrl,
  usePaginacaoDaUrl,
  type CampoDeFiltro,
  type EtapaDaJornada,
} from '../../../shared/ui'

// Galeria do Oikora UI Standard: só existe no `pnpm dev` (a rota nem entra no build de produção).
// Mostra os componentes reais com dados de exemplo e uma "API" simulada em memória; não chama a API.
// Os textos ficam aqui, no mesmo lugar dos dados de exemplo, porque a página não é do produto.

const DETALHES = { rotulo: 'Detalhes técnicos', rotuloCopiar: 'Copiar', itens: [{ rotulo: 'request_id', valor: 'req_8f2c91' }, { rotulo: 'trace_id', valor: '4bb0e58b7fdbcd0446dd856e7ff5abde' }] }

function Secao({ titulo, texto, children }: { titulo: string; texto: string; children: ReactNode }): ReactNode {
  return (
    <Box component="section" aria-label={titulo} sx={{ display: 'grid', gap: 2, py: 4, borderTop: 1, borderColor: 'divider' }}>
      <Box>
        <Typography component="h2" variant="h5">
          {titulo}
        </Typography>
        <Typography color="text.secondary">{texto}</Typography>
      </Box>
      {children}
    </Box>
  )
}

function SeletorDeTema(): ReactNode {
  const { mode, setMode } = useColorScheme()
  return (
    <ToggleButtonGroup exclusive size="small" value={mode ?? 'system'} aria-label="Tema de cores" onChange={(_, novo: 'light' | 'dark' | 'system' | null) => novo && setMode(novo)}>
      <ToggleButton value="system">Sistema</ToggleButton>
      <ToggleButton value="light">Claro</ToggleButton>
      <ToggleButton value="dark">Escuro</ToggleButton>
    </ToggleButtonGroup>
  )
}

function Estados(): ReactNode {
  const [tentativas, setTentativas] = useState(0)
  return (
    <Stack spacing={2}>
      <EstadoCarregando texto="Carregando as pessoas" />
      <EstadoVazio titulo="Nenhuma pessoa encontrada" texto="Tente outro filtro ou cadastre a primeira pessoa." acao={{ rotulo: 'Cadastrar pessoa', aoClicar: () => undefined }} />
      <EstadoSucesso titulo="Cadastro salvo" texto="Maria Souza já aparece na lista." />
      <EstadoAviso titulo="Cadastro incompleto" texto="Falta o telefone. Você pode completar depois." />
      <EstadoErro titulo="Não foi possível salvar" texto="Tente de novo. Se continuar, fale com o suporte." rotuloTentar={`Tentar de novo${tentativas ? ` (${tentativas})` : ''}`} aoTentarDeNovo={() => setTentativas(tentativas + 1)} detalhes={DETALHES} />
      <EstadoIndisponivel titulo="Serviço indisponível" texto="Não conseguimos falar com o servidor agora." rotuloTentar="Tentar de novo" aoTentarDeNovo={() => undefined} detalhes={DETALHES} />
      <AjudaContextual rotulo="Ajuda sobre esta tela">Os estados explicam o que houve e o próximo passo; identificadores técnicos ficam sob demanda.</AjudaContextual>
      <DetalhesTecnicos {...DETALHES} />
    </Stack>
  )
}

const esquemaJornada = z.object({
  nome: z.string().min(1, 'Informe o nome'),
  email: z.email('Informe um e-mail válido'),
  igreja: z.string().min(1, 'Informe a igreja'),
})
type ValoresJornada = z.infer<typeof esquemaJornada>

function Jornada(): ReactNode {
  const [concluida, setConcluida] = useState<string | null>(null)
  const formulario = useForm<ValoresJornada>({ resolver: zodResolver(esquemaJornada), defaultValues: { nome: '', email: '', igreja: '' } })
  const campo = (nome: keyof ValoresJornada, rotulo: string): ReactNode => (
    <Controller
      name={nome}
      control={formulario.control}
      render={({ field, fieldState }) => (
        <TextField label={rotulo} name={field.name} value={field.value} onChange={field.onChange} onBlur={field.onBlur} inputRef={field.ref} error={fieldState.invalid} helperText={fieldState.error?.message} fullWidth sx={{ mb: 2 }} />
      )}
    />
  )
  const etapas: EtapaDaJornada<ValoresJornada>[] = [
    { chave: 'nome', titulo: 'Quem é a pessoa?', campos: ['nome'], conteudo: campo('nome', 'Nome') },
    { chave: 'contato', titulo: 'Como falar com ela?', campos: ['email'], conteudo: campo('email', 'E-mail') },
    { chave: 'igreja', titulo: 'Em qual igreja?', campos: ['igreja'], conteudo: campo('igreja', 'Igreja') },
  ]
  return (
    <Stack spacing={2}>
      <EstadoAviso titulo="Para ver o erro do servidor" texto='Digite o e-mail "repetido@exemplo.com": a "API" responde 422 e a jornada volta à etapa do e-mail.' />
      {concluida ? (
        <EstadoSucesso titulo="Jornada concluída" texto={concluida} />
      ) : (
        <JornadaGuiada
          formulario={formulario}
          etapas={etapas}
          textos={{ anterior: 'Voltar', proximo: 'Avançar', concluir: 'Concluir cadastro', etapaDe: (a, t) => `Etapa ${a} de ${t}`, errosFora: 'Corrija também:' }}
          confirmacao={{ titulo: 'Concluir o cadastro?', texto: 'Depois de concluir, a pessoa recebe o convite por e-mail.', rotuloConfirmar: 'Concluir', rotuloCancelar: 'Voltar' }}
          aoConcluir={(valores) => {
            if (valores.email === 'repetido@exemplo.com') throw new ErroHttp(422, { errors: { email: ['Este e-mail já está cadastrado'] } })
            setConcluida(`${valores.nome} (${valores.email}) — ${valores.igreja}`)
          }}
          aoFalhar={() => undefined}
        />
      )}
      {concluida && <Button variant="outlined" onClick={() => { setConcluida(null); formulario.reset() }}>Recomeçar</Button>}
    </Stack>
  )
}

function Confirmacao(): ReactNode {
  const [aberta, setAberta] = useState(false)
  const [feito, setFeito] = useState(false)
  return (
    <Stack spacing={2} sx={{ alignItems: 'flex-start' }}>
      <Button variant="outlined" color="error" onClick={() => { setFeito(false); setAberta(true) }}>Excluir cadastro</Button>
      {feito && <EstadoSucesso titulo="Cadastro excluído" />}
      <ConfirmacaoIrreversivel aberta={aberta} titulo="Excluir o cadastro?" texto="Não dá para desfazer. O histórico fica na auditoria." rotuloConfirmar="Excluir" rotuloCancelar="Manter" aoCancelar={() => setAberta(false)} aoConfirmar={() => { setAberta(false); setFeito(true) }} />
    </Stack>
  )
}

interface Registro {
  id: string
  evento: string
  ator: string
  situacao: 'ok' | 'falha'
  falha: boolean
  traceId: string
}

const EVENTOS = ['pessoa.criada', 'pessoa.atualizada', 'acesso.concedido'] as const
const ATORES = ['Ana Lima', 'Bruno Reis', 'Carla Dias'] as const

const REGISTROS: Registro[] = Array.from({ length: 60 }, (_, i) => {
  const falha = (i + 1) % 7 === 0
  return {
    id: String(i + 1),
    evento: `${EVENTOS[i % 3] ?? EVENTOS[0]} #${i + 1}`,
    ator: ATORES[i % 3] ?? ATORES[0],
    situacao: falha ? 'falha' : 'ok',
    falha,
    traceId: `trace-${(i + 1) * 7919}`,
  }
})

const CAMPOS: CampoDeFiltro[] = [
  { chave: 'busca', rotulo: 'Buscar por evento' },
  { chave: 'situacao', rotulo: 'Situação', opcoes: [{ valor: 'ok', rotulo: 'Sucesso' }, { valor: 'falha', rotulo: 'Falha' }] },
]

/** "API" em memória: devolve só a página pedida, depois de um atraso, como o servidor faria. */
async function buscarRegistros(busca: string | undefined, situacao: string | undefined, pagina: number, porPagina: number): Promise<{ data: Registro[]; total: number }> {
  await new Promise((resolver) => setTimeout(resolver, 400))
  const filtrados = REGISTROS.filter((r) => (!busca || r.evento.includes(busca)) && (!situacao || r.situacao === situacao))
  return { data: filtrados.slice((pagina - 1) * porPagina, pagina * porPagina), total: filtrados.length }
}

function Registros(): ReactNode {
  const filtros = useFiltrosDaUrl(CAMPOS)
  const { pagina, porPagina, mudar } = usePaginacaoDaUrl()
  const [aberto, setAberto] = useState<Registro | null>(null)
  const { data, isFetching } = useQuery({
    queryKey: ['galeria-registros', filtros, pagina, porPagina],
    queryFn: () => buscarRegistros(filtros.busca, filtros.situacao, pagina, porPagina),
    placeholderData: keepPreviousData,
  })
  return (
    <Stack spacing={2}>
      <FiltrosNaUrl campos={CAMPOS} rotuloAplicar="Aplicar filtros" rotuloLimpar="Limpar filtros" rotuloGrupo="Filtros" rotuloTodos="Todas" />
      <TabelaDeRegistros
        rotulo="Registros de auditoria de exemplo"
        colunas={[
          { chave: 'evento', rotulo: 'Evento', valor: (l) => l.evento },
          { chave: 'ator', rotulo: 'Quem fez', valor: (l) => l.ator },
        ]}
        linhas={data?.data ?? []}
        textoFalha="Falha"
        textoCarregando="Carregando a tabela"
        rotuloAbrir={(l) => `Ver detalhes de ${l.evento}`}
        aoAbrir={setAberto}
        paginacao={{ pagina, porPagina, total: data?.total ?? 0, aoMudar: mudar, carregando: isFetching }}
        textos={{ linhasPorPagina: 'Linhas por página', exibindo: (de, ate, total) => `${de}–${ate} de ${total}`, paginaAnterior: 'Página anterior', proximaPagina: 'Próxima página', semLinhas: 'Nenhum registro encontrado' }}
      />
      <DetalheEmCamadas
        aberto={aberto !== null}
        titulo="Detalhe do registro"
        resumo={[{ rotulo: 'Evento', valor: aberto?.evento ?? '' }, { rotulo: 'Quem fez', valor: aberto?.ator ?? '' }, { rotulo: 'Resultado', valor: aberto?.falha ? 'Falha' : 'Sucesso' }]}
        tecnico={{ rotulo: 'Detalhes técnicos', rotuloCopiar: 'Copiar', itens: [{ rotulo: 'trace_id', valor: aberto?.traceId ?? '' }] }}
        rotuloFechar="Fechar detalhe"
        aoFechar={() => setAberto(null)}
      />
    </Stack>
  )
}

export function Galeria(): ReactNode {
  return (
    <Box component="main" sx={{ maxWidth: 1000, mx: 'auto', p: 3, bgcolor: 'background.default', minHeight: '100dvh' }}>
      <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 2 }}>
        <Box>
          <Typography component="h1" variant="h1">
            Galeria da interface
          </Typography>
          <Typography color="text.secondary">Oikora UI Standard — só em desenvolvimento, com dados de exemplo.</Typography>
        </Box>
        <SeletorDeTema />
      </Stack>
      <Secao titulo="Estados" texto="Carregando, vazio, sucesso, aviso, erro e indisponível, com detalhes técnicos e ajuda."><Estados /></Secao>
      <Secao titulo="Jornada guiada" texto="Valida cada etapa junto ao campo, preserva o digitado ao voltar e confirma antes de concluir."><Jornada /></Secao>
      <Secao titulo="Confirmação de ação irreversível" texto="O foco começa em “Manter”; Esc cancela."><Confirmacao /></Secao>
      <Secao titulo="Filtros, tabela e detalhe" texto="60 registros de exemplo, paginados por uma API simulada: só a página atual é pedida. Filtros e página ficam na URL; a cada sétima linha há uma falha."><Registros /></Secao>
    </Box>
  )
}
