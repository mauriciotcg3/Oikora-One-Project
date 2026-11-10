<?php

namespace App\Modules\Autorizacao\Presentation;

use App\Modules\Autorizacao\Application\Actions\AtribuirPapelAction;
use App\Modules\Autorizacao\Application\Actions\CriarPapelAction;
use App\Modules\Autorizacao\Application\Actions\EditarPapelAction;
use App\Modules\Autorizacao\Application\Actions\RemoverAtribuicaoAction;
use App\Modules\Autorizacao\Application\Actions\Resultado;
use App\Modules\Autorizacao\Application\AlcanceDaRequisicao;
use App\Modules\Autorizacao\Application\Papeis;
use App\Modules\Autorizacao\Domain\EscopoTipo;
use App\Modules\Autorizacao\Domain\Permissao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/** `/painel/permissoes`, `/painel/papeis` e `/painel/acessos/{id}/atribuicoes` (plan.md §7). */
final class PapeisController
{
    public function permissoes(AlcanceDaRequisicao $alcance): JsonResponse
    {
        if (! $alcance->temNaOrganizacao(Permissao::PapeisGerir)) {
            return new JsonResponse(['codigo' => 'authz.denied'], Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse(['data' => array_map(
            static fn (Permissao $permissao): string => $permissao->value,
            Permissao::cases(),
        )]);
    }

    public function listar(AlcanceDaRequisicao $alcance, Papeis $papeis): JsonResponse
    {
        if (! $alcance->temNaOrganizacao(Permissao::PapeisGerir)) {
            return new JsonResponse(['codigo' => 'authz.denied'], Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse(['data' => array_map($this->papel(...), $papeis->listar())]);
    }

    public function criar(Request $request, CriarPapelAction $acao): JsonResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:80'],
            ...$this->regrasDePermissoes(),
        ]);

        return $this->responder($acao->executar(trim($dados['nome']), array_values(array_unique($dados['permissoes']))));
    }

    public function editar(Request $request, string $papel, EditarPapelAction $acao): JsonResponse
    {
        $dados = $request->validate([
            'nome' => ['sometimes', 'string', 'max:80'],
            ...$this->regrasDePermissoes(),
        ]);

        return $this->responder($acao->executar(
            $papel,
            isset($dados['nome']) ? trim($dados['nome']) : null,
            array_values(array_unique($dados['permissoes'])),
        ));
    }

    public function atribuir(Request $request, string $acesso, AtribuirPapelAction $acao): JsonResponse
    {
        $dados = $request->validate([
            'papel' => ['required', 'uuid'],
            'escopo_tipo' => ['required', Rule::enum(EscopoTipo::class)],
            'escopo' => ['nullable', 'uuid', 'required_unless:escopo_tipo,organizacao', 'prohibited_if:escopo_tipo,organizacao'],
        ]);

        return $this->responder($acao->executar($acesso, $dados['papel'], EscopoTipo::from($dados['escopo_tipo']), $dados['escopo'] ?? null));
    }

    public function removerAtribuicao(string $acesso, string $atribuicao, RemoverAtribuicaoAction $acao): JsonResponse
    {
        return $this->responder($acao->executar($acesso, $atribuicao));
    }

    /** @return array<string, list<mixed>> */
    private function regrasDePermissoes(): array
    {
        return [
            'permissoes' => ['required', 'array', 'min:1'],
            'permissoes.*' => ['required', 'string', 'distinct', Rule::enum(Permissao::class)],
        ];
    }

    private function responder(Resultado $resultado): JsonResponse
    {
        if (! $resultado->sucesso()) {
            return new JsonResponse(['codigo' => $resultado->codigo], $resultado->status);
        }

        if ($resultado->status === Response::HTTP_NO_CONTENT) {
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        $dados = $resultado->dados;
        if (isset($dados['papel']) && is_array($dados['papel'])) {
            /** @var array{uuid: string, nome: string, sistema: string|null, permissoes: list<string>} $papel */
            $papel = $dados['papel'];

            return new JsonResponse(['data' => $this->papel($papel)], $resultado->status);
        }

        return new JsonResponse(['data' => $dados['atribuicao'] ?? $dados], $resultado->status);
    }

    /**
     * @param  array{uuid: string, nome: string, sistema: string|null, permissoes: list<string>}  $papel
     * @return array<string, mixed>
     */
    private function papel(array $papel): array
    {
        return [
            'uuid' => $papel['uuid'],
            'nome' => $papel['nome'],
            'sistema' => $papel['sistema'],
            'permissoes' => $papel['permissoes'],
            'meta' => ['acoes' => ['editar']],
        ];
    }
}
