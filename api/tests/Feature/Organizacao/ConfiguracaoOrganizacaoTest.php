<?php

use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Organizacao\Domain\CorPrimaria;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** OIK-SPEC-0001 T-24 — configuração da organização e logo por URL assinada (E-04; ADR-0006 §7; ADR-0011 §7). */
beforeEach(function (): void {
    $this->org = criarOrganizacao('Igreja Central');
    $this->sede = criarIgreja($this->org, 'Sede');
    // Disco local de verdade (o `Storage::fake` troca a URL assinada por uma falsa), numa pasta de teste.
    config(['filesystems.disks.arquivos.root' => storage_path('framework/testing/disks/arquivos-assinados')]);
    Storage::forgetDisk('arquivos');
});

afterEach(function (): void {
    Storage::disk('arquivos')->deleteDirectory('organizacoes');
});

function organizacaoComoDono(int $id): object
{
    return DB::connection('pgsql_dono')->table('organizacoes')->where('id', $id)->first();
}

it('mostra a configuração a todo o painel e atualiza com organizacao.configurar, auditando os campos', function (): void {
    entrarNoPainel($this->org, $this->sede, [Permissao::OrganizacaoConfigurar]);

    $this->getJson('/api/v1/painel/organizacao')->assertOk()
        ->assertJsonPath('data.nome', 'Igreja Central')
        ->assertJsonPath('data.fuso', 'America/Sao_Paulo')
        ->assertJsonPath('data.modo_matricula', 'manual')
        ->assertJsonPath('data.logo_url', null);

    $this->putJson('/api/v1/painel/organizacao', [
        'nome' => 'Igreja Central de Campinas',
        'fuso' => 'America/Manaus',
        'cor_primaria' => '#1d4ed8',
        'limite_whatsapp_mensal' => 500,
        'email_contato' => 'Secretaria@Igreja.test',
    ])->assertOk()
        ->assertJsonPath('data.nome', 'Igreja Central de Campinas')
        ->assertJsonPath('data.cor_primaria', '#1D4ED8')
        ->assertJsonPath('data.email_contato', 'secretaria@igreja.test');

    $evento = auditoria('organizacao.configurada');
    expect($evento)->toHaveCount(1)
        ->and(json_decode($evento[0]->campos, true))->toBe(['nome', 'fuso', 'cor_primaria', 'limite_whatsapp_mensal', 'email_contato'])
        ->and(json_encode($evento))->not->toContain('Campinas')
        ->and(json_encode($evento))->not->toContain('secretaria');
});

it('recusa mudar a organização sem organizacao.configurar', function (): void {
    entrarNoPainel($this->org, $this->sede, [Permissao::PessoasVer]);

    $this->getJson('/api/v1/painel/organizacao')->assertOk();
    $this->putJson('/api/v1/painel/organizacao', ['nome' => 'Outro nome'])->assertForbidden();
    $this->postJson('/api/v1/painel/organizacao/logo', ['tipo_conteudo' => 'image/png', 'tamanho' => 1000])->assertForbidden();

    expect(organizacaoComoDono($this->org)->nome)->toBe('Igreja Central');
});

