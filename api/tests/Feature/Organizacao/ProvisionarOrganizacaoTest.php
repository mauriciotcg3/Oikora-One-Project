<?php

use App\Modules\Autorizacao\Application\CriarPapeisProntos;
use App\Modules\Autorizacao\Domain\Permissao;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/** Provisionamento da organização (plan.md §3.3, §4; RN-17; T-23). */
function provisionar(array $opcoes = []): int
{
    return test()->artisan('oikora:organizacao:provisionar', [
        'identificador' => 'igreja-central',
        '--nome' => 'Igreja Central',
        '--igreja' => 'Sede',
        '--admin-nome' => 'Ana Administradora',
        '--admin-email' => 'ana@exemplo.test',
        '--admin-celular' => '+5511912345678',
        '--operador' => 'operador@oikora.test',
        '--motivo' => 'novo_cliente',
        ...$opcoes,
    ])->run();
}

function dono(): Connection
{
    return DB::connection('pgsql_dono');
}

/** @return list<string> */
function permissoesDoPapel(int $papelId): array
{
    return dono()->table('papel_permissoes')->where('papel_id', $papelId)->orderBy('permissao')->pluck('permissao')->all();
}

it('cria a organização ativa com papéis prontos, a igreja e a Pessoa do administrador', function (): void {
    expect(provisionar())->toBe(0);

    $organizacao = dono()->table('organizacoes')->where('identificador', 'igreja-central')->first();
    expect($organizacao->nome)->toBe('Igreja Central')
        ->and($organizacao->estado)->toBe('ativa');

    $papeis = dono()->table('papeis')->where('organizacao_id', $organizacao->id)->get()->keyBy('sistema');
    $todas = array_map(fn (Permissao $p): string => $p->value, Permissao::cases());
    sort($todas);
    $daSecretaria = array_map(fn (Permissao $p): string => $p->value, CriarPapeisProntos::daSecretaria());
    sort($daSecretaria);

    expect($papeis->keys()->sort()->values()->all())->toBe(['administrador', 'secretaria'])
        ->and(permissoesDoPapel((int) $papeis['administrador']->id))->toBe($todas)
        ->and(permissoesDoPapel((int) $papeis['secretaria']->id))->toBe($daSecretaria)
        ->and($daSecretaria)->not->toContain('pessoas.configurar')
        ->and($daSecretaria)->not->toContain('pessoas.sensiveis.saude.ver')
        ->and($daSecretaria)->toContain('pessoas.sensiveis.fe.editar');

    expect(dono()->table('igrejas')->where('organizacao_id', $organizacao->id)->pluck('nome')->all())->toBe(['Sede']);

    $pessoa = dono()->table('pessoas')->where('organizacao_id', $organizacao->id)->first();
    expect($pessoa->nome)->toBe('Ana Administradora')
        ->and(dono()->table('pessoa_contatos')->where('pessoa_id', $pessoa->id)->orderBy('tipo')->pluck('valor', 'tipo')->all())
        ->toBe(['celular' => '+5511912345678', 'email' => 'ana@exemplo.test']);
});

it('convida o administrador com papel Administrador e escopo organização, e registra o aviso', function (): void {
    provisionar();

    $organizacaoId = (int) dono()->table('organizacoes')->where('identificador', 'igreja-central')->value('id');
    $convite = dono()->table('convites')->where('organizacao_id', $organizacaoId)->first();
    $papel = dono()->table('papeis')->where('id', $convite->papel_id)->first();

    expect($convite->estado)->toBe('enviado')
        ->and($convite->escopo_tipo)->toBe('organizacao')
        ->and($convite->escopo_id)->toBeNull()
        ->and($convite->emitido_por_acesso_id)->toBeNull()
        ->and($papel->sistema)->toBe('administrador');

    $aviso = dono()->table('avisos')->where('organizacao_id', $organizacaoId)->first();
    expect($aviso->tipo)->toBe('convite.painel')
        ->and($aviso->pessoa_id)->toBe($convite->pessoa_id)
        ->and(dono()->table('envios')->where('organizacao_id', $organizacaoId)->count())->toBeGreaterThan(0);
});

it('com --em-migracao a organização nasce em migração e o envio do convite é suprimido', function (): void {
    provisionar(['--em-migracao' => true]);

    $organizacao = dono()->table('organizacoes')->where('identificador', 'igreja-central')->first();
    expect($organizacao->estado)->toBe('em_migracao')
        ->and(dono()->table('avisos')->where('organizacao_id', $organizacao->id)->count())->toBe(1)
        ->and(dono()->table('envios')->where('organizacao_id', $organizacao->id)->count())->toBe(0);
});

it('registra o comando, o operador e o motivo em auditoria_plataforma', function (): void {
    provisionar();

    $uuid = dono()->table('organizacoes')->where('identificador', 'igreja-central')->value('uuid');
    $linha = dono()->table('auditoria_plataforma')->sole();

    expect($linha->comando)->toBe('oikora:organizacao:provisionar')
        ->and($linha->operador)->toBe('operador@oikora.test')
        ->and($linha->motivo_codigo)->toBe('novo_cliente')
        ->and(json_decode($linha->organizacoes, true))->toBe([$uuid]);
});

it('rodar de novo com o mesmo identificador não duplica a organização', function (): void {
    provisionar();
    expect(provisionar(['--nome' => 'Outro Nome']))->toBe(0);

    expect(dono()->table('organizacoes')->where('identificador', 'igreja-central')->count())->toBe(1)
        ->and(dono()->table('organizacoes')->where('identificador', 'igreja-central')->value('nome'))->toBe('Igreja Central')
        ->and(dono()->table('convites')->count())->toBe(1)
        ->and(dono()->table('auditoria_plataforma')->count())->toBe(1);
});

it('recusa identificador inválido e administrador sem contato', function (): void {
    expect(provisionar(['identificador' => 'Igreja Central']))->not->toBe(0)
        ->and(provisionar(['--admin-email' => null, '--admin-celular' => null]))->not->toBe(0)
        ->and(dono()->table('organizacoes')->count())->toBe(0);
});
