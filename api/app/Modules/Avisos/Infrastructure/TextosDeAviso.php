<?php

namespace App\Modules\Avisos\Infrastructure;

use App\Modules\Avisos\Application\MensagemDeAviso;
use App\Modules\Avisos\Application\TextosDaCaixa;

/**
 * Textos do template de e-mail por tipo. Só entram o nome da organização e o link: nada da ficha (nome da
 * pessoa, documento, contato), para o e-mail não carregar dado pessoal (OIK-ADR-0008 §7, §9).
 */
final class TextosDeAviso
{
    /** @return array{assunto: string, titulo: string, texto: string, acao: string, link: string|null, organizacao: string, descadastro: string|null} */
    public static function dados(MensagemDeAviso $mensagem): array
    {
        $organizacao = self::nomeSeguro($mensagem->nomeOrganizacao);
        ['titulo' => $titulo, 'texto' => $texto, 'acao' => $acao] = TextosDaCaixa::do($mensagem->tipo, $organizacao);

        return [
            'assunto' => "{$titulo} — {$organizacao}",
            'titulo' => $titulo,
            'texto' => $texto,
            'acao' => $acao,
            'link' => $mensagem->link === null ? null : rtrim((string) config('app.url'), '/').'/'.ltrim($mensagem->link, '/'),
            'organizacao' => $organizacao,
            'descadastro' => $mensagem->linkDescadastro,
        ];
    }

    /** Nome sem aspas nem quebra de linha, para não quebrar o cabeçalho `From` nem o template. */
    public static function nomeSeguro(string $nome): string
    {
        $limpo = trim((string) preg_replace('/[\r\n"<>]+/', ' ', $nome));

        return $limpo === '' ? 'Oikora' : $limpo;
    }
}
