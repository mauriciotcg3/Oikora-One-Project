<?php

namespace App\Modules\Identidade\Presentation;

use App\Modules\Autorizacao\Domain\EscopoTipo;
use App\Modules\Identidade\Application\AceitarConvite;
use App\Modules\Identidade\Application\Actions\ConvidarAction;
use App\Modules\Identidade\Application\Actions\Resultado;
use App\Modules\Identidade\Application\Actions\RevogarAcessoAction;
use App\Modules\Identidade\Application\Actions\RevogarConviteAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Convite ao painel e revogação de acesso (plan.md §7; T-22). No painel: `POST /painel/convites`,
 * `DELETE /painel/convites/{id}` e `DELETE /painel/acessos/{id}`. Pelo link: `GET /convites/{token}`
 * (dados mínimos) e `POST /convites/{token}/aceitar` (com sessão, liga a conta logada; sem sessão, cria a
 * conta com a senha informada).
 */
final class ConvitesController
{
    public function convidar(Request $request, ConvidarAction $acao): JsonResponse
    {
        $dados = $request->validate([
            'pessoa' => ['required', 'uuid'],
            'papel' => ['required', 'uuid'],
            'escopo_tipo' => ['required', Rule::enum(EscopoTipo::class)],
            'escopo' => ['nullable', 'uuid', Rule::requiredIf(fn (): bool => $request->input('escopo_tipo') !== EscopoTipo::Organizacao->value)],
        ]);

        return $this->responder($acao->executar($dados['pessoa'], $dados['papel'], EscopoTipo::from($dados['escopo_tipo']), $dados['escopo'] ?? null));
    }

    public function revogarConvite(string $convite, RevogarConviteAction $acao): JsonResponse
    {
        return $this->responder($acao->executar($convite));
    }

    public function revogarAcesso(string $acesso, RevogarAcessoAction $acao): JsonResponse
    {
        return $this->responder($acao->executar($acesso));
    }

    public function mostrar(string $token, AceitarConvite $aceite): JsonResponse
    {
        return $this->responder($aceite->dados($token));
    }

    public function aceitar(Request $request, string $token, AceitarConvite $aceite): JsonResponse
    {
        $contaId = Auth::guard('web')->id();
        $senha = null;
        if ($contaId === null) {
            $senha = $request->validate(['senha' => ['required', 'string', 'confirmed', Password::defaults()]])['senha'];
        }

        return $this->responder($aceite->aceitar($token, $contaId === null ? null : (int) $contaId, $senha));
    }

    private function responder(Resultado $resultado): JsonResponse
    {
        if ($resultado->status === 422 && isset($resultado->dados['erros'])) {
            /** @var array<string, string> $erros */
            $erros = $resultado->dados['erros'];

            throw ValidationException::withMessages(array_map(static fn (string $erro): array => [$erro], $erros));
        }
        if (! $resultado->sucesso()) {
            return new JsonResponse(['codigo' => $resultado->codigo, ...$resultado->dados], $resultado->status);
        }
        if ($resultado->status === 204) {
            return new JsonResponse(null, 204);
        }

        return new JsonResponse($resultado->dados, $resultado->status);
    }
}
