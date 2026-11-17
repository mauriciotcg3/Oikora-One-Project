<?php

namespace App\Modules\Identidade\Presentation;

use App\Modules\Identidade\Application\ElevarPainel;
use App\Modules\Identidade\Application\FalhaDeElevacao;
use App\Modules\Identidade\Application\RegistrarElevacao;
use App\Modules\Identidade\Application\SessaoDoPainel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/** `POST /api/v1/painel/elevacao`: senha e, quando a conta tem 2FA, o segundo fator (OIK-ADR-0003 §9). */
final class ElevacaoController
{
    public function __invoke(Request $request, ElevarPainel $elevar, RegistrarElevacao $registrar): Response|JsonResponse
    {
        $dados = $request->validate([
            'senha' => ['required', 'string', 'max:200'],
            'codigo' => ['nullable', 'digits:6'],
            'codigo_recuperacao' => ['nullable', 'string', 'max:20'],
        ]);
        $contaId = (int) $request->user()?->getAuthIdentifier();
        $ip = (string) $request->ip();

        try {
            $canal = $elevar->executar($contaId, $dados['senha'], $dados['codigo'] ?? null, $dados['codigo_recuperacao'] ?? null, $ip);
        } catch (FalhaDeElevacao $falha) {
            return $this->recusar($falha);
        }

        $sessao = $request->session();
        SessaoDoPainel::elevar($sessao);
        $sessao->regenerate();
        $organizacaoId = $request->attributes->get('organizacao_id');
        $registrar->executar($contaId, is_int($organizacaoId) ? $organizacaoId : null, 'dialogo', $canal, $ip);

        return response()->noContent();
    }

    private function recusar(FalhaDeElevacao $falha): JsonResponse
    {
        return match ($falha->motivo) {
            'senha_invalida' => throw ValidationException::withMessages(['senha' => ['Senha incorreta.']]),
            'codigo_invalido' => throw ValidationException::withMessages(['codigo' => ['Código inválido.']]),
            'muitas_tentativas' => response()->json(['codigo' => 'muitas_tentativas', 'message' => 'Muitas tentativas. Aguarde para tentar de novo.'], 429),
            'dois_fatores_necessario' => response()->json(['codigo' => 'dois_fatores_necessario', 'canal' => $falha->canal, 'destino' => $falha->destino], 403),
            'dois_fatores_cadastro_necessario' => response()->json([
                'codigo' => 'dois_fatores_cadastro_necessario',
                'message' => 'Cadastre a verificação em duas etapas para entrar no painel.',
            ], 403),
            default => response()->json(['codigo' => $falha->motivo], 403),
        };
    }
}
