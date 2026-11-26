<?php

namespace App\Modules\Pessoas\Presentation;

use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Pessoas\Application\Actions\RegistrarBatismo;
use App\Modules\Pessoas\Application\Actions\RegistrarConversao;
use App\Modules\Pessoas\Application\Configuracao\Resultado;
use App\Modules\Pessoas\Application\Consultas\ListarDatasDaFe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

final class DatasDaFeController
{
    public function registrarConversao(Request $request, string $pessoa, RegistrarConversao $registrar): JsonResponse
    {
        $dados = $request->validate([
            'data' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'canal' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        return $this->responder($registrar->executar($pessoa, $dados['data'], $this->texto($dados['canal'] ?? null)));
    }

    public function registrarBatismo(Request $request, string $pessoa, RegistrarBatismo $registrar): JsonResponse
    {
        $dados = $request->validate([
            'data' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'batizado_por' => ['sometimes', 'nullable', 'string', 'max:160'],
        ]);

        return $this->responder($registrar->executar($pessoa, $dados['data'], $this->texto($dados['batizado_por'] ?? null)));
    }

    public function novosConvertidos(ListarDatasDaFe $listar): JsonResponse
    {
        if (! $listar->permitido()) {
            return $this->negado();
        }
        $resultado = $listar->novosConvertidos();

        return new JsonResponse(['data' => $resultado['itens'], 'meta' => ['criterio' => $resultado['criterio']]]);
    }

    public function aniversarios(Request $request, ListarDatasDaFe $listar): JsonResponse
    {
        $periodo = $request->validate(['periodo' => ['sometimes', Rule::in(['dia', 'semana', 'mes'])]])['periodo'] ?? 'mes';
        if (! $listar->permitido()) {
            return $this->negado();
        }
        $resultado = $listar->aniversarios($periodo);

        return new JsonResponse(['data' => $resultado['itens'], 'meta' => ['criterio' => $resultado['criterio']]]);
    }

    private function negado(): JsonResponse
    {
        Log::info('authz.denied', ['module' => 'Pessoas', 'action' => 'listar_datas_fe', 'permissao' => Permissao::FeVer->value]);

        return new JsonResponse(['codigo' => 'authz.denied'], Response::HTTP_FORBIDDEN);
    }

    private function responder(Resultado $resultado): JsonResponse
    {
        if (! $resultado->sucesso()) {
            return new JsonResponse(['codigo' => $resultado->codigo], $resultado->status);
        }

        return new JsonResponse(['data' => ['alterados' => $resultado->dados['alterados'] ?? []]]);
    }

    private function texto(mixed $valor): ?string
    {
        if (! is_string($valor)) {
            return null;
        }

        $valor = trim($valor);

        return $valor === '' ? null : $valor;
    }
}
