<?php

use App\Modules\Autorizacao\Application\Alcance;
use App\Modules\Autorizacao\Domain\Permissao;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** @return array{int, int, int, int} */
function cenarioDeAutorizacao(): array
{
    $organizacaoId = criarOrganizacao('Autorização');
    $contaId = criarConta();

    return noContexto($organizacaoId, $contaId, function () use ($organizacaoId, $contaId): array {
        $regionalId = (int) DB::table('regionais')->insertGetId([
            'uuid' => (string) Str::uuid(), 'organizacao_id' => $organizacaoId, 'nome' => 'Regional',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $igrejaId = (int) DB::table('igrejas')->insertGetId([
            'uuid' => (string) Str::uuid(), 'organizacao_id' => $organizacaoId, 'regional_id' => $regionalId,
            'nome' => 'Igreja A', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $outraIgrejaId = (int) DB::table('igrejas')->insertGetId([
            'uuid' => (string) Str::uuid(), 'organizacao_id' => $organizacaoId, 'regional_id' => null,
            'nome' => 'Igreja B', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $pessoaId = (int) DB::table('pessoas')->insertGetId([
            'uuid' => (string) Str::uuid(), 'organizacao_id' => $organizacaoId, 'igreja_id' => $igrejaId,
            'nome' => 'Pessoa A', 'nome_busca' => 'pessoa a', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $acessoId = (int) DB::table('acessos')->insertGetId([
            'uuid' => (string) Str::uuid(), 'organizacao_id' => $organizacaoId,
            'conta_id' => $contaId, 'pessoa_id' => $pessoaId, 'estado' => 'ativo',
            'origem' => 'convite', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$acessoId, $regionalId, $igrejaId, $outraIgrejaId];
    });
}

function criarPapelComPermissao(int $organizacaoId, int $acessoId, Permissao $permissao, string $tipo, ?int $escopoId): int
{
    return noContexto($organizacaoId, null, function () use ($organizacaoId, $acessoId, $permissao, $tipo, $escopoId): int {
        $papelId = (int) DB::table('papeis')->insertGetId([
            'uuid' => (string) Str::uuid(), 'organizacao_id' => $organizacaoId,
            'nome' => 'Papel '.Str::uuid(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('papel_permissoes')->insert([
            'organizacao_id' => $organizacaoId, 'papel_id' => $papelId, 'permissao' => $permissao->value,
        ]);

        return (int) DB::table('atribuicoes_papel')->insertGetId([
            'uuid' => (string) Str::uuid(), 'organizacao_id' => $organizacaoId, 'acesso_id' => $acessoId,
            'papel_id' => $papelId, 'escopo_tipo' => $tipo, 'escopo_id' => $escopoId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    });
}

it('recalcula o alcance após remover uma atribuição, sem cache entre chamadas', function (): void {
    [$acessoId, , $igrejaId] = cenarioDeAutorizacao();
    // O cenário cria a própria organização; use a organização que pertence ao acesso.
    $organizacaoId = (int) DB::connection('pgsql_dono')->table('acessos')->where('id', $acessoId)->value('organizacao_id');
    $atribuicaoId = criarPapelComPermissao($organizacaoId, $acessoId, Permissao::PessoasVer, 'igreja', $igrejaId);

    expect(noContexto($organizacaoId, null, fn (): bool => app(Alcance::class)
        ->permite($organizacaoId, $acessoId, Permissao::PessoasVer, $igrejaId)))->toBeTrue();

    noContexto($organizacaoId, null, fn (): int => DB::table('atribuicoes_papel')->where('id', $atribuicaoId)->delete());

    expect(noContexto($organizacaoId, null, fn (): bool => app(Alcance::class)
        ->permite($organizacaoId, $acessoId, Permissao::PessoasVer, $igrejaId)))->toBeFalse();
});

it('recusa permissão que não pertence ao enum versionado', function (): void {
    [$acessoId] = cenarioDeAutorizacao();
    $organizacaoId = (int) DB::connection('pgsql_dono')->table('acessos')->where('id', $acessoId)->value('organizacao_id');

    noContexto($organizacaoId, null, function () use ($organizacaoId): void {
        $papelId = (int) DB::table('papeis')->insertGetId([
            'uuid' => (string) Str::uuid(), 'organizacao_id' => $organizacaoId,
            'nome' => 'Inválido', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('papel_permissoes')->insert([
            'organizacao_id' => $organizacaoId, 'papel_id' => $papelId, 'permissao' => 'inventada.gerir',
        ]);
    });
})->throws(QueryException::class, 'papel_permissoes_catalogo');
