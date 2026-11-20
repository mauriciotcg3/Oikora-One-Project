<?php

use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Pessoas\Application\Configuracao\ListasDeValores;
use App\Modules\Pessoas\Domain\ListaValores;
use App\Modules\Pessoas\Presentation\Configuracao\ValorAtivoDaLista;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/** OIK-SPEC-0001 T-25 — listas de valores da organização (CA-30; U-09; U-28). */
beforeEach(function (): void {
    $this->org = criarOrganizacao('Listas de valores');
    $this->igreja = criarIgreja($this->org, 'Sede');
});

function idDoItem(string $uuid): int
{
    return (int) DB::connection('pgsql_dono')->table('listas_valores')->where('uuid', $uuid)->value('id');
}

it('cria, renomeia, ordena e desativa itens de cada uma das seis listas', function (string $lista): void {
    entrarNoPainel($this->org, $this->igreja, todasAsPermissoes(), sistema: 'administrador');

    $primeiro = $this->postJson("/api/v1/painel/listas/{$lista}", ['nome' => 'Primeiro'])
        ->assertCreated()
        ->assertJsonPath('data.lista', $lista)
        ->assertJsonPath('data.ativo', true)
        ->assertJsonPath('data.ordem', 1)
        ->json('data.uuid');
    $this->postJson("/api/v1/painel/listas/{$lista}", ['nome' => 'Segundo'])->assertCreated()->assertJsonPath('data.ordem', 2);

    $this->putJson("/api/v1/painel/listas/{$lista}/{$primeiro}", ['nome' => 'Renomeado', 'ordem' => 3, 'ativo' => false])
        ->assertOk()
        ->assertJsonPath('data.nome', 'Renomeado')
        ->assertJsonPath('data.ordem', 3)
        ->assertJsonPath('data.ativo', false);

    $this->getJson("/api/v1/painel/listas/{$lista}")->assertOk()
        ->assertJsonPath('data.*.nome', ['Segundo', 'Renomeado'])
        ->assertJsonPath('data.*.ativo', [true, false]);
})->with(array_map(static fn (ListaValores $lista): string => $lista->value, ListaValores::cases()));

it('recusa nome repetido na mesma lista, sem diferenciar maiúsculas', function (): void {
    entrarNoPainel($this->org, $this->igreja, todasAsPermissoes(), sistema: 'administrador');
    $this->postJson('/api/v1/painel/listas/funcao', ['nome' => 'Diácono'])->assertCreated();
    $outro = $this->postJson('/api/v1/painel/listas/funcao', ['nome' => 'Presbítero'])->json('data.uuid');

    $this->postJson('/api/v1/painel/listas/funcao', ['nome' => 'diácono'])->assertUnprocessable()->assertJsonPath('codigo', 'nome_em_uso');
    $this->putJson("/api/v1/painel/listas/funcao/{$outro}", ['nome' => 'DIÁCONO'])->assertUnprocessable()->assertJsonPath('codigo', 'nome_em_uso');
    $this->postJson('/api/v1/painel/listas/tipo_membro', ['nome' => 'Diácono'])->assertCreated();
});

it('recusa lista desconhecida e item de outra lista', function (): void {
    entrarNoPainel($this->org, $this->igreja, todasAsPermissoes(), sistema: 'administrador');
    $item = $this->postJson('/api/v1/painel/listas/funcao', ['nome' => 'Músico'])->json('data.uuid');

    $this->getJson('/api/v1/painel/listas/cores')->assertNotFound();
    $this->putJson("/api/v1/painel/listas/tipo_membro/{$item}", ['ativo' => false])->assertNotFound()->assertJsonPath('codigo', 'item_inexistente');
});

it('nega a manutenção à secretaria, que só lê os itens ativos', function (): void {
    entrarNoPainel($this->org, $this->igreja, todasAsPermissoes(), sistema: 'administrador');
    $ativo = $this->postJson('/api/v1/painel/listas/como_chegou', ['nome' => 'Convite de amigo'])->json('data.uuid');
    $inativo = $this->postJson('/api/v1/painel/listas/como_chegou', ['nome' => 'Panfleto'])->json('data.uuid');
    $this->putJson("/api/v1/painel/listas/como_chegou/{$inativo}", ['ativo' => false])->assertOk();

    entrarNoPainel($this->org, $this->igreja, [Permissao::PessoasVer, Permissao::PessoasCriar, Permissao::PessoasEditar], sistema: 'secretaria');

    $this->postJson('/api/v1/painel/listas/como_chegou', ['nome' => 'Rádio'])->assertForbidden()->assertJsonPath('codigo', 'authz.denied');
    $this->putJson("/api/v1/painel/listas/como_chegou/{$ativo}", ['nome' => 'Outro'])->assertForbidden();
    $this->putJson("/api/v1/painel/listas/como_chegou/{$ativo}", ['ordem' => 9])->assertForbidden();
    $this->putJson("/api/v1/painel/listas/como_chegou/{$ativo}", ['ativo' => false])->assertForbidden();
    $this->getJson('/api/v1/painel/listas/como_chegou')->assertOk()->assertJsonPath('data.*.uuid', [$ativo]);
});

