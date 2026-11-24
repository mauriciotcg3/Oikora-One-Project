<?php

namespace App\Modules\Pessoas\Presentation;

use App\Modules\Organizacao\Contracts\CatalogoIgrejas;
use App\Modules\Pessoas\Application\Configuracao\Resultado;
use App\Modules\Pessoas\Application\Transferencias\AprovarTransferenciaAction;
use App\Modules\Pessoas\Application\Transferencias\LerTransferencias;
use App\Modules\Pessoas\Application\Transferencias\RecusarTransferenciaAction;
use App\Modules\Pessoas\Application\Transferencias\SolicitarTransferenciaAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Transferências entre igrejas (plan.md §7; T-34): `GET|POST /painel/transferencias`,
 * `POST /painel/transferencias/{id}/aprovar` e `POST /painel/transferencias/{id}/recusar`. Recusa de
 * regra volta como 422 com `codigo` e `mensagem` (ex.: `transferencia_pendente`).
 */
final class TransferenciasController
{
    public function listar(LerTransferencias $ler): JsonResponse
    {
        return new JsonResponse(['data' => $ler->executar()]);
    }

    public function solicitar(Request $request, SolicitarTransferenciaAction $acao, CatalogoIgrejas $igrejas): JsonResponse
    {
        $dados = $request->validate([
            'pessoa' => ['required', 'uuid'],
            'igreja_destino' => ['required', 'uuid'],
        ]);
        $destino = $igrejas->igrejaPorUuid($dados['igreja_destino'])
            ?? throw ValidationException::withMessages(['igreja_destino' => ['igreja_inexistente']]);

        return $this->responder($acao->executar($dados['pessoa'], $destino));
    }

    public function aprovar(string $transferencia, AprovarTransferenciaAction $acao): JsonResponse
    {
        return $this->responder($acao->executar($transferencia));
    }

    public function recusar(Request $request, string $transferencia, RecusarTransferenciaAction $acao): JsonResponse
    {
        $dados = $request->validate(['justificativa' => ['required', 'string', 'max:1000']]);
        $justificativa = trim($dados['justificativa']);
        if ($justificativa === '') {
            throw ValidationException::withMessages(['justificativa' => ['justificativa_obrigatoria']]);
        }

        return $this->responder($acao->executar($transferencia, $justificativa));
    }

    private function responder(Resultado $resultado): JsonResponse
    {
        if ($resultado->status === 422 && isset($resultado->dados['erros'])) {
            /** @var array<string, string> $erros */
            $erros = $resultado->dados['erros'];

            throw ValidationException::withMessages(array_map(static fn (string $erro): array => [$erro], $erros));
        }
        if (! $resultado->sucesso()) {
            return new JsonResponse(['codigo' => $resultado->codigo, ...$resultado->dados], $resultado->status);
        }

        return new JsonResponse($resultado->dados, $resultado->status);
    }
}
