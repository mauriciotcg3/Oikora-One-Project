<?php

namespace App\Modules\Pessoas\Presentation;

use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Pessoas\Application\Consultas\ListarDatasDaFe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

final class DatasDaFeController
{
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
}
