import Button, { type ButtonProps } from '@mui/material/Button'
import Link, { type LinkProps } from '@mui/material/Link'
import { createLink, type LinkComponent } from '@tanstack/react-router'
import { forwardRef } from 'react'

// Navegação dentro do painel com a aparência do UI Standard: o `Link` do TanStack Router troca a rota sem
// recarregar a página (mantém o cache das consultas) e continua sendo um `<a>` com `href` (abrir em nova aba).

const BotaoAncora = forwardRef<HTMLAnchorElement, Omit<ButtonProps<'a'>, 'component'>>(function BotaoAncora(props, ref) {
  return <Button component="a" ref={ref} {...props} />
})

const TextoAncora = forwardRef<HTMLAnchorElement, LinkProps>(function TextoAncora(props, ref) {
  return <Link ref={ref} {...props} />
})

const BotaoCriado = createLink(BotaoAncora)
const TextoCriado = createLink(TextoAncora)

/** Botão que navega para uma rota do painel (ex.: "Cadastrar pessoa"). */
export const BotaoLink: LinkComponent<typeof BotaoAncora> = (props) => <BotaoCriado preload="intent" {...props} />

/** Link de texto para uma rota do painel (ex.: "Voltar para Pessoas", nome na tabela). */
export const LinkTexto: LinkComponent<typeof TextoAncora> = (props) => <TextoCriado preload="intent" {...props} />
