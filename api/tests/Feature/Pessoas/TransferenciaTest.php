<?php

use App\Modules\Autorizacao\Domain\Permissao;
use Illuminate\Support\Facades\DB;

/** OIK-SPEC-0001 T-34 — transferência entre igrejas (CA-05, CA-22; RN-04, RN-08; U-21; FLOW-009). */
beforeEach(function (): void {
    $this->org = criarOrganizacao('Transferências');
    $this->x = criarIgreja($this->org, 'Origem X');
    $this->y = criarIgreja($this->org, 'Destino Y');
    $this->pessoa = uuidDe('pessoas', criarPessoa($this->org, $this->x, ['nome' => 'Marta Transferida', 'vinculo' => 'membro', 'matricula' => 'MX-10']));

    $this->secretariaX = entrarNoPainel($this->org, $this->x, [Permissao::PessoasVer, Permissao::PessoasEditar, Permissao::TransferenciasAprovar], 'igreja', $this->x);
    $this->secretariaY = entrarNoPainel($this->org, $this->y, [Permissao::PessoasVer, Permissao::PessoasEditar, Permissao::TransferenciasSolicitar], 'igreja', $this->y);
});

function pedirTransferencia(object $teste): string
{
    comSessao($teste->secretariaY['cookie']);

    return $teste->postJson('/api/v1/painel/transferencias', ['pessoa' => $teste->pessoa, 'igreja_destino' => uuidDe('igrejas', $teste->y)])
        ->assertCreated()
        ->assertJsonPath('estado', 'solicitada')
        ->json('uuid');
}

/** @return list<array{tipo: string, conta_id: int|null, referencia_id: string}> */
function avisosDeTransferencia(): array
{
    return DB::connection('pgsql_dono')->table('avisos')->where('referencia_tipo', 'transferencia')->orderBy('id')
        ->get(['tipo', 'conta_id', 'referencia_id'])->map(fn (object $a): array => (array) $a)->all();
}

it('a secretaria de Y pede a pessoa de X: solicitada, com aviso a quem aprova em X', function (): void {
    $uuid = pedirTransferencia($this);

    expect(DB::connection('pgsql_dono')->table('transferencias')->where('uuid', $uuid)->value('estado'))->toBe('solicitada')
        ->and(avisosDeTransferencia())->toBe([
            ['tipo' => 'servico.transferencia_solicitada', 'conta_id' => $this->secretariaX['conta'], 'referencia_id' => $uuid],
        ])
        ->and(auditoria('pessoa.transferencia_solicitada')[0]->metadados)->toMatchArray([
            'transferencia_id' => $uuid, 'igreja_origem_id' => $this->x, 'igreja_destino_id' => $this->y,
        ]);

    $this->getJson('/api/v1/painel/transferencias')->assertOk()
        ->assertJsonPath('data.0.uuid', $uuid)
        ->assertJsonPath('data.0.pessoa.nome', 'Marta Transferida')
        ->assertJsonPath('data.0.acoes.decidir', false);
});

it('recusa um segundo pedido para a mesma pessoa enquanto houver um pendente', function (): void {
    pedirTransferencia($this);

    $this->postJson('/api/v1/painel/transferencias', ['pessoa' => $this->pessoa, 'igreja_destino' => uuidDe('igrejas', $this->y)])
        ->assertUnprocessable()
        ->assertJsonPath('codigo', 'transferencia_pendente');

    expect(DB::connection('pgsql_dono')->table('transferencias')->count())->toBe(1);
});