it('nega até a leitura a quem não cadastra nem configura', function (): void {
    entrarNoPainel($this->org, $this->igreja, [Permissao::PessoasVer]);

    $this->getJson('/api/v1/painel/listas/funcao')->assertForbidden();
});

it('não aceita item desativado em cadastro novo, mas mostra o nome onde já foi usado', function (): void {
    entrarNoPainel($this->org, $this->igreja, todasAsPermissoes(), sistema: 'administrador');
    $uuid = $this->postJson('/api/v1/painel/listas/tipo_membro', ['nome' => 'Comungante'])->json('data.uuid');
    $pessoa = criarPessoa($this->org, $this->igreja, ['tipo_membro_id' => idDoItem($uuid)]);

    $aceita = fn (): bool => noContexto($this->org, null, fn (): bool => Validator::make(
        ['tipo_membro' => $uuid],
        ['tipo_membro' => [new ValorAtivoDaLista(ListaValores::TipoMembro)]],
    )->passes());

    expect($aceita())->toBeTrue();

    $this->putJson("/api/v1/painel/listas/tipo_membro/{$uuid}", ['ativo' => false])->assertOk();

    $usado = (int) DB::connection('pgsql_dono')->table('pessoas')->where('id', $pessoa)->value('tipo_membro_id');
    expect($aceita())->toBeFalse()
        ->and(noContexto($this->org, null, fn (): array => app(ListasDeValores::class)->nomes([$usado])))->toBe([$usado => 'Comungante']);
});

it('audita cada mudança, sem gravar o nome do item', function (): void {
    entrarNoPainel($this->org, $this->igreja, todasAsPermissoes(), sistema: 'administrador');
    $uuid = $this->postJson('/api/v1/painel/listas/motivo_inativacao', ['nome' => 'Mudou de cidade'])->json('data.uuid');
    $this->putJson("/api/v1/painel/listas/motivo_inativacao/{$uuid}", ['nome' => 'Mudança de cidade', 'ativo' => false])->assertOk();
    $this->putJson("/api/v1/painel/listas/motivo_inativacao/{$uuid}", ['ativo' => false])->assertOk();

    $criado = auditoria('pessoas.lista_valor_criado');
    $alterado = auditoria('pessoas.lista_valor_atualizado');

    expect($criado)->toHaveCount(1)
        ->and($criado[0]->alvo_id)->toBe($uuid)
        ->and($criado[0]->metadados)->toBe(['lista' => 'motivo_inativacao', 'ordem_depois' => 1])
        ->and($alterado)->toHaveCount(1)
        // `jsonb` não guarda a ordem das chaves.
        ->and($alterado[0]->metadados)->toEqual([
            'lista' => 'motivo_inativacao',
            'nome_alterado' => true,
            'ativo_antes' => true,
            'ativo_depois' => false,
        ]);
});

it('isola as listas por organização', function (): void {
    $outra = criarOrganizacao('Outra');
    $igrejaOutra = criarIgreja($outra, 'Outra sede');
    entrarNoPainel($outra, $igrejaOutra, todasAsPermissoes(), sistema: 'administrador');
    $deOutra = $this->postJson('/api/v1/painel/listas/funcao', ['nome' => 'Regente'])->json('data.uuid');

    entrarNoPainel($this->org, $this->igreja, todasAsPermissoes(), sistema: 'administrador');

    $this->getJson('/api/v1/painel/listas/funcao')->assertOk()->assertJsonPath('data', []);
    $this->putJson("/api/v1/painel/listas/funcao/{$deOutra}", ['ativo' => false])->assertNotFound();
    $this->postJson('/api/v1/painel/listas/funcao', ['nome' => 'Regente'])->assertCreated();
});
