<?php

namespace App\Modules\Pessoas\Presentation\Configuracao;

use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Pessoas\Application\Configuracao\CriarItemListaAction;
use App\Modules\Pessoas\Application\Configuracao\EditarItemListaAction;
use App\Modules\Pessoas\Application\Configuracao\ListasDeValores;
use App\Modules\Pessoas\Application\Configuracao\Resultado;
use App\Modules\Pessoas\Domain\ListaValores;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `GET|POST /painel/listas/{lista}` e `PUT /painel/listas/{lista}/{id}` (plan.md §7; CA-30).
 * A manutenção exige `pessoas.configurar`. A leitura também serve aos formulários de cadastro: quem cria
 * ou edita pessoas em alguma igreja vê só os itens ativos.
 *
 * @phpstan-import-type ItemLista from ListasDeValores
 */
final class ListasController
{
    public function listar(string $lista, AlcanceAtual $alcance, ListasDeValores $listas): JsonResponse
    {
        $configura = $alcance->temNaOrganizacao(Permissao::PessoasConfigurar);
        $cadastra = $alcance->igrejasCom(Permissao::PessoasCriar) !== [] || $alcance->igrejasCom(Permissao::PessoasEditar) !== [];
        if (! $configura && ! $cadastra) {
            return new JsonResponse(['codigo' => 'authz.denied'], Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse(['data' => array_map(
            $this->item(...),
            $listas->listar(ListaValores::from($lista), soAtivos: ! $configura),
        )]);
    }

    public function criar(Request $request, string $lista, CriarItemListaAction $acao): JsonResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:80'],
            'ordem' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ]);

        return $this->responder($acao->executar(
            ListaValores::from($lista),
            trim($dados['nome']),
            isset($dados['ordem']) ? (int) $dados['ordem'] : null,
        ));
    }

    public function editar(Request $request, string $lista, string $item, EditarItemListaAction $acao): JsonResponse
    {
        $dados = $request->validate([
            'nome' => ['sometimes', 'string', 'max:80'],
            'ordem' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'ativo' => ['sometimes', 'boolean'],
        ]);

        $mudancas = [];
        if (isset($dados['nome'])) {
            $mudancas['nome'] = trim($dados['nome']);
        }
        if (isset($dados['ordem'])) {
            $mudancas['ordem'] = (int) $dados['ordem'];
        }
        if (isset($dados['ativo'])) {
            $mudancas['ativo'] = (bool) $dados['ativo'];
        }

        return $this->responder($acao->executar(ListaValores::from($lista), $item, $mudancas));
    }

    private function responder(Resultado $resultado): JsonResponse
    {
        if (! $resultado->sucesso()) {
            return new JsonResponse(['codigo' => $resultado->codigo], $resultado->status);
        }

        /** @var ItemLista $item */
        $item = $resultado->dados['item'];

        return new JsonResponse(['data' => $this->item($item)], $resultado->status);
    }

    /**
     * @param  ItemLista  $item
     * @return array{uuid: string, lista: string, nome: string, ativo: bool, ordem: int}
     */
    private function item(array $item): array
    {
        return [
            'uuid' => $item['uuid'],
            'lista' => $item['lista'],
            'nome' => $item['nome'],
            'ativo' => $item['ativo'],
            'ordem' => $item['ordem'],
        ];
    }
}
