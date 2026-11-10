<?php

use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Avisos\Infrastructure\Canais\Postmark\PostmarkFalso;
use App\Modules\Identidade\Application\ExigenciaDeDoisFatores;
use App\Modules\Identidade\Contracts\CodigosDeIdentidade;
use App\Modules\Pessoas\Infrastructure\Pessoa;
use App\Shared\Tenancy\Contracts\ResolvedorOrganizacaoAtiva;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Rls', 'Autorizacao', 'LogSeguro');

const ROTA_LOGIN = '/api/v1/auth/login';
const SENHA = 'senha-correta-123';

/** @return list<object> */
function eventos(): array
{
    return DB::connection('pgsql_dono')->table('eventos_autenticacao')->orderBy('id')->get()->all();
}

function entrarCom(string $identificador, string $senha = SENHA): TestResponse
{
    return test()->postJson(ROTA_LOGIN, ['identificador' => $identificador, 'senha' => $senha]);
}

function cookieDaSessao(TestResponse $resposta): string
{
    return $resposta->getCookie(config('session.cookie'), decrypt: false)->getValue();
}

/** Simula uma nova requisição do navegador: só o cookie identifica a pessoa. */
function comSessao(string $cookie): void
{
    Auth::forgetGuards();
    // `auth.driver` guarda a instância antiga do guard, e é por ela que a sessão grava o `user_id`.
    app()->forgetInstance('auth.driver');
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');
    test()->flushHeaders();
    test()->withCredentials();
    test()->withUnencryptedCookie(config('session.cookie'), $cookie);
}

