<?php

namespace App\Modules\Organizacao\Presentation;

use App\Modules\Organizacao\Application\Actions\ConfigurarOrganizacaoAction;
use App\Modules\Organizacao\Application\CadastroDaOrganizacao;
use App\Modules\Organizacao\Application\PedirEnvioDoLogo;
use App\Modules\Organizacao\Application\Resultado;
use App\Modules\Organizacao\Domain\CorPrimaria;
use App\Modules\Organizacao\Domain\Fuso;
use App\Modules\Organizacao\Domain\ModoMatricula;
use App\Shared\Arquivos\ArquivosPrivados;
use App\Shared\Arquivos\UrlDeEnvio;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * `GET|PUT /painel/organizacao` e `POST /painel/organizacao/logo` (plan.md §7; E-04). A leitura serve a
 * todo o painel (tema: nome, cor e logo); o logo sai só por URL assinada de curta duração.
 *
 * @phpstan-import-type DadosOrganizacao from CadastroDaOrganizacao
 */
final class OrganizacaoController
{
    public function __construct(
        private readonly CadastroDaOrganizacao $cadastro,
        private readonly ArquivosPrivados $arquivos,
    ) {}

    public function mostrar(Request $request): JsonResponse
    {
        return new JsonResponse(['data' => $this->organizacao($this->cadastro->dados($this->organizacaoId($request)))]);
    }

    public function atualizar(Request $request, ConfigurarOrganizacaoAction $acao): JsonResponse
    {
        $dados = $request->validate([
            'nome' => ['sometimes', 'string', 'min:2', 'max:120'],
            'fuso' => ['sometimes', 'string', $this->regra(static fn (string $fuso): bool => Fuso::valido($fuso), 'fuso_invalido')],
            'cor_primaria' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/',
                $this->regra(static fn (string $cor): bool => CorPrimaria::temContrasteAA($cor), 'contraste_insuficiente')],
            'modo_matricula' => ['sometimes', Rule::enum(ModoMatricula::class)],
            'limite_whatsapp_mensal' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10000000'],
            'email_contato' => ['sometimes', 'nullable', 'email:rfc', 'max:254'],
            'logo' => ['sometimes', 'nullable', 'string', 'max:255', $this->regra(
                fn (string $caminho): bool => str_starts_with($caminho, PedirEnvioDoLogo::prefixo($this->cadastro->dados($this->organizacaoId($request))['uuid']))
                    && ! str_contains($caminho, '..') && $this->arquivos->existe($caminho),
                'logo_invalido',
            )],
        ]);

        if (isset($dados['cor_primaria'])) {
            $dados['cor_primaria'] = strtoupper($dados['cor_primaria']);
        }
        if (isset($dados['email_contato'])) {
            $dados['email_contato'] = mb_strtolower(trim($dados['email_contato']));
        }
        if (isset($dados['nome'])) {
            $dados['nome'] = trim($dados['nome']);
        }

        return $this->responder($acao->executar($dados), function (array $dadosOk): array {
            /** @var DadosOrganizacao $organizacao */
            $organizacao = $dadosOk['organizacao'];

            return $this->organizacao($organizacao);
        });
    }

    public function logo(Request $request, PedirEnvioDoLogo $pedir): JsonResponse
    {
        $dados = $request->validate([
            'tipo_conteudo' => ['required', Rule::in(array_keys(PedirEnvioDoLogo::TIPOS))],
            'tamanho' => ['required', 'integer', 'min:1', 'max:'.PedirEnvioDoLogo::TAMANHO_MAXIMO],
        ]);

        return $this->responder($pedir->executar($dados['tipo_conteudo']), static function (array $dadosOk): array {
            /** @var UrlDeEnvio $envio */
            $envio = $dadosOk['envio'];

            return [
                'url' => $envio->url,
                'metodo' => 'PUT',
                'cabecalhos' => $envio->cabecalhos,
                'caminho' => $envio->caminho,
                'expira_em' => $envio->expiraEm->format(DATE_ATOM),
            ];
        });
    }

    /**
     * @param  DadosOrganizacao  $organizacao
     * @return array{uuid: string, nome: string, fuso: string, cor_primaria: string|null, modo_matricula: string, limite_whatsapp_mensal: int|null, email_contato: string|null, logo: string|null, logo_url: string|null}
     */
    private function organizacao(array $organizacao): array
    {
        return [
            'uuid' => $organizacao['uuid'],
            'nome' => $organizacao['nome'],
            'fuso' => $organizacao['fuso'],
            'cor_primaria' => $organizacao['cor_primaria'],
            'modo_matricula' => $organizacao['modo_matricula'],
            'limite_whatsapp_mensal' => $organizacao['limite_whatsapp_mensal'],
            'email_contato' => $organizacao['email_contato'],
            'logo' => $organizacao['logo_path'],
            'logo_url' => $organizacao['logo_path'] === null ? null : $this->arquivos->urlDeLeitura($organizacao['logo_path']),
        ];
    }

    /** @param  Closure(array<string, mixed>): array<string, mixed>  $corpo */
    private function responder(Resultado $resultado, Closure $corpo): JsonResponse
    {
        if (! $resultado->sucesso()) {
            return new JsonResponse(['codigo' => $resultado->codigo], $resultado->status);
        }

        return new JsonResponse(['data' => $corpo($resultado->dados)], $resultado->status);
    }

    /** @param  Closure(string): bool  $valido */
    private function regra(Closure $valido, string $mensagem): Closure
    {
        return static function (string $atributo, mixed $valor, Closure $falhar) use ($valido, $mensagem): void {
            if (is_string($valor) && ! $valido($valor)) {
                $falhar($mensagem);
            }
        };
    }

    private function organizacaoId(Request $request): int
    {
        return (int) $request->attributes->get('organizacao_id');
    }
}
