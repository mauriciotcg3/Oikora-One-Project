<?php

namespace App\Modules\Pessoas\Presentation;

use App\Modules\Organizacao\Contracts\ConsultaOrganizacoes;
use App\Modules\Pessoas\Application\Actions\EditarFichaAction;
use App\Modules\Pessoas\Application\Configuracao\Resultado;
use App\Modules\Pessoas\Application\Consultas\LerFicha;
use App\Modules\Pessoas\Application\Consultas\PedirEnvioDaFoto;
use App\Modules\Pessoas\Domain\Contato;
use App\Shared\Arquivos\ArquivosPrivados;
use App\Shared\Arquivos\UrlDeEnvio;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * `PATCH /painel/pessoas/{id}` por seção e `POST /painel/pessoas/{id}/foto` (plan.md §7; T-27).
 * Corpo do PATCH: `secao` e os campos dela; a seção `contatos` substitui a lista inteira. Devolve a ficha.
 */
final class FichaController
{
    private const TIPOS_SANGUINEOS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    public function __construct(
        private readonly ConsultaOrganizacoes $organizacoes,
        private readonly ArquivosPrivados $arquivos,
    ) {}

    public function editar(Request $request, string $pessoa, EditarFichaAction $acao, LerFicha $lerFicha): JsonResponse
    {
        $secao = (string) $request->validate([
            'secao' => ['required', Rule::in(array_keys(EditarFichaAction::CATEGORIAS))],
        ])['secao'];

        $dados = $this->normalizar($secao, $request->validate($this->regras($secao, $request, $pessoa)));
        if ($dados === []) {
            throw ValidationException::withMessages(['secao' => ['nenhum_campo_da_secao']]);
        }

        $resultado = $acao->executar($pessoa, $secao, $dados);
        if (! $resultado->sucesso()) {
            return $this->falha($resultado);
        }

        return FichaResposta::de($lerFicha->executar($pessoa));
    }

    public function foto(Request $request, string $pessoa, PedirEnvioDaFoto $pedir): JsonResponse
    {
        $dados = $request->validate([
            'tipo_conteudo' => ['required', Rule::in(array_keys(PedirEnvioDaFoto::TIPOS))],
            'tamanho' => ['required', 'integer', 'min:1', 'max:'.PedirEnvioDaFoto::TAMANHO_MAXIMO],
        ]);

        $resultado = $pedir->executar($pessoa, $dados['tipo_conteudo']);
        if (! $resultado->sucesso()) {
            return $this->falha($resultado);
        }

        /** @var UrlDeEnvio $envio */
        $envio = $resultado->dados['envio'];

        return new JsonResponse(['data' => [
            'url' => $envio->url,
            'metodo' => 'PUT',
            'cabecalhos' => $envio->cabecalhos,
            'caminho' => $envio->caminho,
            'expira_em' => $envio->expiraEm->format(DATE_ATOM),
        ]], 201);
    }

    private function falha(Resultado $resultado): JsonResponse
    {
        if ($resultado->status === 422 && isset($resultado->dados['erros'])) {
            /** @var array<string, string> $erros */
            $erros = $resultado->dados['erros'];

            throw ValidationException::withMessages(array_map(static fn (string $erro): array => [$erro], $erros));
        }

        return new JsonResponse(['codigo' => $resultado->codigo], $resultado->status);
    }

    /** @return array<string, list<mixed>> */
    private function regras(string $secao, Request $request, string $pessoa): array
    {
        $texto = static fn (int $maximo): array => ['sometimes', 'nullable', 'string', 'max:'.$maximo];
        $data = ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'];
        $logico = ['sometimes', 'nullable', 'boolean'];

        return match ($secao) {
            'pessoais' => [
                'nome' => ['sometimes', 'string', 'min:2', 'max:160'],
                'nascimento' => $data,
                'sexo' => $texto(30),
                'estado_civil' => $texto(30),
                'data_casamento' => $data,
                'foto' => ['sometimes', 'nullable', 'string', 'max:255', $this->fotoEnviada($request, $pessoa)],
            ],
            'documentos' => [
                'cpf' => ['sometimes', 'nullable', 'string', 'max:20', $this->cpfValido()],
                'rg' => $texto(30),
                'rg_pais' => $texto(60),
            ],
            'contatos' => [
                'contatos' => ['present', 'array', 'max:10'],
                'contatos.*.tipo' => ['required', Rule::in(['celular', 'telefone', 'email'])],
                'contatos.*.valor' => ['required', 'string', 'max:160'],
                'contatos.*.e_whatsapp' => ['sometimes', 'boolean'],
                'contatos.*.principal' => ['sometimes', 'boolean'],
            ],
            'endereco' => [
                'cep' => $texto(20), 'logradouro' => $texto(160), 'numero' => $texto(30), 'complemento' => $texto(120),
                'bairro' => $texto(120), 'cidade' => $texto(120), 'estado' => $texto(80), 'pais' => $texto(80),
            ],
            'saude' => [
                'necessidades_especiais' => $texto(500),
                'tipo_sanguineo' => ['sometimes', 'nullable', Rule::in(self::TIPOS_SANGUINEOS)],
            ],
            'eclesiasticos' => [
                'aceitou_jesus' => $logico, 'conversao_data' => $data, 'conversao_canal' => $texto(120),
                'batismo_data' => $data, 'batizado_por' => $texto(160), 'igreja_origem' => $texto(160),
                'e_lider' => $logico, 'e_pastor' => $logico, 'novo_convertido' => $logico,
            ],
            default => [
                'escolaridade' => $texto(80), 'profissao' => $texto(120), 'nacionalidade' => $texto(80), 'naturalidade' => $texto(120),
            ],
        };
    }