/** Cria a organização pelo `oikora_dono`, fora da RLS, e devolve o id. */
function criarOrganizacao(string $nome = 'Igreja Teste'): int
{
    return (int) DB::connection('pgsql_dono')->table('organizacoes')->insertGetId([
        'uuid' => (string) Str::uuid(),
        'nome' => $nome,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** Cria a conta pelo `oikora_dono`, como faria o aceite de convite (T-22), e devolve o id. */
function criarConta(array $dados = []): int
{
    return (int) DB::connection('pgsql_dono')->table('contas')->insertGetId([
        'uuid' => (string) Str::uuid(),
        'email' => 'maria@exemplo.test',
        'celular' => '+5511987654321',
        'password' => Hash::make('senha-correta-123'),
        'estado' => 'ativa',
        'created_at' => now(),
        'updated_at' => now(),
        ...$dados,
    ]);
}

/** Liga uma conta a uma organização e devolve o id do acesso. */
function criarAcesso(int $organizacaoId, int $contaId, int $pessoaId, string $estado = 'ativo'): int
{
    return (int) DB::connection('pgsql_dono')->table('acessos')->insertGetId([
        'uuid' => (string) Str::uuid(),
        'organizacao_id' => $organizacaoId,
        'conta_id' => $contaId,
        'pessoa_id' => $pessoaId,
        'estado' => $estado,
        'origem' => 'convite',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** Roda `$acao` numa transação do `oikora_app` com o contexto gravado (OIK-ADR-0011 §3). */
function noContexto(?int $organizacaoId, ?int $contaId, Closure $acao): mixed
{
    return DB::transaction(function () use ($organizacaoId, $contaId, $acao): mixed {
        if ($organizacaoId !== null) {
            DB::select("select set_config('app.organizacao_id', ?, true)", [(string) $organizacaoId]);
        }
        if ($contaId !== null) {
            DB::select("select set_config('app.conta_id', ?, true)", [(string) $contaId]);
        }

        return $acao();
    });
}

/** Simula outro navegador: sem cookie, nada da sessão anterior é herdado. */
function novoNavegador(): void
{
    comSessao('');
}

/** Emite um código pelo Contract público de Identidade e devolve o id em `codigos_verificacao`. */
function emitirCodigo(int $contaId, string $finalidade, string $canal): int
{
    return app(CodigosDeIdentidade::class)->emitir($contaId, $finalidade, $canal);
}

function criarContaVerificada(array $dados = []): int
{
    return criarConta(['email_verificado_em' => now(), 'celular_verificado_em' => now(), ...$dados]);
}

/** Entra na conta já criada, sem 2FA, e deixa o navegador com a sessão dela. */
function logarNaConta(): string
{
    $cookie = cookieDaSessao(entrarCom('maria@exemplo.test')->assertNoContent());
    comSessao($cookie);

    return $cookie;
}

function logado(array $dados = []): string
{
    criarContaVerificada($dados);

    return logarNaConta();
}

function confirmarSenhaAtual(string $cookie): void
{
    comSessao($cookie);
    test()->postJson('/api/v1/conta/confirmar-senha', ['senha' => SENHA])->assertNoContent();
    comSessao($cookie);
}

/** Ativa o 2FA por e-mail e devolve os códigos de recuperação. */
function ativarDoisFatores(string $cookie): array
{
    confirmarSenhaAtual($cookie);

    return test()->postJson('/api/v1/conta/2fa', ['canal' => 'email'])->assertOk()->json('codigos_recuperacao');
}

/** Até a T-16 ligar o papel Administrador, a obrigação vem desta porta. */
function exigirDoisFatores(): void
{
    app()->instance(ExigenciaDeDoisFatores::class, new class implements ExigenciaDeDoisFatores
    {
        public function obrigatorio(int $contaId): bool
        {
            return true;
        }
    });
}

/** Redefine a senha por e-mail há `$horas` e deixa um navegador novo pronto para entrar. */
function entrarDepoisDeRecuperar(int $horas): void
{
    test()->travel(-$horas)->hours();
    test()->postJson('/api/v1/auth/senha/esqueci', ['identificador' => 'maria@exemplo.test'])->assertNoContent();
    $token = collect(app(PostmarkFalso::class)->enviados)->last()->codigo;
    test()->postJson('/api/v1/auth/senha/redefinir', ['token' => $token, 'senha' => 'outra-senha-forte-456'])->assertNoContent();
    test()->travelBack();
    novoNavegador();
}

/** Desvia o canal `json` para um arquivo temporário, para o teste ler as linhas reais do log. */
function capturarLog(): string
{
    $arquivo = tempnam(sys_get_temp_dir(), 'oik-log-');
    config(['logging.default' => 'json', 'logging.channels.json.handler_with.stream' => $arquivo]);
    app('log')->forgetChannel('json');
    test()->arquivoDeLog = $arquivo;

    return $arquivo;
}

function conteudoDoLog(): string
{
    return (string) file_get_contents(test()->arquivoDeLog);
}

/** @return list<array<string, mixed>> */
function linhasDoLog(): array
{
    return collect(explode("\n", trim(conteudoDoLog())))
        ->filter()
        ->map(fn (string $linha): array => json_decode($linha, true, flags: JSON_THROW_ON_ERROR))
        ->values()
        ->all();
}

/** Até a T-16, a organização ativa do painel vem desta porta. */
function usarOrganizacao(int $organizacaoId): void
{
    app()->instance(ResolvedorOrganizacaoAtiva::class, new class($organizacaoId) implements ResolvedorOrganizacaoAtiva
    {
        public function __construct(private readonly int $organizacaoId) {}

        public function resolver(Request $request): int
        {
            return $this->organizacaoId;
        }
    });
}

/** Insere pelo `oikora_dono` (fora da RLS) e devolve o id. */
function inserirComoDono(string $tabela, array $dados): int
{
    return (int) DB::connection('pgsql_dono')->table($tabela)->insertGetId([
        'uuid' => (string) Str::uuid(),
        'created_at' => now(),
        'updated_at' => now(),
        ...$dados,
    ]);
}

function uuidDe(string $tabela, int $id): string
{
    return (string) DB::connection('pgsql_dono')->table($tabela)->where('id', $id)->value('uuid');
}

function criarRegional(int $organizacaoId, string $nome = 'Regional'): int
{
    return inserirComoDono('regionais', ['organizacao_id' => $organizacaoId, 'nome' => $nome]);
}

function criarIgreja(int $organizacaoId, string $nome = 'Igreja', ?int $regionalId = null): int
{
    return inserirComoDono('igrejas', ['organizacao_id' => $organizacaoId, 'regional_id' => $regionalId, 'nome' => $nome]);
}

function criarPessoa(int $organizacaoId, int $igrejaId, array $dados = []): int
{
    $nome = $dados['nome'] ?? 'Pessoa '.Str::random(6);

    return inserirComoDono('pessoas', [
        'organizacao_id' => $organizacaoId,
        'igreja_id' => $igrejaId,
        'nome' => $nome,
        'nome_busca' => Str::lower(Str::ascii($nome)),
        ...$dados,
    ]);
}

/** @param  list<Permissao|string>  $permissoes */
function criarPapel(int $organizacaoId, string $nome, array $permissoes, ?string $sistema = null): int
{
    $papelId = inserirComoDono('papeis', ['organizacao_id' => $organizacaoId, 'nome' => $nome, 'sistema' => $sistema]);
    $valores = array_map(fn ($p): string => $p instanceof Permissao ? $p->value : $p, $permissoes);
    DB::connection('pgsql_dono')->table('papel_permissoes')->insert(array_map(fn (string $p): array => [
        'organizacao_id' => $organizacaoId, 'papel_id' => $papelId, 'permissao' => $p,
    ], array_values(array_unique($valores))));

    return $papelId;
}

function atribuirPapel(int $organizacaoId, int $acessoId, int $papelId, string $tipo = 'organizacao', ?int $escopoId = null): int
{
    return inserirComoDono('atribuicoes_papel', [
        'organizacao_id' => $organizacaoId, 'acesso_id' => $acessoId, 'papel_id' => $papelId,
        'escopo_tipo' => $tipo, 'escopo_id' => $escopoId,
    ]);
}

/** @return list<string> */
function todasAsPermissoes(): array
{
    return array_map(fn (Permissao $p): string => $p->value, Permissao::cases());
}

/**
 * Cria conta, pessoa, acesso e papel com as permissões no escopo dado, entra e deixa a sessão pronta.
 *
 * @param  list<Permissao|string>  $permissoes
 * @return array{conta: int, acesso: int, pessoa: int, papel: int, atribuicao: int, cookie: string}
 */
function entrarNoPainel(int $organizacaoId, int $igrejaId, array $permissoes, string $tipo = 'organizacao', ?int $escopoId = null, ?string $sistema = null): array
{
    static $sequencia = 0;
    $sequencia++;
    $email = "ator{$sequencia}@exemplo.test";
    $contaId = criarContaVerificada(['email' => $email, 'celular' => '+55119'.str_pad((string) (80000000 + $sequencia), 8, '0', STR_PAD_LEFT)]);
    $pessoaId = criarPessoa($organizacaoId, $igrejaId, ['nome' => "Ator {$sequencia}"]);
    $acessoId = criarAcesso($organizacaoId, $contaId, $pessoaId);
    $papelId = criarPapel($organizacaoId, $sistema === null ? "Papel do ator {$sequencia}" : ucfirst($sistema), $permissoes, $sistema);
    $atribuicaoId = atribuirPapel($organizacaoId, $acessoId, $papelId, $tipo, $escopoId);

    usarOrganizacao($organizacaoId);
    novoNavegador();
    $cookie = cookieDaSessao(entrarCom($email)->assertNoContent());
    comSessao($cookie);

    return ['conta' => $contaId, 'acesso' => $acessoId, 'pessoa' => $pessoaId, 'papel' => $papelId, 'atribuicao' => $atribuicaoId, 'cookie' => $cookie];
}

/** @return list<object> eventos de auditoria, com `metadados` decodificado */
function auditoria(string $evento): array
{
    return DB::connection('pgsql_dono')->table('auditoria')->where('evento', $evento)->orderBy('id')->get()
        ->map(function (object $linha): object {
            $linha->metadados = json_decode($linha->metadados, true);

            return $linha;
        })->all();
}

/** Cria a Pessoa pelo model (cifra e `cpf_hash` de verdade), como rotina de sistema, e devolve o id. */
function criarPessoaComFicha(int $organizacaoId, int $igrejaId, array $dados = []): int
{
    return noContexto($organizacaoId, null, fn (): int => app(AlcanceAtual::class)
        ->comoSistema($organizacaoId, fn (): int => (int) Pessoa::query()->create([
            'uuid' => (string) Str::uuid(),
            'organizacao_id' => $organizacaoId,
            'igreja_id' => $igrejaId,
            'nome' => 'Pessoa '.Str::random(6),
            ...$dados,
        ])->getKey()));
}

function criarContato(int $organizacaoId, int $pessoaId, string $tipo, string $valor): int
{
    return inserirComoDono('pessoa_contatos', [
        'organizacao_id' => $organizacaoId, 'pessoa_id' => $pessoaId, 'tipo' => $tipo, 'valor' => $valor,
    ]);
}

/** Captura o SQL executado por `$acao`. @return list<string> */
function sqlDe(Closure $acao): array
{
    $consultas = [];
    DB::listen(function (QueryExecuted $consulta) use (&$consultas): void {
        $consultas[] = $consulta->sql;
    });
    $acao();

    return $consultas;
}
