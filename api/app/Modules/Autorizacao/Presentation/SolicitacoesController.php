<?php

namespace App\Modules\Autorizacao\Presentation;

use App\Modules\Autorizacao\Application\Actions\Resultado;
use App\Modules\Autorizacao\Application\Solicitacoes\AprovarSolicitacaoAction;
use App\Modules\Autorizacao\Application\Solicitacoes\CancelarSolicitacaoAction;
use App\Modules\Autorizacao\Application\Solicitacoes\Decisoes;
use App\Modules\Autorizacao\Application\Solicitacoes\RecusarSolicitacaoAction;
use App\Modules\Autorizacao\Application\Solicitacoes\RevogarConcessaoAction;
use App\Modules\Autorizacao\Application\Solicitacoes\SolicitacoesDePermissao;
use App\Modules\Autorizacao\Application\Solicitacoes\SolicitarPermissaoAction;
use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\EscopoTipo;
use App\Modules\Autorizacao\Domain\ItemSolicitado;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Organizacao\Contracts\CatalogoIgrejas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/** Solicitações e concessões temporárias de permissão (`contrato-permissoes.md`; OIK-ADR-0012). */
final class SolicitacoesController
{
    public function __construct(
        private readonly SolicitacoesDePermissao $solicitacoes,
        private readonly CatalogoIgrejas $igrejas,
    ) {}

    public function listar(Request $request, AlcanceAtual $alcance, Decisoes $decisoes): JsonResponse
    {
        $visao = $request->validate(['visao' => ['sometimes', Rule::in(['proprias', 'aprovaveis'])]])['visao'] ?? 'proprias';

        if ($visao === 'aprovaveis' && ($negada = $decisoes->decisorNegado())) {
            return $this->falha($negada);
        }

        return new JsonResponse(['data' => array_map(
            fn (array $solicitacao): array => $this->apresentar($solicitacao),
            $this->solicitacoes->listar($alcance->ator()->acessoId, $visao),
        )]);
    }

    public function criar(Request $request, SolicitarPermissaoAction $acao): JsonResponse
    {
        $dados = $request->validate([
            'permissoes' => ['required', 'array', 'min:1', 'max:20'],
            'permissoes.*.chave' => ['required', 'string', Rule::enum(Permissao::class)],
            'permissoes.*.categoria_sensivel' => ['nullable', 'string', 'max:20'],
            'permissoes.*.escopo_tipo' => ['required', Rule::enum(EscopoTipo::class)],
            'permissoes.*.escopo_id' => ['nullable', 'uuid'],
            'duracao_horas' => ['required', 'integer'],
            'justificativa' => ['required', 'string', 'max:1000'],
        ]);

        $resultado = $acao->executar($this->itens($dados['permissoes']), (int) $dados['duracao_horas'], trim($dados['justificativa']));
        if (! $resultado->sucesso()) {
            return $this->falha($resultado);
        }

        $uuid = (string) $resultado->dados['uuid'];

        return new JsonResponse(['data' => $this->apresentar($this->solicitacoes->detalhar($uuid) ?? [])], Response::HTTP_CREATED);
    }

    public function cancelar(string $solicitacao, CancelarSolicitacaoAction $acao): JsonResponse
    {
        return $this->responder($acao->executar($solicitacao), $solicitacao);
    }

    public function aprovar(Request $request, string $solicitacao, AprovarSolicitacaoAction $acao): JsonResponse
    {
        $versao = $request->validate(['versao' => ['sometimes', 'integer', 'min:1']])['versao'] ?? null;

        return $this->responder($acao->executar($solicitacao, $versao === null ? null : (int) $versao), $solicitacao);
    }

    public function recusar(Request $request, string $solicitacao, RecusarSolicitacaoAction $acao): JsonResponse
    {
        $dados = $request->validate([
            'justificativa' => ['required', 'string', 'max:1000'],
            'versao' => ['sometimes', 'integer', 'min:1'],
        ]);

        return $this->responder($acao->executar($solicitacao, trim($dados['justificativa']), isset($dados['versao']) ? (int) $dados['versao'] : null), $solicitacao);
    }

