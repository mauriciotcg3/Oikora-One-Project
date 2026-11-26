<?php

use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Pessoas\Application\Jobs\ExpirarDesfazerMesclagem;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->org = criarOrganizacao('Desfazer mesclagem');
    $this->igreja = criarIgreja($this->org, 'Igreja Central');
    entrarNoPainel($this->org, $this->igreja, [
        Permissao::PessoasVer,
        Permissao::PessoasMesclar,
        Permissao::DocumentosVer,
        Permissao::DocumentosEditar,
    ]);
});

function criarMesclagemParaDesfazer(object $teste): array
{
    $mantida = criarPessoaComFicha($teste->org, $teste->igreja, ['nome' => 'Nome mantido', 'profissao' => 'Antes']);
    $absorvida = criarPessoaComFicha($teste->org, $teste->igreja, [
        'nome' => 'Nome absorvido',
        'profissao' => 'Outra',
        'cpf' => '529.982.247-25',
        'matricula' => 'M-33',
    ]);
    $contato = criarContato($teste->org, $absorvida, 'email', 'absorvida@example.test');
    $operacao = $teste->postJson('/api/v1/painel/mesclagens', [
        'mantida' => uuidDe('pessoas', $mantida),
        'absorvida' => uuidDe('pessoas', $absorvida),
        'valores' => ['nome' => 'absorvida', 'cpf' => 'absorvida'],
    ])->assertCreated()->json('operacao');

    return compact('mantida', 'absorvida', 'contato', 'operacao');
}

it('restaura as duas fichas e os filhos ao desfazer', function (): void {
    $dados = criarMesclagemParaDesfazer($this);

    $this->postJson("/api/v1/painel/mesclagens/{$dados['operacao']}/desfazer")
        ->assertOk()->assertJson(['estado' => 'desfeita', 'conflitos' => []]);

    $dono = DB::connection('pgsql_dono');
    expect($dono->table('pessoas')->find($dados['mantida'])->nome)->toBe('Nome mantido')
        ->and($dono->table('pessoas')->find($dados['absorvida'])->nome)->toBe('Nome absorvido')
        ->and($dono->table('pessoas')->find($dados['absorvida'])->cpf_hash)->not->toBeNull()
        ->and($dono->table('pessoas')->find($dados['absorvida'])->matricula)->toBe('M-33')
        ->and($dono->table('pessoa_contatos')->find($dados['contato'])->pessoa_id)->toBe($dados['absorvida']);
});

it('preserva campo alterado depois da mesclagem e informa o conflito', function (): void {
    $dados = criarMesclagemParaDesfazer($this);
    DB::connection('pgsql_dono')->table('pessoas')->where('id', $dados['mantida'])->update(['nome' => 'Mudança posterior']);

    $this->postJson("/api/v1/painel/mesclagens/{$dados['operacao']}/desfazer")
        ->assertOk()->assertJson(['conflitos' => ['nome']]);

    expect(DB::connection('pgsql_dono')->table('pessoas')->find($dados['mantida'])->nome)->toBe('Mudança posterior');
});

it('recusa desfazer duas vezes e grava evento e auditoria', function (): void {
    $dados = criarMesclagemParaDesfazer($this);
    $rota = "/api/v1/painel/mesclagens/{$dados['operacao']}/desfazer";
    $this->postJson($rota)->assertOk();
    $this->postJson($rota)->assertUnprocessable()->assertJson(['codigo' => 'operacao_ja_encerrada']);

    $dono = DB::connection('pgsql_dono');
    expect($dono->table('eventos_linha_do_tempo')->where('tipo', 'mesclagem_desfeita')->count())->toBe(2)
        ->and($dono->table('auditoria')->where('evento', 'pessoa.mesclagem_desfeita')->count())->toBe(1);
});

it('expira após noventa dias, elimina snapshot e ficha absorvida', function (): void {
    $dados = criarMesclagemParaDesfazer($this);
    DB::connection('pgsql_dono')->table('operacoes_desfaziveis')->where('uuid', $dados['operacao'])
        ->update(['expira_em' => now()->subSecond()]);

    $this->postJson("/api/v1/painel/mesclagens/{$dados['operacao']}/desfazer")
        ->assertUnprocessable()->assertJson(['codigo' => 'operacao_expirada']);
    Bus::dispatchSync(new ExpirarDesfazerMesclagem($this->org));

    $dono = DB::connection('pgsql_dono');
    $operacao = $dono->table('operacoes_desfaziveis')->where('uuid', $dados['operacao'])->first();
    expect($operacao->estado)->toBe('expirada')
        ->and($operacao->snapshot)->toBeNull()
        ->and($dono->table('pessoas')->find($dados['absorvida']))->toBeNull();
});