it('recusa cor primária sem contraste AA com o texto do tema', function (): void {
    entrarNoPainel($this->org, $this->sede, [Permissao::OrganizacaoConfigurar]);

    $this->putJson('/api/v1/painel/organizacao', ['cor_primaria' => '#FFE066'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cor_primaria' => 'contraste_insuficiente']);
    $this->putJson('/api/v1/painel/organizacao', ['cor_primaria' => 'azul'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cor_primaria']);

    expect(organizacaoComoDono($this->org)->cor_primaria)->toBeNull()
        ->and(CorPrimaria::contraste('#767676', '#FFFFFF'))->toBeGreaterThanOrEqual(4.5)
        ->and(CorPrimaria::temContrasteAA('#777777'))->toBeFalse();
});

it('aceita o modo de matrícula automática na configuração, com pessoas.configurar', function (): void {
    entrarNoPainel($this->org, $this->sede, [Permissao::PessoasConfigurar]);

    $this->putJson('/api/v1/painel/organizacao', ['modo_matricula' => 'automatica'])
        ->assertOk()
        ->assertJsonPath('data.modo_matricula', 'automatica');

    $evento = auditoria('organizacao.configurada');
    expect(organizacaoComoDono($this->org)->modo_matricula)->toBe('automatica')
        ->and($evento[0]->metadados)->toBe(['modo_matricula_antes' => 'manual', 'modo_matricula_depois' => 'automatica']);

    // O modo de matrícula é de pessoas.configurar; o resto continua pedindo organizacao.configurar.
    $this->putJson('/api/v1/painel/organizacao', ['nome' => 'Outro'])->assertForbidden();
});

it('recusa mudar o modo de matrícula só com organizacao.configurar', function (): void {
    entrarNoPainel($this->org, $this->sede, [Permissao::OrganizacaoConfigurar]);

    $this->putJson('/api/v1/painel/organizacao', ['modo_matricula' => 'automatica'])->assertForbidden();
    $this->putJson('/api/v1/painel/organizacao', ['modo_matricula' => 'semestral'])->assertUnprocessable();
});

it('envia e lê o logo só por URL assinada de curta duração', function (): void {
    entrarNoPainel($this->org, $this->sede, [Permissao::OrganizacaoConfigurar]);

    $envio = $this->postJson('/api/v1/painel/organizacao/logo', ['tipo_conteudo' => 'image/png', 'tamanho' => 2048])
        ->assertCreated()
        ->assertJsonPath('data.metodo', 'PUT')
        ->assertJsonPath('data.cabecalhos.Content-Type', 'image/png')
        ->json('data');
    $uuidOrg = uuidDe('organizacoes', $this->org);
    expect($envio['caminho'])->toStartWith("organizacoes/{$uuidOrg}/logo/")
        ->and($envio['url'])->toContain('signature=')->toContain('expires=')
        ->and(now()->diffInMinutes($envio['expira_em']))->toBeLessThanOrEqual(5.0);

    // Sem assinatura, o armazenamento recusa; com ela, recebe o arquivo direto do navegador.
    $this->call('PUT', strtok($envio['url'], '?'), [], [], [], ['CONTENT_TYPE' => 'image/png'], 'png-falso')->assertForbidden();
    $this->call('PUT', $envio['url'], [], [], [], ['CONTENT_TYPE' => 'image/png'], 'png-falso')->assertNoContent();

    $logoUrl = $this->putJson('/api/v1/painel/organizacao', ['logo' => $envio['caminho']])
        ->assertOk()
        ->assertJsonPath('data.logo', $envio['caminho'])
        ->json('data.logo_url');
    expect($logoUrl)->toContain('signature=')->toContain('expires=');

    expect($this->get($logoUrl)->assertOk()->streamedContent())->toBe('png-falso');
    $this->get(strtok($logoUrl, '?'))->assertForbidden();
    $this->travel(6)->minutes();
    $this->get($logoUrl)->assertForbidden();
});

it('recusa logo fora do caminho emitido para a organização ou ainda não enviado', function (): void {
    entrarNoPainel($this->org, $this->sede, [Permissao::OrganizacaoConfigurar]);
    $outra = uuidDe('organizacoes', criarOrganizacao('Outra'));
    Storage::disk('arquivos')->put("organizacoes/{$outra}/logo/x.png", 'x');

    $this->putJson('/api/v1/painel/organizacao', ['logo' => "organizacoes/{$outra}/logo/x.png"])
        ->assertUnprocessable()->assertJsonValidationErrors(['logo' => 'logo_invalido']);
    $this->putJson('/api/v1/painel/organizacao', ['logo' => 'organizacoes/'.uuidDe('organizacoes', $this->org).'/logo/nao-enviado.png'])
        ->assertUnprocessable()->assertJsonValidationErrors(['logo' => 'logo_invalido']);
    $this->postJson('/api/v1/painel/organizacao/logo', ['tipo_conteudo' => 'image/svg+xml', 'tamanho' => 10])
        ->assertUnprocessable();
    $this->postJson('/api/v1/painel/organizacao/logo', ['tipo_conteudo' => 'image/png', 'tamanho' => 5_000_000])
        ->assertUnprocessable();
});

it('recusa fuso inválido', function (): void {
    entrarNoPainel($this->org, $this->sede, [Permissao::OrganizacaoConfigurar]);

    $this->putJson('/api/v1/painel/organizacao', ['fuso' => 'America/Atlantida'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['fuso' => 'fuso_invalido']);

    expect(organizacaoComoDono($this->org)->fuso)->toBe('America/Sao_Paulo');
});
