<?php

namespace App\Modules\Identidade\Presentation;

use App\Modules\Identidade\Application\FalhaDeDesafio;
use App\Modules\Identidade\Application\GerirDoisFatores;
use App\Modules\Identidade\Application\SenhaDaConta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

final class ContaController
{
    public function confirmarSenha(Request $request, SenhaDaConta $senhas): Response|JsonResponse
    {
        $dados = $request->validate(['senha' => ['required', 'string', 'max:1024']]);

        try {
            $confere = $senhas->confirmar($this->contaId(), $dados['senha']);
        } catch (FalhaDeDesafio) {
            return response()->json(['codigo' => 'muitas_tentativas', 'message' => 'Muitas tentativas. Aguarde para tentar de novo.'], 429);
        }

        if (! $confere) {
            throw ValidationException::withMessages(['senha' => ['A senha não confere.']]);
        }

        $request->session()->put(ExigirSenhaConfirmada::CHAVE, now()->getTimestamp());

        return response()->noContent();
    }

    public function estadoDoisFatores(GerirDoisFatores $gerir): JsonResponse
    {
        return response()->json($gerir->estado($this->contaId()));
    }

    public function ativarDoisFatores(Request $request, GerirDoisFatores $gerir): JsonResponse
    {
        $dados = $request->validate([
            'canal' => ['required', Rule::in(['email', 'whatsapp', 'totp'])],
            'codigo' => ['nullable', 'digits:6'],
        ]);
        $ip = (string) $request->ip();

        if ($dados['canal'] !== 'totp') {
            $codigos = $gerir->ativarPorMensagem($this->contaId(), $dados['canal'], $ip);

            return $codigos === null
                ? throw ValidationException::withMessages(['canal' => ['Verifique este contato antes de usá-lo.']])
                : response()->json(['canal' => $dados['canal'], 'codigos_recuperacao' => $codigos]);
        }

        if (! isset($dados['codigo'])) {
            return response()->json($gerir->iniciarTotp($this->contaId())
                ?? throw ValidationException::withMessages(['canal' => ['Desative o 2FA antes de cadastrar outro aplicativo.']]));
        }

        $codigos = $gerir->concluirTotp($this->contaId(), (string) $dados['codigo'], $ip)
            ?? throw ValidationException::withMessages(['codigo' => ['Código inválido.']]);

        return response()->json(['canal' => 'totp', 'codigos_recuperacao' => $codigos]);
    }

    public function desativarDoisFatores(Request $request, GerirDoisFatores $gerir): Response|JsonResponse
    {
        if (! $gerir->desativar($this->contaId(), (string) $request->ip())) {
            return response()->json(['codigo' => 'dois_fatores_obrigatorio', 'message' => 'Seu papel exige o 2FA.'], 403);
        }

        return response()->noContent();
    }

    public function trocarSenha(Request $request, SenhaDaConta $senhas): Response
    {
        $dados = $request->validate(['senha' => ['required', 'string', 'max:1024', Password::defaults()]]);

        $senhas->trocar($this->contaId(), $dados['senha'], (string) $request->ip());

        // A sessão desta requisição também acaba: salva depois da limpeza, ela voltaria autenticada.
        Auth::guard('web')->logout();
        $request->session()->invalidate();

        return response()->noContent();
    }

    private function contaId(): int
    {
        return (int) Auth::guard('web')->id();
    }
}
