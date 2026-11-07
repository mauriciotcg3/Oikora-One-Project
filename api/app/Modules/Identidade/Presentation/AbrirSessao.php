<?php

namespace App\Modules\Identidade\Presentation;

use App\Modules\Identidade\Application\Entrar;
use App\Modules\Identidade\Application\FalhaDeLogin;
use Closure;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/** Etapa do pipeline de login do Fortify: traduz o resultado de `Entrar` para sessão e contrato HTTP. */
final class AbrirSessao
{
    public function __construct(private readonly Entrar $entrar) {}

    public function handle(EntrarRequest $request, Closure $proximo): Responsable|Response
    {
        try {
            $contaId = $this->entrar->executar(
                $request->string('identificador')->toString(),
                $request->string('senha')->toString(),
                (string) $request->ip(),
            );
        } catch (FalhaDeLogin $falha) {
            return $this->recusar($falha);
        }

        Auth::guard('web')->loginUsingId($contaId);
        $request->session()->regenerate();

        return $proximo($request);
    }

    private function recusar(FalhaDeLogin $falha): JsonResponse
    {
        return match ($falha->motivo) {
            'muitas_tentativas' => response()->json(
                ['codigo' => 'muitas_tentativas', 'message' => 'Muitas tentativas. Aguarde para tentar de novo.'],
                429,
                ['Retry-After' => $falha->segundosDeEspera],
            ),
            'conta_bloqueada' => response()->json(['codigo' => 'conta_bloqueada', 'message' => 'Conta bloqueada.'], 403),
            'dois_fatores_necessario' => response()->json(
                ['codigo' => 'dois_fatores_necessario', 'canal' => $falha->canal, 'destino' => $falha->destino],
                403,
            ),
            // Mesma resposta para senha errada e conta inexistente (OIK-ADR-0003 §4).
            default => throw ValidationException::withMessages(['identificador' => ['Os dados não conferem.']]),
        };
    }
}
