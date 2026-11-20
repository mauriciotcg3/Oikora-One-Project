<?php

namespace App\Modules\Pessoas\Presentation;

use App\Modules\Pessoas\Application\Actions\AlterarSituacaoAction;
use App\Modules\Pessoas\Application\Actions\AlterarVinculoAction;
use App\Modules\Pessoas\Application\Configuracao\ListasDeValores;
use App\Modules\Pessoas\Application\Configuracao\Resultado;
use App\Modules\Pessoas\Application\Consultas\LerFicha;
use App\Modules\Pessoas\Domain\ListaValores;
use App\Modules\Pessoas\Domain\Situacao;
use App\Modules\Pessoas\Domain\Vinculo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * `POST /painel/pessoas/{id}/vinculo` e `POST /painel/pessoas/{id}/situacao` (plan.md §7; T-28).
 * O motivo é obrigatório e vem da lista da organização: `motivo_vinculo`, `motivo_inativacao` ou
 * `motivo_reativacao` (U-28). Recusa de regra volta como 422 com `codigo` e `mensagem`.
 */
final class VinculoController
{
    public function __construct(private readonly ListasDeValores $listas) {}

    public function vinculo(Request $request, string $pessoa, AlterarVinculoAction $acao, LerFicha $lerFicha): JsonResponse
    {
        $dados = $request->validate([
            'vinculo' => ['required', Rule::enum(Vinculo::class)],
            'motivo' => ['required', 'uuid'],
            'matricula' => ['sometimes', 'nullable', 'string', 'max:40'],
        ]);
        $destino = Vinculo::from($dados['vinculo']);
        $matricula = isset($dados['matricula']) && trim($dados['matricula']) !== '' ? trim($dados['matricula']) : null;
        if ($matricula !== null && ! $destino->temMatricula()) {
            throw ValidationException::withMessages(['matricula' => ['matricula_so_para_membro']]);
        }

        $motivo = $this->motivo(ListaValores::MotivoVinculo, $dados['motivo']);

        return $this->responder($acao->executar($pessoa, $destino, $motivo, $matricula), $pessoa, $lerFicha);
    }

    public function situacao(Request $request, string $pessoa, AlterarSituacaoAction $acao, LerFicha $lerFicha): JsonResponse
    {
        $dados = $request->validate([
            'situacao' => ['required', Rule::enum(Situacao::class)],
            'motivo' => ['required', 'uuid'],
        ]);
        $destino = Situacao::from($dados['situacao']);
        $motivo = $this->motivo($destino->listaDeMotivos(), $dados['motivo']);

        return $this->responder($acao->executar($pessoa, $destino, $motivo), $pessoa, $lerFicha);
    }

    private function motivo(ListaValores $lista, string $uuid): int
    {
        return $this->listas->idAtivo($lista, $uuid)
            ?? throw ValidationException::withMessages(['motivo' => ['motivo_inexistente_ou_inativo']]);
    }

    private function responder(Resultado $resultado, string $pessoa, LerFicha $lerFicha): JsonResponse
    {
        if (! $resultado->sucesso()) {
            return new JsonResponse(['codigo' => $resultado->codigo, ...$resultado->dados], $resultado->status);
        }

        return FichaResposta::de($lerFicha->executar($pessoa));
    }
}