    /**
     * Forma guardada: texto aparado (vazio vira nulo), CPF só com dígitos, celular em E.164, e-mail minúsculo,
     * um contato principal por tipo e "é WhatsApp" só em celular.
     *
     * @param  array<string, mixed>  $validados
     * @return array<string, mixed>
     */
    private function normalizar(string $secao, array $validados): array
    {
        if ($secao === 'contatos') {
            return ['contatos' => $this->contatos($validados['contatos'] ?? [])];
        }

        $dados = [];
        foreach ($validados as $campo => $valor) {
            $valor = is_string($valor) ? trim($valor) : $valor;
            $dados[$campo === 'foto' ? 'foto_path' : $campo] = match (true) {
                $valor === '' => null,
                $campo === 'cpf' && is_string($valor) => preg_replace('/\D+/', '', $valor),
                $campo === 'nome' && is_string($valor) => Contato::nome($valor),
                in_array($campo, ['aceitou_jesus', 'e_lider', 'e_pastor', 'novo_convertido'], true) && $valor !== null => (bool) $valor,
                default => $valor,
            };
        }

        return $dados;
    }

    /**
     * @param  array<int, array{tipo: string, valor: string, e_whatsapp?: bool, principal?: bool}>  $contatos
     * @return list<array{tipo: string, valor: string, e_whatsapp: bool, principal: bool}>
     */
    private function contatos(array $contatos): array
    {
        $erros = [];
        $normalizados = [];
        $principais = [];
        foreach (array_values($contatos) as $i => $contato) {
            $valor = match ($contato['tipo']) {
                'email' => filter_var(Contato::email($contato['valor']), FILTER_VALIDATE_EMAIL) === false ? null : Contato::email($contato['valor']),
                default => Contato::celular($contato['valor']),
            };
            if ($valor === null) {
                $erros["contatos.{$i}.valor"] = [$contato['tipo'] === 'email' ? 'email_invalido' : 'telefone_invalido'];

                continue;
            }

            $chave = $contato['tipo'].'|'.$valor;
            if (isset($normalizados[$chave])) {
                continue;
            }
            $principal = (bool) ($contato['principal'] ?? false) && ! isset($principais[$contato['tipo']]);
            if ($principal) {
                $principais[$contato['tipo']] = true;
            }
            $normalizados[$chave] = [
                'tipo' => $contato['tipo'],
                'valor' => $valor,
                'e_whatsapp' => $contato['tipo'] === 'celular' && (bool) ($contato['e_whatsapp'] ?? false),
                'principal' => $principal,
            ];
        }

        if ($erros !== []) {
            throw ValidationException::withMessages($erros);
        }

        // Sem principal marcado num tipo, o primeiro dele vira o principal.
        foreach ($normalizados as $chave => $contato) {
            if (! isset($principais[$contato['tipo']])) {
                $normalizados[$chave]['principal'] = true;
                $principais[$contato['tipo']] = true;
            }
        }

        return array_values($normalizados);
    }

    private function cpfValido(): Closure
    {
        return static function (string $atributo, mixed $valor, Closure $falhar): void {
            if (is_string($valor) && trim($valor) !== '' && strlen(preg_replace('/\D+/', '', $valor) ?? '') !== 11) {
                $falhar('cpf_invalido');
            }
        };
    }

    private function fotoEnviada(Request $request, string $pessoa): Closure
    {
        $prefixo = PedirEnvioDaFoto::prefixo($this->organizacoes->uuidDe((int) $request->attributes->get('organizacao_id')), $pessoa);

        return function (string $atributo, mixed $valor, Closure $falhar) use ($prefixo): void {
            if (is_string($valor) && (! str_starts_with($valor, $prefixo) || str_contains($valor, '..') || ! $this->arquivos->existe($valor))) {
                $falhar('foto_invalida');
            }
        };
    }
}
