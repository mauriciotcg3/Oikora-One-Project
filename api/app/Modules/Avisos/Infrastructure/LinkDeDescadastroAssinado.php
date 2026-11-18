<?php

namespace App\Modules\Avisos\Infrastructure;

use App\Modules\Avisos\Application\LinksDeDescadastro;
use Illuminate\Support\Facades\URL;

/**
 * Link de descadastro com assinatura HMAC do Laravel (chave do app), sem expiração: o e-mail pode ser
 * aberto meses depois. Leva só identificadores públicos e o canal.
 */
final class LinkDeDescadastroAssinado implements LinksDeDescadastro
{
    public const ROTA = 'avisos.descadastro';

    public function paraPessoa(string $organizacaoUuid, string $pessoaUuid, string $canal): string
    {
        return URL::signedRoute(self::ROTA, ['o' => $organizacaoUuid, 'p' => $pessoaUuid, 'c' => $canal]);
    }
}
