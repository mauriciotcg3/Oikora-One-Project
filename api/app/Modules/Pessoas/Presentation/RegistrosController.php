<?php

namespace App\Modules\Pessoas\Presentation;

use App\Modules\Pessoas\Application\Actions\AtribuirFuncaoAction;
use App\Modules\Pessoas\Application\Actions\DefinirTipoMembroAction;
use App\Modules\Pessoas\Application\Actions\EncerrarFuncaoAction;
use App\Modules\Pessoas\Application\Actions\RegistrarEventoManualAction;
use App\Modules\Pessoas\Application\Actions\RelacionarAction;
use App\Modules\Pessoas\Application\Actions\RemoverRelacionamentoAction;
use App\Modules\Pessoas\Application\Configuracao\ListasDeValores;
use App\Modules\Pessoas\Application\Configuracao\Resultado;
use App\Modules\Pessoas\Application\Consultas\LerRegistros;
use App\Modules\Pessoas\Domain\ListaValores;
use App\Modules\Pessoas\Domain\TipoRelacionamento;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Linha do tempo, relacionamentos, funções e tipo de membro da ficha (plan.md §7; T-29):
 * `GET|POST /painel/pessoas/{id}/linha-do-tempo`, `GET|POST|DELETE /painel/pessoas/{id}/relacionamentos[/{id}]`,
 * `GET|POST|PATCH /painel/pessoas/{id}/funcoes[/{id}]` e `PUT /painel/pessoas/{id}/tipo-membro`.
 * Funções e tipos de membro só aceitam itens ativos das listas da organização (CA-30).
 */
final class RegistrosController
{
    public function __construct(private readonly ListasDeValores $listas) {}

    public function linhaDoTempo(string $pessoa, LerRegistros $ler): JsonResponse
    {
        return $this->responder($ler->linhaDoTempo($pessoa));
    }

    public function registrar(Request $request, string $pessoa, RegistrarEventoManualAction $acao): JsonResponse
    {
        $dados = $request->validate([
            'ocorrido_em' => ['required', 'date', 'before_or_equal:now'],
            'descricao' => ['required', 'string', 'max:2000'],
        ]);

        return $this->responder($acao->executar($pessoa, new DateTimeImmutable($dados['ocorrido_em']), trim($dados['descricao'])));
    }

    public function relacionamentos(string $pessoa, LerRegistros $ler): JsonResponse
    {
        return $this->responder($ler->relacionamentos($pessoa));
    }

    public function relacionar(Request $request, string $pessoa, RelacionarAction $acao): JsonResponse
    {
        $dados = $request->validate([
            'relacionada' => ['required', 'uuid'],
            'tipo' => ['required', Rule::enum(TipoRelacionamento::class)],
            'descricao' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);
        $descricao = isset($dados['descricao']) && trim($dados['descricao']) !== '' ? trim($dados['descricao']) : null;

        return $this->responder($acao->executar($pessoa, $dados['relacionada'], TipoRelacionamento::from($dados['tipo']), $descricao));
    }

    public function removerRelacionamento(string $pessoa, string $relacionamento, RemoverRelacionamentoAction $acao): JsonResponse
    {
        return $this->responder($acao->executar($pessoa, $relacionamento));
    }

    public function funcoes(string $pessoa, LerRegistros $ler): JsonResponse
    {
        return $this->responder($ler->funcoes($pessoa));
    }

    public function atribuirFuncao(Request $request, string $pessoa, AtribuirFuncaoAction $acao): JsonResponse
    {
        $dados = $request->validate([
            'funcao' => ['required', 'uuid'],
            'desde' => ['required', 'date_format:Y-m-d'],
            'ate' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ]);
        $funcao = $this->listas->idAtivo(ListaValores::Funcao, $dados['funcao'])
            ?? throw ValidationException::withMessages(['funcao' => ['funcao_inexistente_ou_inativa']]);
        $ate = isset($dados['ate']) ? new DateTimeImmutable($dados['ate']) : null;

        return $this->responder($acao->executar($pessoa, $funcao, new DateTimeImmutable($dados['desde']), $ate));
    }

    public function encerrarFuncao(Request $request, string $pessoa, string $funcao, EncerrarFuncaoAction $acao): JsonResponse
    {
        $dados = $request->validate(['ate' => ['required', 'date_format:Y-m-d']]);

        return $this->responder($acao->executar($pessoa, $funcao, new DateTimeImmutable($dados['ate'])));
    }

    public function tipoMembro(Request $request, string $pessoa, DefinirTipoMembroAction $acao): JsonResponse
    {
        $dados = $request->validate(['tipo_membro' => ['present', 'nullable', 'uuid']]);
        $tipo = null;
        if ($dados['tipo_membro'] !== null) {
            $tipo = $this->listas->idAtivo(ListaValores::TipoMembro, $dados['tipo_membro'])
                ?? throw ValidationException::withMessages(['tipo_membro' => ['tipo_membro_inexistente_ou_inativo']]);
        }

        return $this->responder($acao->executar($pessoa, $tipo));
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
        if ($resultado->status === 204) {
            return new JsonResponse(null, 204);
        }

        return new JsonResponse($resultado->dados === [] ? null : $resultado->dados, $resultado->status);
    }
}
