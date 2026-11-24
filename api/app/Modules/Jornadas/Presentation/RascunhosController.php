<?php

namespace App\Modules\Jornadas\Presentation;

use App\Modules\Jornadas\Application\RascunhoDesatualizado;
use App\Modules\Jornadas\Application\RascunhosJornada;
use App\Modules\Jornadas\Domain\TipoJornada;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class RascunhosController
{
    public function ler(TipoJornada $tipo, RascunhosJornada $rascunhos): JsonResponse
    {
        return response()->json(['data' => $rascunhos->ler($tipo)]);
    }

    public function salvar(Request $request, TipoJornada $tipo, RascunhosJornada $rascunhos): JsonResponse
    {
        $dados = $request->validate([
            'etapa' => ['required', 'string', 'max:80'],
            'dados' => ['present', 'array'],
            'dados.*' => ['nullable', 'string', 'max:500'],
            'versao' => ['required', 'integer', 'min:0'],
        ]);
        $permitidos = [...$tipo->camposPublicos(), ...$tipo->camposSensiveis()];
        foreach (array_keys($dados['dados']) as $campo) {
            if (! in_array($campo, $permitidos, true)) {
                throw ValidationException::withMessages(["dados.{$campo}" => 'Este dado não pode ser persistido no rascunho.']);
            }
        }

        try {
            return response()->json(['data' => $rascunhos->salvar($tipo, $dados['etapa'], $dados['dados'], $dados['versao'])]);
        } catch (RascunhoDesatualizado $erro) {
            return response()->json(['codigo' => 'rascunho_desatualizado', 'atual' => $erro->atual], Response::HTTP_CONFLICT);
        }
    }

    public function descartar(TipoJornada $tipo, RascunhosJornada $rascunhos): Response
    {
        $rascunhos->descartar($tipo);

        return response()->noContent();
    }
}