    public function revogar(Request $request, string $concessao, RevogarConcessaoAction $acao): JsonResponse
    {
        $motivo = $request->validate(['motivo' => ['nullable', 'string', 'max:1000']])['motivo'] ?? null;
        $resultado = $acao->executar($concessao, $motivo === null ? null : trim($motivo));

        return $resultado->sucesso() ? new JsonResponse(null, Response::HTTP_NO_CONTENT) : $this->falha($resultado);
    }

    /**
     * @param  list<array{chave: string, categoria_sensivel?: string|null, escopo_tipo: string, escopo_id?: string|null}>  $pedidas
     * @return list<ItemSolicitado>
     */
    private function itens(array $pedidas): array
    {
        $itens = [];
        $erros = [];

        foreach ($pedidas as $indice => $pedida) {
            $permissao = Permissao::from($pedida['chave']);
            $tipo = EscopoTipo::from($pedida['escopo_tipo']);
            $categoria = $permissao->categoriaSensivel();

            // Sensível só com a categoria explícita, igual à da permissão (OIK-ADR-0012 §5).
            if ($categoria !== null && ($pedida['categoria_sensivel'] ?? null) !== $categoria) {
                $erros["permissoes.{$indice}.categoria_sensivel"] = ['Informe a categoria sensível desta permissão.'];
            }

            $escopoId = match ($tipo) {
                EscopoTipo::Organizacao => null,
                EscopoTipo::Regional => isset($pedida['escopo_id']) ? $this->igrejas->regionalPorUuid($pedida['escopo_id']) : null,
                EscopoTipo::Igreja => isset($pedida['escopo_id']) ? $this->igrejas->igrejaPorUuid($pedida['escopo_id']) : null,
            };
            if ($tipo !== EscopoTipo::Organizacao && $escopoId === null) {
                $erros["permissoes.{$indice}.escopo_id"] = ['Escopo não encontrado.'];
            }

            $itens[] = new ItemSolicitado($permissao, $tipo, $escopoId);
        }

        if ($erros !== []) {
            throw ValidationException::withMessages($erros);
        }

        return $itens;
    }

    /**
     * @param  array<string, mixed>  $solicitacao
     * @return array<string, mixed>
     */
    private function apresentar(array $solicitacao): array
    {
        /** @var list<ItemSolicitado> $itens */
        $itens = $solicitacao['itens'] ?? [];
        $igrejas = $this->igrejas->resumo(array_values(array_filter(array_map(
            static fn (ItemSolicitado $i): ?int => $i->escopoTipo === EscopoTipo::Igreja ? $i->escopoId : null,
            $itens,
        ))));
        $regionais = $this->igrejas->resumoRegionais(array_values(array_filter(array_map(
            static fn (ItemSolicitado $i): ?int => $i->escopoTipo === EscopoTipo::Regional ? $i->escopoId : null,
            $itens,
        ))));

        unset($solicitacao['itens']);

        return [...$solicitacao, 'permissoes' => array_map(static fn (ItemSolicitado $i): array => [
            'chave' => $i->permissao->value,
            'categoria_sensivel' => $i->permissao->categoriaSensivel(),
            'escopo_tipo' => $i->escopoTipo->value,
            'escopo_id' => $i->escopoId === null ? null : ($i->escopoTipo === EscopoTipo::Igreja ? $igrejas : $regionais)[$i->escopoId]['uuid'] ?? null,
        ], $itens)];
    }

    private function responder(Resultado $resultado, string $solicitacao): JsonResponse
    {
        if (! $resultado->sucesso()) {
            return $this->falha($resultado);
        }

        return new JsonResponse(['data' => $this->apresentar($this->solicitacoes->detalhar($solicitacao) ?? [])]);
    }

    private function falha(Resultado $resultado): JsonResponse
    {
        return new JsonResponse(['codigo' => $resultado->codigo], $resultado->status);
    }
}
