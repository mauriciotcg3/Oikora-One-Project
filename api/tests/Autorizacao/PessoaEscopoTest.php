<?php

use App\Modules\Autorizacao\Contracts\ContextoDeAlcanceAusente;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Pessoas\Infrastructure\Pessoa;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->org = criarOrganizacao('Escopo de Pessoas');
    $this->igrejaX = criarIgreja($this->org, 'Igreja X');
    $this->igrejaY = criarIgreja($this->org, 'Igreja Y');
    $this->pessoaX = criarPessoa($this->org, $this->igrejaX, ['nome' => 'Ana da X']);
    $this->pessoaY = criarPessoa($this->org, $this->igrejaY, ['nome' => 'Bruno da Y']);
});

it('falha com authz.context_missing quando consulta Pessoa sem alcance no contexto', function (): void {
    expect(fn () => noContexto($this->org, null, fn (): int => Pessoa::query()->count()))
        ->toThrow(ContextoDeAlcanceAusente::class, 'authz.context_missing');
});

it('deixa a secretaria da igreja X abrir a ficha de X e nega a de Y', function (): void {
    entrarNoPainel($this->org, $this->igrejaX, [Permissao::PessoasVer, Permissao::PessoasEditar], 'igreja', $this->igrejaX);

    $this->getJson('/api/v1/painel/pessoas/'.uuidDe('pessoas', $this->pessoaX))
        ->assertOk()
        ->assertJsonPath('data.campos.nome', 'Ana da X')
        ->assertJsonPath('data.igreja.nome', 'Igreja X')
        ->assertJsonPath('meta.acoes', ['editar']);

    $this->getJson('/api/v1/painel/pessoas/'.uuidDe('pessoas', $this->pessoaY))
        ->assertForbidden()
        ->assertJsonPath('codigo', 'authz.denied');

    $this->getJson('/api/v1/painel/pessoas/'.Str::uuid())->assertNotFound();
});

it('filtra a consulta de Pessoa pelas igrejas do alcance de quem age', function (): void {
    entrarNoPainel($this->org, $this->igrejaX, [Permissao::PessoasVer], 'igreja', $this->igrejaX);

    $rota = '/api/v1/painel/pessoas/'.uuidDe('pessoas', $this->pessoaX);
    $sql = sqlDe(fn () => $this->getJson($rota)->assertOk());

    $localizacao = collect($sql)->first(fn (string $consulta): bool => str_contains($consulta, 'from "pessoas"'));
    expect($localizacao)->toContain('"pessoas"."igreja_id" in');
});
