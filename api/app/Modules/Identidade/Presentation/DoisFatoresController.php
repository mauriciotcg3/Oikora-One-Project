<?php

namespace App\Modules\Identidade\Presentation;

use App\Modules\Identidade\Application\ConfirmarDoisFatores;
use App\Modules\Identidade\Application\FalhaDeDesafio;
use App\Modules\Identidade\Application\ReenviarCodigo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class DoisFatoresController
{
    public function confirmar(Request $request, ConfirmarDoisFatores $confirmar): Response|JsonResponse
    {
        $desafio = DesafioPendente::ler($request);
        if ($desafio === null) {
            return $this->semDesafio();
        }

        $dados = $request->validate(['codigo' => ['required', 'digits:6']]);

        try {
            $confirmar->executar($desafio->contaId, (string) $dados['codigo'], $desafio->identificadorResumo, (string) $request->ip());
        } catch (FalhaDeDesafio $falha) {
            return $this->recusar($request, $falha, 'codigo', 'Código inválido.');
        }

        DesafioPendente::fechar($request);
        Auth::guard('web')->loginUsingId($desafio->contaId);
        $request->session()->regenerate();

        return response()->noContent();
    }

    public function enviar(Request $request, ReenviarCodigo $reenviar): JsonResponse
    {
        $desafio = DesafioPendente::ler($request);
        if ($desafio === null) {
            return $this->semDesafio();
        }

        $dados = $request->validate(['canal' => ['required', Rule::in(['email', 'whatsapp'])]]);

        if ($desafio->reenvios >= (int) config('oikora.codigos.reenvios')) {
            return $this->muitasTentativas();
        }

        try {
            $destino = $reenviar->executar($desafio->contaId, $dados['canal']);
        } catch (FalhaDeDesafio $falha) {
            return $this->recusar($request, $falha, 'canal', 'Canal indisponível.');
        }

        if ($destino === null) {
            throw ValidationException::withMessages(['canal' => ['Canal indisponível.']]);
        }

        DesafioPendente::contarReenvio($request);

        return response()->json(['canal' => $dados['canal'], 'destino' => $destino]);
    }

    private function recusar(Request $request, FalhaDeDesafio $falha, string $campo, string $mensagem): JsonResponse
    {
        if ($falha->motivo === 'conta_bloqueada') {
            DesafioPendente::fechar($request);

            return response()->json(['codigo' => 'conta_bloqueada', 'message' => 'Conta bloqueada.'], 403);
        }

        if ($falha->motivo === 'muitas_tentativas') {
            return $this->muitasTentativas();
        }

        throw ValidationException::withMessages([$campo => [$mensagem]]);
    }

    private function muitasTentativas(): JsonResponse
    {
        return response()->json(['codigo' => 'muitas_tentativas', 'message' => 'Muitas tentativas. Peça um novo código ou entre de novo.'], 429);
    }

    private function semDesafio(): JsonResponse
    {
        return response()->json(['codigo' => 'sem_desafio', 'message' => 'Entre de novo para receber um código.'], 401);
    }
}
