<?php

namespace App\Modules\Avisos\Infrastructure;

use App\Modules\Avisos\Application\MensagemDeAviso;
use App\Modules\Avisos\Domain\TipoAviso;

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
        [$titulo, $texto, $acao] = match ($mensagem->tipo) {
            TipoAviso::ConvitePainel => ['Convite para o painel', "Você foi convidado(a) para acessar o painel de {$organizacao} no Oikora.", 'Aceitar convite'],
            TipoAviso::TransferenciaSolicitada => ['Pedido de transferência', "Há um pedido de transferência aguardando sua análise em {$organizacao}.", 'Ver pedido'],
            TipoAviso::TransferenciaDecidida => ['Transferência decidida', "Um pedido de transferência de {$organizacao} foi decidido.", 'Ver transferência'],
            TipoAviso::ExportacaoPronta => ['Exportação pronta', "A exportação que você pediu em {$organizacao} está pronta para baixar.", 'Baixar arquivo'],
        };

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
