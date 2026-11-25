<?php

namespace App\Modules\Pessoas\Presentation;

use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Pessoas\Application\Actions\SolicitarExportacao;
use App\Modules\Pessoas\Application\Exportacoes\ArquivosDeExportacao;
use App\Modules\Pessoas\Domain\Situacao;
use App\Modules\Pessoas\Domain\Vinculo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

final readonly class ExportacoesController
{
    public function criar(Request $request, SolicitarExportacao $solicitar): JsonResponse
    {
        $dados = $request->validate([
            'tipo' => ['required', Rule::in(['pessoas', 'aniversarios', 'novos_convertidos'])],
            'formato' => ['required', Rule::in(['xlsx', 'csv'])],
            'filtro' => ['sometimes', 'array:igreja,vinculo,situacao,tipo_membro,funcao,ficha_incompleta,aniversariantes_mes,mes'],
            'filtro.igreja' => ['sometimes', 'uuid'],
            'filtro.vinculo' => ['sometimes', Rule::enum(Vinculo::class)],
            'filtro.situacao' => ['sometimes', Rule::enum(Situacao::class)],
            'filtro.tipo_membro' => ['sometimes', 'uuid'],
            'filtro.funcao' => ['sometimes', 'uuid'],
            'filtro.ficha_incompleta' => ['sometimes', 'boolean'],
            'filtro.aniversariantes_mes' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'filtro.mes' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'selecionados' => ['sometimes', 'array', 'max:1000'], 'selecionados.*' => ['uuid'],
            'categorias' => ['required', 'array', 'min:1'], 'categorias.*' => [Rule::in(['gerais', 'contatos', 'fe'])],
        ]);
        $uuid = $solicitar->executar($dados['tipo'], $dados['filtro'] ?? [], $dados['selecionados'] ?? [], array_values(array_unique($dados['categorias'])), $dados['formato']);

        return $uuid === null
            ? new JsonResponse(['codigo' => 'export.denied'], Response::HTTP_FORBIDDEN)
            : new JsonResponse(['data' => ['uuid' => $uuid, 'estado' => 'pendente']], Response::HTTP_ACCEPTED);
    }

    public function mostrar(string $exportacao, AlcanceAtual $alcance, ArquivosDeExportacao $arquivos): JsonResponse
    {
        $ator = $alcance->ator();
        if ($alcance->igrejasCom(Permissao::PessoasExportar) === []) {
            return new JsonResponse(['codigo' => 'authz.denied'], Response::HTTP_FORBIDDEN);
        }
        $item = DB::table('exportacoes')->where('uuid', $exportacao)->where('acesso_id', $ator->acessoId)->first();
        if ($item === null) {
            return new JsonResponse(['codigo' => 'exportacao.nao_encontrada'], Response::HTTP_NOT_FOUND);
        }
        if ($item->estado === 'concluida' && now()->gte($item->expira_em)) {
            $item->estado = 'expirada';
        }
        $url = $item->estado === 'concluida' && is_string($item->arquivo_path) ? $arquivos->url($item->arquivo_path) : null;

        return new JsonResponse(['data' => [
            'uuid' => $item->uuid, 'tipo' => $item->tipo, 'formato' => $item->formato, 'estado' => $item->estado,
            'contagem' => $item->contagem, 'expira_em' => $item->expira_em, 'url' => $url, 'motivo_codigo' => $item->motivo_codigo,
        ]]);
    }
}