it('aprovar muda a igreja, mantém vínculo e matrícula e grava o evento: Y passa a abrir a ficha e X não', function (): void {
    $uuid = pedirTransferencia($this);
    $this->getJson("/api/v1/painel/pessoas/{$this->pessoa}")->assertForbidden();

    comSessao($this->secretariaX['cookie']);
    $this->getJson('/api/v1/painel/transferencias')->assertOk()->assertJsonPath('data.0.acoes.decidir', true);
    $this->postJson("/api/v1/painel/transferencias/{$uuid}/aprovar")->assertOk()->assertJsonPath('estado', 'aprovada');

    $pessoa = DB::connection('pgsql_dono')->table('pessoas')->where('uuid', $this->pessoa)->first();
    $evento = DB::connection('pgsql_dono')->table('eventos_linha_do_tempo')->where('pessoa_id', $pessoa->id)->where('tipo', 'transferencia')->first();
    expect((int) $pessoa->igreja_id)->toBe($this->y)
        ->and($pessoa->vinculo)->toBe('membro')
        ->and($pessoa->matricula)->toBe('MX-10')
        ->and(json_decode($evento->dados, true))->toEqualCanonicalizing(['transferencia' => $uuid, 'de' => uuidDe('igrejas', $this->x), 'para' => uuidDe('igrejas', $this->y)])
        ->and(array_column(avisosDeTransferencia(), 'tipo'))->toBe(['servico.transferencia_solicitada', 'servico.transferencia_decidida'])
        ->and(avisosDeTransferencia()[1]['conta_id'])->toBe($this->secretariaY['conta'])
        ->and(count(auditoria('pessoa.transferencia_aprovada')))->toBe(1);

    $this->getJson("/api/v1/painel/pessoas/{$this->pessoa}")->assertForbidden();
    $this->postJson("/api/v1/painel/transferencias/{$uuid}/aprovar")->assertUnprocessable()->assertJsonPath('codigo', 'transferencia_ja_decidida');

    comSessao($this->secretariaY['cookie']);
    $this->getJson("/api/v1/painel/pessoas/{$this->pessoa}")->assertOk();
});

it('recusar exige justificativa; com ela o vínculo fica inalterado e Y é avisada', function (): void {
    $uuid = pedirTransferencia($this);
    comSessao($this->secretariaX['cookie']);

    $this->postJson("/api/v1/painel/transferencias/{$uuid}/recusar", ['justificativa' => '   '])
        ->assertUnprocessable()->assertJsonValidationErrors(['justificativa']);
    $this->postJson("/api/v1/painel/transferencias/{$uuid}/recusar", ['justificativa' => 'A pessoa segue congregando aqui.'])
        ->assertOk()->assertJsonPath('estado', 'recusada');
    $this->postJson("/api/v1/painel/transferencias/{$uuid}/aprovar")
        ->assertUnprocessable()->assertJsonPath('codigo', 'transferencia_ja_decidida');

    $pessoa = DB::connection('pgsql_dono')->table('pessoas')->where('uuid', $this->pessoa)->first();
    $pedido = DB::connection('pgsql_dono')->table('transferencias')->where('uuid', $uuid)->first();
    expect((int) $pessoa->igreja_id)->toBe($this->x)
        ->and($pessoa->vinculo)->toBe('membro')
        ->and($pedido->justificativa)->toBe('A pessoa segue congregando aqui.')
        ->and(avisosDeTransferencia()[1])->toBe(['tipo' => 'servico.transferencia_decidida', 'conta_id' => $this->secretariaY['conta'], 'referencia_id' => $uuid])
        ->and(json_encode(auditoria('pessoa.transferencia_recusada')))->not->toContain('congregando');

    // Recusado, um novo pedido volta a ser possível.
    pedirTransferencia($this);
});

it('a secretaria de Y não aprova o próprio pedido, sem permissão em X', function (): void {
    $uuid = pedirTransferencia($this);

    $this->postJson("/api/v1/painel/transferencias/{$uuid}/aprovar")->assertForbidden();
    $this->postJson("/api/v1/painel/transferencias/{$uuid}/recusar", ['justificativa' => 'Não'])->assertForbidden();

    expect(DB::connection('pgsql_dono')->table('transferencias')->where('uuid', $uuid)->value('estado'))->toBe('solicitada');
});

it('não pede para igreja fora do próprio alcance nem para a igreja em que a pessoa já está', function (): void {
    comSessao($this->secretariaY['cookie']);
    $z = criarIgreja($this->org, 'Z');
    $daY = uuidDe('pessoas', criarPessoa($this->org, $this->y));

    $this->postJson('/api/v1/painel/transferencias', ['pessoa' => $this->pessoa, 'igreja_destino' => uuidDe('igrejas', $z)])->assertForbidden();
    $this->postJson('/api/v1/painel/transferencias', ['pessoa' => $daY, 'igreja_destino' => uuidDe('igrejas', $this->y)])
        ->assertUnprocessable()->assertJsonPath('codigo', 'mesma_igreja');
});
