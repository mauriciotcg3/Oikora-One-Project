<?php

namespace App\Modules\Identidade\Presentation;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Mudar senha ou 2FA exige ter confirmado a senha nos últimos 15 minutos (OIK-ADR-0003 §11). */
final class ExigirSenhaConfirmada
{
    public const CHAVE = 'senha_confirmada_em';

    public function handle(Request $request, Closure $proximo): Response
    {
        $confirmadaEm = (int) $request->session()->get(self::CHAVE, 0);
        $validade = (int) config('oikora.senha.confirmacao_minutos') * 60;

        if ($confirmadaEm + $validade <= now()->getTimestamp()) {
            return response()->json(['codigo' => 'senha_nao_confirmada', 'message' => 'Confirme sua senha para continuar.'], 403);
        }

        return $proximo($request);
    }
}
