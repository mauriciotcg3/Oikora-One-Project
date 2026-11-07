<?php

namespace App\Shared\Observabilidade;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/** GET /api/v1/saude: público e sem dado de organização ou pessoa (OIK-ADR-0011 §12). */
class SaudeController
{
    public function __invoke(): JsonResponse
    {
        /** @var list<string> $nomes */
        $nomes = config('oikora.filas');
        $filas = array_fill_keys($nomes, 0);

        try {
            $agora = now()->getTimestamp();
            $maisAntigos = DB::table('jobs')->selectRaw('queue, min(created_at) as criado_em')->groupBy('queue')->get();

            foreach ($maisAntigos as $fila) {
                $filas[$fila->queue] = max(0, $agora - (int) $fila->criado_em);
            }
        } catch (Throwable) {
            return $this->resposta('indisponivel', $filas, 503);
        }

        return $this->resposta('ok', $filas, 200);
    }

    /** @param array<string, int> $filas */
    private function resposta(string $banco, array $filas, int $status): JsonResponse
    {
        return response()->json([
            'vivo' => true,
            'banco' => $banco,
            'versao' => config('oikora.versao'),
            'filas' => $filas,
        ], $status);
    }
}
