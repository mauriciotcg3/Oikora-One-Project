<?php

use App\Modules\Autorizacao\Domain\Permissao;
use Illuminate\Support\Facades\DB;

/** OIK-SPEC-0001 T-24 — cadastro e edição de igrejas (P FR-001). */
beforeEach(function (): void {
    $this->org = criarOrganizacao('Igrejas');
    $this->sede = criarIgreja($this->org, 'Sede');
    $this->regional = criarRegional($this->org, 'Regional Norte');
});

it('cria e edita igreja com igrejas.gerir, auditando sem o nome', function (): void {
    entrarNoPainel($this->org, $this->sede, [Permissao::IgrejasGerir, Permissao::PessoasVer]);
    $regional = uuidDe('regionais', $this->regional);

    $uuid = $this->postJson('/api/v1/painel/igrejas', ['nome' => '  Congregação Leste ', 'regional' => $regional])
        ->assertCreated()
        ->assertJsonPath('data.nome', 'Congregação Leste')
        ->assertJsonPath('data.regional.uuid', $regional)
        ->json('data.uuid');

    $this->putJson("/api/v1/painel/igrejas/{$uuid}", ['nome' => 'Congregação do Leste', 'regional' => null])
        ->assertOk()
        ->assertJsonPath('data.nome', 'Congregação do Leste')
        ->assertJsonPath('data.regional', null);

    $criada = auditoria('organizacao.igreja_criada');
    $atualizada = auditoria('organizacao.igreja_atualizada');
    expect($criada)->toHaveCount(1)
        ->and($criada[0]->alvo_id)->toBe($uuid)
        ->and(json_decode($criada[0]->campos, true))->toBe(['nome', 'regional_id'])
        ->and($atualizada)->toHaveCount(1)
        ->and(json_decode($atualizada[0]->campos, true))->toBe(['nome', 'regional_id'])
        ->and(json_encode([$criada, $atualizada]))->not->toContain('Leste');

    $this->getJson('/api/v1/painel/igrejas')->assertOk()
        ->assertJsonPath('data.*.nome', ['Congregação do Leste', 'Sede']);
});

it('recusa criar e editar igreja sem igrejas.gerir', function (): void {
    entrarNoPainel($this->org, $this->sede, [Permissao::PessoasVer, Permissao::PessoasCriar]);
    $sede = uuidDe('igrejas', $this->sede);

    $this->postJson('/api/v1/painel/igrejas', ['nome' => 'Nova'])->assertForbidden();
    $this->putJson("/api/v1/painel/igrejas/{$sede}", ['nome' => 'Outra'])->assertForbidden();

    expect(DB::connection('pgsql_dono')->table('igrejas')->where('organizacao_id', $this->org)->pluck('nome')->all())->toBe(['Sede'])
        ->and(auditoria('organizacao.igreja_criada'))->toBe([]);
});

it('recusa igrejas.gerir só no escopo de uma igreja: a gestão é da organização', function (): void {
    entrarNoPainel($this->org, $this->sede, [Permissao::IgrejasGerir], 'igreja', $this->sede);

    $this->postJson('/api/v1/painel/igrejas', ['nome' => 'Nova'])->assertForbidden();
});

it('não tem rota de exclusão de igreja', function (): void {
    entrarNoPainel($this->org, $this->sede, todasAsPermissoes(), sistema: 'administrador');

    $this->deleteJson('/api/v1/painel/igrejas/'.uuidDe('igrejas', $this->sede))->assertMethodNotAllowed();
    expect(DB::connection('pgsql_dono')->table('igrejas')->where('id', $this->sede)->exists())->toBeTrue();
});

it('não enxerga nem edita igreja de outra organização', function (): void {
    $outra = criarOrganizacao('Outra');
    $deFora = criarIgreja($outra, 'De fora');
    entrarNoPainel($this->org, $this->sede, [Permissao::IgrejasGerir]);

    $this->getJson('/api/v1/painel/igrejas')->assertOk()->assertJsonPath('data.*.nome', ['Sede']);
    $this->putJson('/api/v1/painel/igrejas/'.uuidDe('igrejas', $deFora), ['nome' => 'Invadida'])->assertNotFound();
});

it('recusa regional inexistente', function (): void {
    entrarNoPainel($this->org, $this->sede, [Permissao::IgrejasGerir]);

    $this->postJson('/api/v1/painel/igrejas', ['nome' => 'Nova', 'regional' => '00000000-0000-4000-8000-000000000000'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['regional' => 'regional_inexistente']);
});
