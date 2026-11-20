<?php

namespace App\Modules\Organizacao\Presentation;

use App\Modules\Organizacao\Application\Actions\CriarIgrejaAction;
use App\Modules\Organizacao\Application\Actions\EditarIgrejaAction;
use App\Modules\Organizacao\Application\CadastroDaOrganizacao;
use App\Modules\Organizacao\Application\Resultado;
use App\Modules\Organizacao\Contracts\CatalogoIgrejas;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET|POST /painel/igrejas` e `PUT /painel/igrejas/{id}` (plan.md §7; P FR-001). A lista serve a todo o
 * painel (seletores de igreja); criar e editar pedem `igrejas.gerir` com escopo organização. Não há
 * exclusão de igreja.
 *
 * @phpstan-import-type DadosIgreja from CadastroDaOrganizacao
 */
final class IgrejasController
{
    public function __construct(private readonly CatalogoIgrejas $catalogo) {}

    public function listar(CadastroDaOrganizacao $cadastro): JsonResponse
    {
        return new JsonResponse(['data' => array_map($this->igreja(...), $cadastro->igrejas())]);
    }

    public function criar(Request $request, CriarIgrejaAction $acao): JsonResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'min:2', 'max:120'],
            'regional' => ['sometimes', 'nullable', 'uuid', $this->regionalExiste()],
        ]);

        return $this->responder($acao->executar(trim($dados['nome']), $this->regionalId($dados['regional'] ?? null)));
    }

    public function editar(Request $request, string $igreja, EditarIgrejaAction $acao): JsonResponse
    {
        $dados = $request->validate([
            'nome' => ['sometimes', 'string', 'min:2', 'max:120'],
            'regional' => ['sometimes', 'nullable', 'uuid', $this->regionalExiste()],
        ]);

        $mudancas = [];
        if (isset($dados['nome'])) {
            $mudancas['nome'] = trim($dados['nome']);
        }
        if (array_key_exists('regional', $dados)) {
            $mudancas['regional_id'] = $this->regionalId($dados['regional']);
        }

        return $this->responder($acao->executar($igreja, $mudancas));
    }

    private function responder(Resultado $resultado): JsonResponse
    {
        if (! $resultado->sucesso()) {
            return new JsonResponse(['codigo' => $resultado->codigo], $resultado->status);
        }

        /** @var DadosIgreja $igreja */
        $igreja = $resultado->dados['igreja'];

        return new JsonResponse(['data' => $this->igreja($igreja)], $resultado->status);
    }

    /**
     * @param  DadosIgreja  $igreja
     * @return array{uuid: string, nome: string, regional: array{uuid: string, nome: string}|null}
     */
    private function igreja(array $igreja): array
    {
        $regional = $igreja['regional_id'] === null ? null : ($this->catalogo->resumoRegionais([$igreja['regional_id']])[$igreja['regional_id']] ?? null);

        return ['uuid' => $igreja['uuid'], 'nome' => $igreja['nome'], 'regional' => $regional];
    }

    private function regionalId(?string $uuid): ?int
    {
        return $uuid === null ? null : $this->catalogo->regionalPorUuid($uuid);
    }

    private function regionalExiste(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falhar): void {
            if (is_string($valor) && $this->catalogo->regionalPorUuid($valor) === null) {
                $falhar('regional_inexistente');
            }
        };
    }
}
