<?php

namespace App\Modules\Identidade\Presentation;

use App\Modules\Identidade\Application\FalhaDeDesafio;
use App\Modules\Identidade\Application\VerificarContato;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class VerificacaoContatoController
{
    public function pedir(string $tipo, VerificarContato $verificar): Response|JsonResponse
    {
        try {
            $temContato = $verificar->pedir($this->contaId(), $tipo);
        } catch (FalhaDeDesafio) {
            return $this->muitasTentativas();
        }

        if (! $temContato) {
            throw ValidationException::withMessages(['tipo' => ['A conta não tem esse contato.']]);
        }

        return response()->noContent();
    }

    public function confirmar(Request $request, string $tipo, VerificarContato $verificar): Response|JsonResponse
    {
        $dados = $request->validate(['codigo' => ['required', 'digits:6']]);

        try {
            $verificar->confirmar($this->contaId(), $tipo, (string) $dados['codigo'], (string) $request->ip());
        } catch (FalhaDeDesafio $falha) {
            return $falha->motivo === 'muitas_tentativas'
                ? $this->muitasTentativas()
                : throw ValidationException::withMessages(['codigo' => ['Código inválido.']]);
        }

        return response()->noContent();
    }

    private function muitasTentativas(): JsonResponse
    {
        return response()->json(['codigo' => 'muitas_tentativas', 'message' => 'Muitas tentativas. Aguarde para tentar de novo.'], 429);
    }

    private function contaId(): int
    {
        return (int) Auth::guard('web')->id();
    }
}
