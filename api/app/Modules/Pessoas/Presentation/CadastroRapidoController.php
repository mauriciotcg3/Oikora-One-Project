<?php

namespace App\Modules\Pessoas\Presentation;

use App\Modules\Organizacao\Contracts\CatalogoIgrejas;
use App\Modules\Pessoas\Application\Actions\CadastrarPessoaAction;
use App\Modules\Pessoas\Application\Actions\DadosCadastroRapido;
use App\Modules\Pessoas\Application\Configuracao\ListasDeValores;
use App\Modules\Pessoas\Application\Consultas\CriteriosDuplicidade;
use App\Modules\Pessoas\Application\Consultas\VerificarDuplicidade;
use App\Modules\Pessoas\Domain\Contato;
use App\Modules\Pessoas\Domain\ListaValores;
use App\Modules\Pessoas\Domain\Vinculo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/** `GET /painel/pessoas/duplicidade` (enquanto digita) e `POST /painel/pessoas` (cadastro rápido, T-26). */
final class CadastroRapidoController
{
    public function duplicidade(Request $request, VerificarDuplicidade $verificar, CatalogoIgrejas $igrejas): JsonResponse
    {
        $dados = $request->validate([
            'nome' => ['nullable', 'string', 'max:160'],
            'nascimento' => ['nullable', 'date_format:Y-m-d'],
            'cpf' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'string', 'max:160'],
        ]);

        if (! $verificar->permitido()) {
            return new JsonResponse(['codigo' => 'authz.denied'], Response::HTTP_FORBIDDEN);
        }

        $cpf = isset($dados['cpf']) ? (preg_replace('/\D+/', '', (string) $dados['cpf']) ?? '') : '';
        $achadas = $verificar->executar(new CriteriosDuplicidade(
            nome: isset($dados['nome']) && trim($dados['nome']) !== '' ? Contato::nome($dados['nome']) : null,
            nascimento: $dados['nascimento'] ?? null,
            cpf: strlen($cpf) === 11 ? $cpf : null,
            email: isset($dados['email']) && str_contains($dados['email'], '@') ? Contato::email($dados['email']) : null,
        ));
        $resumo = $igrejas->resumo(array_values(array_unique(array_column($achadas, 'igreja_id'))));

        return new JsonResponse(['data' => array_map(static fn (array $pessoa): array => [
            'uuid' => $pessoa['uuid'],
            'nome' => $pessoa['nome'],
            'igreja' => $resumo[$pessoa['igreja_id']] ?? null,
            'vinculo' => $pessoa['vinculo'],
            'motivos' => $pessoa['motivos'],
            'na_lixeira' => $pessoa['na_lixeira'],
            'meta' => ['acoes' => $pessoa['acoes']],
        ], $achadas)]);
    }

    public function cadastrar(Request $request, CadastrarPessoaAction $acao, CatalogoIgrejas $igrejas, ListasDeValores $listas): JsonResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:160'],
            'igreja' => ['required', 'uuid'],
            'vinculo' => ['required', Rule::enum(Vinculo::class)],
            'celular' => ['nullable', 'required_without:email', 'string', 'max:30'],
            'email' => ['nullable', 'required_without:celular', 'email:rfc', 'max:160'],
            'como_chegou' => ['nullable', 'uuid'],
            'nascimento' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'cpf' => ['nullable', 'string', 'max:20'],
            'nao_duplicadas' => ['sometimes', 'array', 'max:20'],
            'nao_duplicadas.*' => ['uuid'],
        ]);

        $igrejaId = $igrejas->igrejaPorUuid($dados['igreja']);
        $celular = isset($dados['celular']) ? Contato::celular($dados['celular']) : null;
        $comoChegou = isset($dados['como_chegou']) ? $listas->idAtivo(ListaValores::ComoChegou, $dados['como_chegou']) : null;
        $cpf = isset($dados['cpf']) ? (preg_replace('/\D+/', '', (string) $dados['cpf']) ?? '') : null;

        $erros = array_filter([
            'igreja' => $igrejaId === null ? 'Igreja não encontrada.' : null,
            'celular' => isset($dados['celular']) && $celular === null ? 'Celular inválido.' : null,
            'como_chegou' => isset($dados['como_chegou']) && $comoChegou === null ? 'Opção inexistente ou inativa.' : null,
            'cpf' => $cpf !== null && strlen($cpf) !== 11 ? 'CPF deve ter 11 dígitos.' : null,
        ]);
        if ($erros !== [] || $igrejaId === null) {
            throw ValidationException::withMessages(array_map(static fn (string $erro): array => [$erro], $erros));
        }

        $nome = Contato::nome($dados['nome']);
        if ($nome === '') {
            throw ValidationException::withMessages(['nome' => ['Informe o nome.']]);
        }

        $resultado = $acao->executar(new DadosCadastroRapido(
            nome: $nome,
            igrejaId: $igrejaId,
            vinculo: Vinculo::from($dados['vinculo']),
            celular: $celular,
            email: isset($dados['email']) ? Contato::email($dados['email']) : null,
            comoChegouId: $comoChegou,
            nascimento: $dados['nascimento'] ?? null,
            cpf: $cpf === '' ? null : $cpf,
        ), array_values($dados['nao_duplicadas'] ?? []));

        return $resultado->sucesso()
            ? new JsonResponse(['data' => $resultado->dados['pessoa']], $resultado->status)
            : new JsonResponse(['codigo' => $resultado->codigo], $resultado->status);
    }
}
