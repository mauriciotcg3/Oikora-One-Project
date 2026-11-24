import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Typography from '@mui/material/Typography'
import { useEffect, useRef, useState, type ReactNode } from 'react'
import type { FieldValues, Path, UseFormReturn } from 'react-hook-form'
import { aplicarErros422, etapaComErro422, ResumoErros, type ErroForaDaTela } from '../../forms'
import { registrarEventoUi } from '../../observabilidade'
import { ConfirmacaoIrreversivel, type ConfirmacaoIrreversivelProps } from '../ConfirmacaoIrreversivel'
import type { PersistenciaJornada } from './rascunho'

export interface EtapaDaJornada<T extends FieldValues> {
  chave: string
  titulo: string
  /** Campos validados ao avançar e usados para levar o erro 422 à etapa certa. */
  campos: readonly Path<T>[]
  conteudo: ReactNode
}

export interface TextosDaJornada {
  anterior: string
  proximo: string
  concluir: string
  /** Anúncio da etapa atual, ex.: "Etapa 2 de 4". */
  etapaDe: (atual: number, total: number) => string
  /** Título do resumo de erros de campos que não estão em nenhuma etapa. */
  errosFora: string
}

export interface JornadaGuiadaProps<T extends FieldValues> {
  formulario: UseFormReturn<T>
  etapas: readonly EtapaDaJornada<T>[]
  textos: TextosDaJornada
  /** Envio ao concluir; um 422 volta à etapa do campo com erro. Outros erros são da feature. */
  aoConcluir: (valores: T) => Promise<void> | void
  /** Erro que não é 422 (ou 422 sem campo da jornada): a feature decide o que mostrar. */
  aoFalhar?: (erro: unknown) => void
  /** Quando a conclusão é irreversível, pede confirmação antes de executar. */
  confirmacao?: Omit<ConfirmacaoIrreversivelProps, 'aberta' | 'aoConfirmar' | 'aoCancelar'>
  /** Retomada opt-in conforme OIK-ADR-0016; nunca persiste sem configuração explícita. */
  persistencia?: PersistenciaJornada<T>
}

/**
 * Jornada guiada sobre React Hook Form: validação da etapa junto ao campo, foco no primeiro erro,
 * valores preservados ao voltar e confirmação opcional ao concluir (OIK-ADR-0014). Salvar e
 * retomar fica fora (OIK-ADR-0016).
 */
export function JornadaGuiada<T extends FieldValues>({ formulario, etapas, textos, aoConcluir, aoFalhar, confirmacao, persistencia }: JornadaGuiadaProps<T>): ReactNode {
  const [indice, setIndice] = useState(0)
  const [confirmando, setConfirmando] = useState(false)
  const [errosFora, setErrosFora] = useState<readonly ErroForaDaTela[]>([])
  const focoPendente = useRef<Path<T> | null>(null)
  const etapasIniciais = useRef(etapas)
  const etapa = etapas[indice]
  const ultima = indice === etapas.length - 1

  useEffect(() => {
    if (!persistencia) return
    let ativa = true
    void persistencia.carregar().then((rascunho) => {
      if (!ativa || rascunho === null) return
      formulario.reset(rascunho.dados)
      const retomada = etapasIniciais.current.findIndex((item) => item.chave === rascunho.etapa)
      if (retomada >= 0) setIndice(retomada)
    }).catch(aoFalhar)
    return () => { ativa = false }
  }, [aoFalhar, formulario, persistencia])

  // O foco do erro 422 espera a etapa certa estar na tela: o campo precisa estar montado.
  useEffect(() => {
    if (focoPendente.current === null) return
    formulario.setFocus(focoPendente.current)
    focoPendente.current = null
  })

  if (!etapa) return null

  const concluir = async (): Promise<void> => {
    try {
      await aoConcluir(formulario.getValues())
      await persistencia?.descartar()
      registrarEventoUi('ui.jornada_concluida', { etapas: etapas.length })
    } catch (erro) {
      const alvo = etapaComErro422(erro, etapas.map((e) => e.campos))
      if (alvo < 0) {
        aoFalhar?.(erro)
        return
      }
      setIndice(alvo)
      const adiarFoco = { setError: formulario.setError, setFocus: (campo: Path<T>) => void (focoPendente.current = campo) }
      setErrosFora(aplicarErros422(erro, adiarFoco, etapas.flatMap((e) => e.campos)) ?? [])
    }
  }

  const avancar = async (): Promise<void> => {
    setErrosFora([])
    const valida = await formulario.trigger([...etapa.campos], { shouldFocus: true })
    if (!valida) {
      registrarEventoUi('ui.jornada_etapa_invalida', { etapa: etapa.chave })
      return
    }
    if (!ultima) {
      const proxima = indice + 1
      const etapaSeguinte = etapas[proxima]
      if (!etapaSeguinte) return
      if (persistencia) {
        try {
          const salvo = await persistencia.salvar(etapaSeguinte.chave, formulario.getValues())
          formulario.reset(salvo.dados)
          const retomada = etapas.findIndex((item) => item.chave === salvo.etapa)
          setIndice(retomada >= 0 ? retomada : proxima)
        } catch (erro) {
          aoFalhar?.(erro)
        }
      } else setIndice(proxima)
      return
    }
    if (confirmacao) setConfirmando(true)
    else await concluir()
  }

  return (
    <Box component="section" aria-label={etapa.titulo} sx={{ display: 'grid', gap: 2 }}>
      <Typography role="status" color="text.secondary">
        {textos.etapaDe(indice + 1, etapas.length)}
      </Typography>
      <Typography component="h2" variant="h5">
        {etapa.titulo}
      </Typography>
      <ResumoErros erros={errosFora} titulo={textos.errosFora} />
      <Box>{etapa.conteudo}</Box>
      <Box sx={{ display: 'flex', justifyContent: 'space-between', gap: 1 }}>
        <Button variant="outlined" disabled={indice === 0} onClick={() => setIndice(indice - 1)}>
          {textos.anterior}
        </Button>
        <Button variant="contained" onClick={() => void avancar()}>
          {ultima ? textos.concluir : textos.proximo}
        </Button>
      </Box>
      {confirmacao && (
        <ConfirmacaoIrreversivel
          {...confirmacao}
          aberta={confirmando}
          aoCancelar={() => setConfirmando(false)}
          aoConfirmar={() => {
            setConfirmando(false)
            void concluir()
          }}
        />
      )}
    </Box>
  )
}
