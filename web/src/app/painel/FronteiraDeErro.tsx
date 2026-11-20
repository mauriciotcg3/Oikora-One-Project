import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Typography from '@mui/material/Typography'
import { Component, type ErrorInfo, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { ErroHttp } from '../../shared/http'
import { traceAtual } from '../../shared/observabilidade'

interface Props {
  children: ReactNode
}

interface Estado {
  traceId: string | null
}

function TelaDeErro({ traceId }: { traceId: string }): ReactNode {
  const { t } = useTranslation('painel')
  return (
    <Box component="main" role="alert" sx={{ minHeight: '100dvh', display: 'grid', placeContent: 'center', gap: 2, p: 3, textAlign: 'center' }}>
      <Typography variant="h1">{t('erro.titulo')}</Typography>
      <Typography color="text.secondary">{t('erro.texto')}</Typography>
      <Typography component="p" sx={{ fontFamily: 'monospace' }}>
        {t('erro.codigo', { traceId })}
      </Typography>
      <Button variant="contained" onClick={() => window.location.reload()}>
        {t('erro.recarregar')}
      </Button>
    </Box>
  )
}

/** Error Boundary da entrada `painel` (OIK-ADR-0006 §14): mostra e registra o `trace_id`. */
export class FronteiraDeErro extends Component<Props, Estado> {
  state: Estado = { traceId: null }

  static getDerivedStateFromError(erro: unknown): Estado {
    return { traceId: erro instanceof ErroHttp && erro.traceId ? erro.traceId : traceAtual() }
  }

  componentDidCatch(erro: unknown, info: ErrorInfo): void {
    // Sem dado pessoal: só o tipo do erro, o trace e onde quebrou.
    console.error('painel.erro', {
      trace_id: this.state.traceId,
      erro: erro instanceof Error ? erro.name : typeof erro,
      componente: info.componentStack?.split('\n').find((linha) => linha.trim() !== '')?.trim(),
    })
  }

  render(): ReactNode {
    return this.state.traceId === null ? this.props.children : <TelaDeErro traceId={this.state.traceId} />
  }
}
