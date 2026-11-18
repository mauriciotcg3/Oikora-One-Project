<?php

namespace App\Modules\Avisos\Presentation;

use App\Modules\Avisos\Application\Descadastrar;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Descadastro em um clique (RFC 8058): o provedor de e-mail ou a pessoa fazem POST no link assinado do
 * `List-Unsubscribe`. Sem sessão; a assinatura (middleware `signed`) é a autorização. Responde 204 mesmo
 * quando nada muda, para o link não revelar se o contato existe.
 */
final class DescadastroController
{
    public function __invoke(Request $request, Descadastrar $descadastrar): Response
    {
        $descadastrar->porLink((string) $request->query('o'), (string) $request->query('p'), (string) $request->query('c'));

        return response()->noContent();
    }
}
