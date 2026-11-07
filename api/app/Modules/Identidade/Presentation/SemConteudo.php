<?php

namespace App\Modules\Identidade\Presentation;

use Illuminate\Http\Response;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\LogoutResponse;

/** Login e logout respondem 204: quem precisa dos dados da conta pede `GET /me` (T-16). */
final class SemConteudo implements LoginResponse, LogoutResponse
{
    public function toResponse($request): Response
    {
        return response()->noContent();
    }
}
