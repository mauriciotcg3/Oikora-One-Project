<?php

namespace App\Modules\Identidade\Presentation;

use App\Modules\Identidade\Application\PedirRecuperacao;
use App\Modules\Identidade\Application\RedefinirSenha;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

final class RecuperacaoSenhaController
{
    public function esqueci(Request $request, PedirRecuperacao $pedir): Response|JsonResponse
    {
        $dados = $request->validate(['identificador' => ['required', 'string', 'max:255']]);

        if (! $pedir->executar($dados['identificador'], (string) $request->ip())) {
            return response()->json(['codigo' => 'muitas_tentativas', 'message' => 'Muitas tentativas. Aguarde para tentar de novo.'], 429);
        }

        return response()->noContent();
    }

    public function redefinir(Request $request, RedefinirSenha $redefinir): Response
    {
        $dados = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'senha' => ['required', 'string', 'max:1024', Password::defaults()],
        ]);

        if (! $redefinir->executar($dados['token'], $dados['senha'], (string) $request->ip())) {
            throw ValidationException::withMessages(['token' => ['Este link não vale mais. Peça outro.']]);
        }

        // A sessão desta requisição também acaba: salva depois da limpeza, ela voltaria autenticada.
        Auth::guard('web')->logout();
        $request->session()->invalidate();

        return response()->noContent();
    }
}
