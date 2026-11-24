<?php

namespace App\Modules\Avisos\Presentation;

use App\Modules\Avisos\Application\PreferenciaRecusada;
use App\Modules\Avisos\Application\PreferenciasDeAviso;
use App\Modules\Avisos\Domain\TipoAviso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * `GET|PUT /conta/preferencias-aviso` (OIK-ADR-0008 §13; plan.md §7): por tipo e canal, na organização
 * ativa. Ligadas por padrão. Convite não se desliga; serviço só troca o canal externo (um fica ligado).
 */
final class PreferenciasAvisoController
{
    public function mostrar(Request $request, PreferenciasDeAviso $preferencias): JsonResponse
    {
        $contaId = (int) $request->user()?->getAuthIdentifier();

        return new JsonResponse(['data' => $this->lista($preferencias, $contaId)]);
    }

    public function definir(Request $request, PreferenciasDeAviso $preferencias): JsonResponse
    {
        $dados = $request->validate([
            'tipo' => ['required', Rule::enum(TipoAviso::class)],
            'canal' => ['required', Rule::in(['email', 'whatsapp'])],
            'ligado' => ['required', 'boolean'],
        ]);
        $contaId = (int) $request->user()?->getAuthIdentifier();

        try {
            $preferencias->definir($contaId, TipoAviso::from($dados['tipo']), $dados['canal'], (bool) $dados['ligado']);
        } catch (PreferenciaRecusada $recusa) {
            throw ValidationException::withMessages(['canal' => [$recusa->getMessage()]]);
        }

        return new JsonResponse(['data' => $this->lista($preferencias, $contaId)]);
    }

    /** @return list<array{tipo: string, categoria: string, obrigatorio: bool, permite_escolher_canal: bool, canais: list<array{canal: string, ligado: bool}>}> */
    private function lista(PreferenciasDeAviso $preferencias, int $contaId): array
    {
        return array_map(static function (TipoAviso $tipo) use ($preferencias, $contaId): array {
            $ligados = $preferencias->ligados($contaId, $tipo);

            return [
                'tipo' => $tipo->value,
                'categoria' => $tipo->categoria(),
                'obrigatorio' => $tipo->obrigatorio(),
                'permite_escolher_canal' => $tipo->permiteEscolherCanal(),
                'canais' => array_map(static fn (string $canal): array => [
                    'canal' => $canal,
                    'ligado' => in_array($canal, $ligados, true),
                ], $tipo->canais()),
            ];
        }, TipoAviso::cases());
    }
}
