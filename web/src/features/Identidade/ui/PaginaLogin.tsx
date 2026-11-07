import { useEffect, useEffectEvent, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { FalhaAutenticacao, type DesafioDoisFatores, type MotivoFalha } from '../api/autenticacao'
import { normalizarIdentificador } from '../lib/identificador'
import { useConfirmarDoisFatores, useEntrar, useEnviarCodigo, usePedirRecuperacaoSenha } from '../model/useAutenticacao'
import { FormularioCodigo, type RespostaAcao } from './FormularioCodigo'
import { FormularioEsqueci } from './FormularioEsqueci'
import { FormularioLogin, type DadosLogin } from './FormularioLogin'
import { FormularioRecuperacao } from './FormularioRecuperacao'
import { IconeConfirmado, IconeEmail } from './icones'
import { LayoutLogin } from './LayoutLogin'
import { TelaConcluida } from './TelaConcluida'

// Fluxo do login do painel (T-38): entrar → código de 2FA ou de recuperação → painel;
// e "esqueci a senha". Telas e textos do protótipo aprovado em 2026-11-01.

type Etapa = 'login' | 'codigo' | 'recuperacao' | 'esqueci' | 'esqueciEnviado' | 'concluido'

const ABRIR_PAINEL_EM_MS = 800

const motivoDe = (erro: unknown): MotivoFalha => (erro instanceof FalhaAutenticacao ? erro.motivo : 'falha')

interface Props {
  /** Chamado depois do login concluído, para a entrada abrir o painel. */
  aoEntrar: () => void
}

export function PaginaLogin({ aoEntrar }: Props): ReactNode {
  const { t } = useTranslation('identidade')
  const [etapa, setEtapa] = useState<Etapa>('login')
  const [navegou, setNavegou] = useState(false)
  const [desafio, setDesafio] = useState<DesafioDoisFatores | null>(null)
  const entrar = useEntrar()
  const confirmar = useConfirmarDoisFatores()
  const enviarCodigo = useEnviarCodigo()
  const pedirRecuperacao = usePedirRecuperacaoSenha()

  const irPara = (proxima: Etapa): void => {
    setNavegou(true)
    setEtapa(proxima)
  }

  const abrirPainel = useEffectEvent(aoEntrar)
  useEffect(() => {
    if (etapa !== 'concluido') return
    const id = window.setTimeout(abrirPainel, ABRIR_PAINEL_EM_MS)
    return () => window.clearTimeout(id)
  }, [etapa])

  const mensagem = (motivo: MotivoFalha, seIncorreto: string): string =>
    t(motivo === 'muitas_tentativas' ? 'erros.muitasTentativas' : motivo === 'codigo_incorreto' ? seIncorreto : 'erros.falha')

  const aoEnviarLogin = async ({ identificador, senha }: DadosLogin): Promise<MotivoFalha | null> => {
    try {
      const resultado = await entrar.mutateAsync({ identificador: normalizarIdentificador(identificador), senha })
      if (resultado.tipo === 'dois_fatores') {
        setDesafio(resultado.desafio)
        irPara('codigo')
      } else {
        irPara('concluido')
      }
      return null
    } catch (erro) {
      return motivoDe(erro)
    }
  }

  const confirmarCom = async (
    confirmacao: Parameters<typeof confirmar.mutateAsync>[0],
    seIncorreto: string,
  ): Promise<RespostaAcao> => {
    try {
      await confirmar.mutateAsync(confirmacao)
      irPara('concluido')
      return null
    } catch (erro) {
      return mensagem(motivoDe(erro), seIncorreto)
    }
  }

  const reenviarPor = async (canal: DesafioDoisFatores['canal']): Promise<RespostaAcao> => {
    try {
      setDesafio(await enviarCodigo.mutateAsync(canal))
      return null
    } catch (erro) {
      return mensagem(motivoDe(erro), 'erros.falha')
    }
  }

  const aoPedirRecuperacao = async (identificador: string): Promise<RespostaAcao> => {
    try {
      await pedirRecuperacao.mutateAsync(normalizarIdentificador(identificador))
      irPara('esqueciEnviado')
      return null
    } catch (erro) {
      return mensagem(motivoDe(erro), 'erros.falha')
    }
  }

  const voltarAoLogin = (): void => {
    entrar.reset()
    irPara('login')
  }

  const falhaLogin = entrar.isError ? motivoDe(entrar.error) : null

  return (
    <LayoutLogin>
      {etapa === 'login' ? (
        <FormularioLogin
          aoEnviar={aoEnviarLogin}
          enviando={entrar.isPending}
          falha={falhaLogin}
          aoEsquecer={() => irPara('esqueci')}
          focarAoMontar={navegou}
        />
      ) : null}
      {etapa === 'codigo' && desafio ? (
        <FormularioCodigo
          desafio={desafio}
          aoConfirmar={(codigo) => confirmarCom({ codigo }, 'erros.codigoIncorreto')}
          confirmando={confirmar.isPending}
          aoReenviar={() => reenviarPor(desafio.canal)}
          aoTrocarCanal={() => reenviarPor(desafio.canal === 'email' ? 'whatsapp' : 'email')}
          aoUsarRecuperacao={() => irPara('recuperacao')}
          aoVoltar={voltarAoLogin}
        />
      ) : null}
      {etapa === 'recuperacao' ? (
        <FormularioRecuperacao
          aoConfirmar={(codigo) => confirmarCom({ codigo_recuperacao: codigo }, 'erros.codigoRecuperacaoInvalido')}
          confirmando={confirmar.isPending}
          aoVoltar={() => irPara('codigo')}
        />
      ) : null}
      {etapa === 'esqueci' ? (
        <FormularioEsqueci aoEnviar={aoPedirRecuperacao} enviando={pedirRecuperacao.isPending} aoVoltar={voltarAoLogin} />
      ) : null}
      {etapa === 'esqueciEnviado' ? (
        <TelaConcluida
          id="titulo-esqueci-enviado"
          icone={<IconeEmail />}
          titulo={t('esqueci.okTitulo')}
          texto={t('esqueci.okTexto')}
          acao={{ rotulo: t('voltarLogin'), aoClicar: voltarAoLogin }}
        />
      ) : null}
      {etapa === 'concluido' ? (
        <TelaConcluida id="titulo-concluido" icone={<IconeConfirmado />} titulo={t('concluido.titulo')} texto={t('concluido.texto')} />
      ) : null}
    </LayoutLogin>
  )
}
