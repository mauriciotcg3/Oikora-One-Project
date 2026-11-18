<?php

use App\Modules\Autorizacao\Domain\Permissao;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** OIK-SPEC-0001 T-27 — ficha por seções, completude e foto (CA-14; RN-10; RN-13). */
beforeEach(function (): void {
    $this->org = criarOrganizacao('Ficha');
    $this->igreja = criarIgreja($this->org, 'Sede');
    $this->pessoa = criarPessoaComFicha($this->org, $this->igreja, [
        'nome' => 'Maria Souza', 'vinculo' => 'membro', 'nascimento' => '1990-05-10',
    ]);
    $this->uuid = uuidDe('pessoas', $this->pessoa);

    config(['filesystems.disks.arquivos.root' => storage_path('framework/testing/disks/arquivos-assinados')]);
    Storage::forgetDisk('arquivos');
});

afterEach(function (): void {
    Storage::disk('arquivos')->deleteDirectory('organizacoes');
});

function obrigatoriosDeMembro(int $organizacaoId, array $campos): void
{
    foreach ($campos as $campo) {
        DB::connection('pgsql_dono')->table('campos_obrigatorios')->insert([
            'organizacao_id' => $organizacaoId, 'vinculo' => 'membro', 'campo' => $campo, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

/** @return list<string> */
function camposAuditados(string $evento): array
{
    return array_map(static fn (object $linha): array => json_decode($linha->campos, true), auditoria($evento));
}

const EDITA_TUDO = [
    Permissao::PessoasVer, Permissao::PessoasEditar,
    Permissao::DocumentosVer, Permissao::DocumentosEditar,
    Permissao::ContatosVer, Permissao::ContatosEditar,
];

it('calcula a completude pelos obrigatórios do vínculo', function (): void {
    obrigatoriosDeMembro($this->org, ['cep', 'nascimento']);
    entrarNoPainel($this->org, $this->igreja, EDITA_TUDO);

    $this->getJson("/api/v1/painel/pessoas/{$this->uuid}")->assertOk()
        ->assertJsonPath('data.completude.percentual', 50)
        ->assertJsonPath('data.completude.faltando', ['cep']);

    $this->patchJson("/api/v1/painel/pessoas/{$this->uuid}", ['secao' => 'endereco', 'cep' => '13010-000', 'cidade' => 'Campinas'])
        ->assertOk()
        ->assertJsonPath('data.completude.percentual', 100)
        ->assertJsonPath('data.enderecos.0.cep', '13010-000');

    DB::connection('pgsql_dono')->table('campos_obrigatorios')->delete();
    DB::connection('pgsql_dono')->table('pessoa_enderecos')->delete();
    $this->getJson("/api/v1/painel/pessoas/{$this->uuid}")->assertOk()
        ->assertJsonPath('data.completude.percentual', 100);
});

it('conta na completude campo sensível que quem vê não enxerga, sem mostrar o valor', function (): void {
    obrigatoriosDeMembro($this->org, ['cpf', 'nascimento']);
    DB::connection('pgsql_dono')->table('pessoas')->where('id', $this->pessoa)->update(['cpf' => 'cifrado-qualquer']);
    entrarNoPainel($this->org, $this->igreja, [Permissao::PessoasVer]);

    $this->getJson("/api/v1/painel/pessoas/{$this->uuid}")->assertOk()
        ->assertJsonPath('data.completude.percentual', 100)
        ->assertJsonMissingPath('data.campos.cpf');
});

it('recusa a seção documentos sem pessoas.sensiveis.documentos.editar, com 422 por campo', function (): void {
    entrarNoPainel($this->org, $this->igreja, [Permissao::PessoasVer, Permissao::PessoasEditar, Permissao::DocumentosVer]);

    $this->patchJson("/api/v1/painel/pessoas/{$this->uuid}", ['secao' => 'documentos', 'cpf' => '529.982.247-25', 'rg' => '12.345.678-9'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cpf' => 'sem_permissao_categoria', 'rg' => 'sem_permissao_categoria']);

    expect(DB::connection('pgsql_dono')->table('pessoas')->where('id', $this->pessoa)->value('cpf'))->toBeNull()
        ->and(auditoria('pessoa.atualizada'))->toBe([]);
});

it('grava documentos com a permissão da categoria e recusa CPF de outra pessoa', function (): void {
    criarPessoaComFicha($this->org, $this->igreja, ['nome' => 'Outra', 'cpf' => '11144477735']);
    entrarNoPainel($this->org, $this->igreja, EDITA_TUDO);

    $this->patchJson("/api/v1/painel/pessoas/{$this->uuid}", ['secao' => 'documentos', 'cpf' => '111.444.777-35'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cpf' => 'cpf_em_uso']);

    $this->patchJson("/api/v1/painel/pessoas/{$this->uuid}", ['secao' => 'documentos', 'cpf' => '529.982.247-25'])
        ->assertOk()
        ->assertJsonPath('data.campos.cpf', '52998224725');

    // O mesmo CPF de novo é dela mesma: não conta como em uso nem gera auditoria.
    $this->patchJson("/api/v1/painel/pessoas/{$this->uuid}", ['secao' => 'documentos', 'cpf' => '52998224725'])->assertOk();
    expect(camposAuditados('pessoa.atualizada'))->toBe([['cpf']]);
});

it('audita só os nomes dos campos alterados, sem valores', function (): void {
    entrarNoPainel($this->org, $this->igreja, EDITA_TUDO);

    $this->patchJson("/api/v1/painel/pessoas/{$this->uuid}", [
        'secao' => 'pessoais', 'nome' => 'Maria  Souza Lima ', 'nascimento' => '1990-05-10', 'estado_civil' => 'casada',
    ])->assertOk()
        ->assertJsonPath('data.campos.nome', 'Maria Souza Lima');

    $evento = auditoria('pessoa.atualizada');
    expect(camposAuditados('pessoa.atualizada'))->toBe([['nome', 'estado_civil']])
        ->and($evento[0]->alvo_id)->toBe($this->uuid)
        ->and((int) $evento[0]->igreja_id)->toBe($this->igreja)
        ->and(json_encode($evento))->not->toContain('Lima')->not->toContain('casada');
});

it('salva o celular em E.164 com "é WhatsApp" e o e-mail em minúsculas', function (): void {
    entrarNoPainel($this->org, $this->igreja, EDITA_TUDO);
    $antigo = criarContato($this->org, $this->pessoa, 'email', 'maria@exemplo.test');
    DB::connection('pgsql_dono')->table('pessoa_contatos')->where('id', $antigo)->update(['descadastrado_em' => now()]);

    $this->patchJson("/api/v1/painel/pessoas/{$this->uuid}", ['secao' => 'contatos', 'contatos' => [
        ['tipo' => 'celular', 'valor' => '(11) 98765-4321', 'e_whatsapp' => true],
        ['tipo' => 'email', 'valor' => ' Maria@Exemplo.TEST '],
        ['tipo' => 'telefone', 'valor' => '(19) 3232-1000', 'e_whatsapp' => true],
    ]])->assertOk()
        ->assertJsonFragment(['tipo' => 'celular', 'valor' => '+5511987654321', 'e_whatsapp' => true]);

    $contatos = DB::connection('pgsql_dono')->table('pessoa_contatos')->where('pessoa_id', $this->pessoa)->orderBy('tipo')->get()->keyBy('tipo');
    expect($contatos['celular']->valor)->toBe('+5511987654321')
        ->and($contatos['celular']->e_whatsapp)->toBeTrue()
        ->and($contatos['email']->valor)->toBe('maria@exemplo.test')
        ->and($contatos['email']->id)->toBe($antigo)
        ->and($contatos['email']->descadastrado_em)->not->toBeNull()
        ->and($contatos['telefone']->valor)->toBe('+551932321000')
        ->and($contatos['telefone']->e_whatsapp)->toBeFalse()
        // O e-mail continua o mesmo, mas passou a ser o principal do tipo: também conta como alteração.
        ->and(camposAuditados('pessoa.atualizada'))->toBe([['celular', 'e_whatsapp', 'email', 'telefone']]);

    $this->patchJson("/api/v1/painel/pessoas/{$this->uuid}", ['secao' => 'contatos', 'contatos' => [
        ['tipo' => 'celular', 'valor' => '123'],
        ['tipo' => 'email', 'valor' => 'sem-arroba'],
    ]])->assertUnprocessable()
        ->assertJsonValidationErrors(['contatos.0.valor' => 'telefone_invalido', 'contatos.1.valor' => 'email_invalido']);
});

it('envia a foto por URL assinada e a ficha a devolve por URL assinada', function (): void {
    entrarNoPainel($this->org, $this->igreja, EDITA_TUDO);

    $envio = $this->postJson("/api/v1/painel/pessoas/{$this->uuid}/foto", ['tipo_conteudo' => 'image/jpeg', 'tamanho' => 50_000])
        ->assertCreated()
        ->json('data');
    expect($envio['url'])->toContain('signature=')
        ->and($envio['caminho'])->toStartWith('organizacoes/'.uuidDe('organizacoes', $this->org)."/pessoas/{$this->uuid}/foto/");
    $this->call('PUT', $envio['url'], [], [], [], ['CONTENT_TYPE' => 'image/jpeg'], 'jpeg-falso')->assertNoContent();

    $fotoUrl = $this->patchJson("/api/v1/painel/pessoas/{$this->uuid}", ['secao' => 'pessoais', 'foto' => $envio['caminho']])
        ->assertOk()
        ->json('data.foto_url');
    expect($fotoUrl)->toContain('signature=')->toContain('expires=')
        ->and($this->get($fotoUrl)->assertOk()->streamedContent())->toBe('jpeg-falso')
        ->and(camposAuditados('pessoa.atualizada'))->toBe([['foto_path']]);
    $this->get(strtok($fotoUrl, '?'))->assertForbidden();

    $this->patchJson("/api/v1/painel/pessoas/{$this->uuid}", ['secao' => 'pessoais', 'foto' => 'organizacoes/outra/foto.jpg'])
        ->assertUnprocessable()->assertJsonValidationErrors(['foto' => 'foto_invalida']);
});

it('recusa editar sem pessoas.editar, fora do alcance ou pessoa inexistente', function (): void {
    $outraIgreja = criarIgreja($this->org, 'Outra');
    $deFora = uuidDe('pessoas', criarPessoa($this->org, $outraIgreja));
    entrarNoPainel($this->org, $this->igreja, [Permissao::PessoasVer, Permissao::PessoasEditar], 'igreja', $this->igreja);

    $this->patchJson("/api/v1/painel/pessoas/{$deFora}", ['secao' => 'pessoais', 'nome' => 'Invadida'])->assertForbidden();
    $this->postJson("/api/v1/painel/pessoas/{$deFora}/foto", ['tipo_conteudo' => 'image/png', 'tamanho' => 10])->assertForbidden();
    $this->patchJson('/api/v1/painel/pessoas/00000000-0000-4000-8000-000000000000', ['secao' => 'pessoais', 'nome' => 'Ninguém'])->assertNotFound();

    entrarNoPainel($this->org, $this->igreja, [Permissao::PessoasVer]);
    $this->patchJson("/api/v1/painel/pessoas/{$this->uuid}", ['secao' => 'pessoais', 'nome' => 'Sem permissão'])->assertForbidden();
    $this->patchJson("/api/v1/painel/pessoas/{$this->uuid}", ['secao' => 'inexistente'])->assertUnprocessable();
});
