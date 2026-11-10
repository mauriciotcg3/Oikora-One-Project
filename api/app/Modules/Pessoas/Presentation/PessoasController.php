<?php

namespace App\Modules\Pessoas\Presentation;

use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Organizacao\Contracts\CatalogoIgrejas;
use App\Modules\Pessoas\Application\Consultas\BuscaDenominacao;
use App\Modules\Pessoas\Application\Consultas\FichaInexistente;
use App\Modules\Pessoas\Application\Consultas\FichaNegada;
use App\Modules\Pessoas\Application\Consultas\LerFicha;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/** `GET /painel/pessoas/{id}` e `GET /painel/busca?q=` (plan.md §7). */
final class PessoasController
{
    public function mostrar(string $pessoa, LerFicha $lerFicha): JsonResponse
    {
        try {
            $ficha = $lerFicha->executar($pessoa);
        } catch (FichaNegada) {
            Log::info('authz.denied', ['permissao' => Permissao::PessoasVer->value]);

            return new JsonResponse(['codigo' => 'authz.denied'], Response::HTTP_FORBIDDEN);
        } catch (FichaInexistente) {
            return new JsonResponse(['codigo' => 'pessoa_inexistente'], Response::HTTP_NOT_FOUND);
        }

        $dados = [
            'uuid' => $ficha->uuid,
            'igreja' => $ficha->igreja,
            'vinculo' => $ficha->vinculo,
            'situacao' => $ficha->situacao,
            'campos' => $ficha->campos,
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

    public function buscar(Request $request, AlcanceAtual $alcance, BuscaDenominacao $busca, CatalogoIgrejas $igrejas): JsonResponse
    {
        $termo = trim((string) $request->validate(['q' => ['required', 'string', 'min:3', 'max:120']])['q']);

        $comBusca = $alcance->igrejasCom(Permissao::PessoasVer);
        if ($comBusca === []) {
            return new JsonResponse(['codigo' => 'authz.denied'], Response::HTTP_FORBIDDEN);
        }

        $achadas = $busca->buscar($termo);
        $resumo = $igrejas->resumo(array_values(array_unique(array_column($achadas, 'igreja_id'))));

        return new JsonResponse(['data' => array_map(static fn (array $pessoa): array => [
            'uuid' => $pessoa['uuid'],
            'nome' => $pessoa['nome'],
            'igreja' => $resumo[$pessoa['igreja_id']] ?? null,
            'vinculo' => $pessoa['vinculo'],
            'meta' => ['acoes' => in_array($pessoa['igreja_id'], $comBusca, true) ? ['abrir'] : []],
        ], $achadas)]);
    }
}
