<?php

namespace App\Modules\Pessoas\Presentation;

use App\Modules\Pessoas\Application\Consultas\Ficha;
use Illuminate\Http\JsonResponse;

/** Corpo da ficha em `GET` e `PATCH /painel/pessoas/{id}`: projeção, completude, foto e `meta.acoes`. */
final class FichaResposta
{
    public static function de(Ficha $ficha): JsonResponse
    {
        $dados = [
            'uuid' => $ficha->uuid,
            'igreja' => $ficha->igreja,
            'vinculo' => $ficha->vinculo,
            'situacao' => $ficha->situacao,
            'campos' => $ficha->campos,
            'foto_url' => $ficha->fotoUrl,
            'completude' => $ficha->completude === null ? null : [
                'percentual' => $ficha->completude->percentual,
                'obrigatorios' => $ficha->completude->obrigatorios,
                'faltando' => $ficha->completude->faltando,
            ],
        ];
        if ($ficha->contatos !== null) {
            $dados['contatos'] = $ficha->contatos;
            $dados['enderecos'] = $ficha->enderecos;
        }

        return new JsonResponse([
            'data' => $dados,
            'meta' => ['acoes' => $ficha->acoes, 'categorias' => $ficha->categorias],
        ]);
    }
}
