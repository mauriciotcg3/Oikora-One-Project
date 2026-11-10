<?php

use App\Modules\Autorizacao\Domain\Permissao;

/** Organização com duas igrejas e um administrador logado no painel. */
beforeEach(function (): void {
    $this->org = criarOrganizacao('Gestão de papéis');
    $this->igrejaA = criarIgreja($this->org, 'Igreja A');
    $this->igrejaB = criarIgreja($this->org, 'Igreja B');
});

function entrarComoAdministrador(object $teste): array
{
    return entrarNoPainel($teste->org, $teste->igrejaA, todasAsPermissoes(), sistema: 'administrador');
}

it('cria papel com permissões do catálogo e recusa permissão fora dele', function (): void {
    entrarComoAdministrador($this);

    $this->postJson('/api/v1/painel/papeis', [
        'nome' => 'Tesouraria',
        'permissoes' => ['pessoas.ver', 'pessoas.exportar'],
    ])->assertCreated()
        ->assertJsonPath('data.nome', 'Tesouraria')
        ->assertJsonPath('data.permissoes', ['pessoas.exportar', 'pessoas.ver'])
        ->assertJsonPath('data.meta.acoes', ['editar']);

    $this->postJson('/api/v1/painel/papeis', ['nome' => 'Inventado', 'permissoes' => ['pessoas.voar']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('permissoes.0');

    expect(auditoria('autorizacao.papel_criado'))->toHaveCount(1)
        ->and(auditoria('autorizacao.papel_criado')[0]->metadados['permissoes_depois'])->toBe(['pessoas.exportar', 'pessoas.ver']);
});

it('lista o catálogo de permissões e os papéis', function (): void {
    entrarComoAdministrador($this);

    $this->getJson('/api/v1/painel/permissoes')->assertOk()->assertJsonPath('data', todasAsPermissoes());
    $this->getJson('/api/v1/painel/papeis')->assertOk()->assertJsonPath('data.0.sistema', 'administrador');
});

it('permite editar os papéis prontos', function (): void {
    entrarComoAdministrador($this);
    $secretaria = criarPapel($this->org, 'Secretaria', [Permissao::PessoasVer, Permissao::DocumentosVer], 'secretaria');

    $this->putJson('/api/v1/painel/papeis/'.uuidDe('papeis', $secretaria), [
        'permissoes' => ['pessoas.ver', 'pessoas.sensiveis.documentos.ver', 'pessoas.sensiveis.saude.ver'],
    ])->assertOk()
        ->assertJsonPath('data.sistema', 'secretaria')
        ->assertJsonPath('data.permissoes', ['pessoas.sensiveis.documentos.ver', 'pessoas.sensiveis.saude.ver', 'pessoas.ver']);
});

it('recusa remover a última atribuição ativa do Administrador e audita', function (): void {
    $admin = entrarComoAdministrador($this);
    $rota = '/api/v1/painel/acessos/'.uuidDe('acessos', $admin['acesso']).'/atribuicoes/'.uuidDe('atribuicoes_papel', $admin['atribuicao']);

    $this->deleteJson($rota)->assertUnprocessable()->assertJsonPath('codigo', 'ultimo_administrador');

    $bloqueios = auditoria('authz.last_admin_blocked');
    expect($bloqueios)->toHaveCount(1)
        ->and($bloqueios[0]->metadados)->toBe(['motivo_codigo' => 'ultima_atribuicao'])
        ->and((int) $bloqueios[0]->acesso_id)->toBe($admin['acesso']);
    expect(DB::connection('pgsql_dono')->table('atribuicoes_papel')->where('id', $admin['atribuicao'])->exists())->toBeTrue();
});

it('remove a atribuição de Administrador quando outro administrador ativo continua', function (): void {
    $admin = entrarComoAdministrador($this);
    $outraConta = criarContaVerificada(['email' => 'outro.admin@exemplo.test', 'celular' => '+5511900000001']);
    $outroAcesso = criarAcesso($this->org, $outraConta, criarPessoa($this->org, $this->igrejaA));
    atribuirPapel($this->org, $outroAcesso, $admin['papel']);

    $this->deleteJson('/api/v1/painel/acessos/'.uuidDe('acessos', $admin['acesso']).'/atribuicoes/'.uuidDe('atribuicoes_papel', $admin['atribuicao']))
        ->assertNoContent();

    expect(auditoria('autorizacao.atribuicao_revogada'))->toHaveCount(1);
});

it('não conta administrador com acesso revogado para manter o último', function (): void {
    $admin = entrarComoAdministrador($this);
    $outraConta = criarContaVerificada(['email' => 'revogado@exemplo.test', 'celular' => '+5511900000002']);
    $revogado = criarAcesso($this->org, $outraConta, criarPessoa($this->org, $this->igrejaA), 'revogado');
    atribuirPapel($this->org, $revogado, $admin['papel']);

    $this->deleteJson('/api/v1/painel/acessos/'.uuidDe('acessos', $admin['acesso']).'/atribuicoes/'.uuidDe('atribuicoes_papel', $admin['atribuicao']))
        ->assertUnprocessable()
        ->assertJsonPath('codigo', 'ultimo_administrador');
});

it('recusa tirar papeis.gerir ou acessos.gerir do papel Administrador', function (string $retirada): void {
    $admin = entrarComoAdministrador($this);
    $permissoes = array_values(array_diff(todasAsPermissoes(), [$retirada]));

    $this->putJson('/api/v1/painel/papeis/'.uuidDe('papeis', $admin['papel']), ['permissoes' => $permissoes])
        ->assertUnprocessable()
        ->assertJsonPath('codigo', 'administrador_sem_governanca');

    expect(auditoria('authz.last_admin_blocked')[0]->metadados['motivo_codigo'])->toBe('governanca_do_administrador');
})->with(['papeis.gerir', 'acessos.gerir']);

it('bloqueia conceder permissão que quem concede não tem', function (): void {
    entrarNoPainel($this->org, $this->igrejaA, [Permissao::PapeisGerir, Permissao::AcessosGerir, Permissao::PessoasVer]);

    $this->postJson('/api/v1/painel/papeis', ['nome' => 'Exclusão', 'permissoes' => ['pessoas.ver', 'pessoas.excluir']])
        ->assertForbidden()
        ->assertJsonPath('codigo', 'escalada_bloqueada');

    $escaladas = auditoria('authz.escalation_blocked');
    expect($escaladas)->toHaveCount(1)
        ->and($escaladas[0]->metadados)->toEqual(['motivo_codigo' => 'permissao_nao_possuida', 'permissoes' => ['pessoas.excluir']]);
});

it('bloqueia conceder escopo que quem concede não tem', function (): void {
    // Gere acessos na organização, mas só vê pessoas da igreja A.
    $ator = entrarNoPainel($this->org, $this->igrejaA, [Permissao::AcessosGerir]);
    $leitura = criarPapel($this->org, 'Leitura', [Permissao::PessoasVer]);
    atribuirPapel($this->org, $ator['acesso'], $leitura, 'igreja', $this->igrejaA);

    $alvoConta = criarContaVerificada(['email' => 'alvo@exemplo.test', 'celular' => '+5511900000003']);
    $alvo = uuidDe('acessos', criarAcesso($this->org, $alvoConta, criarPessoa($this->org, $this->igrejaB)));
    $rota = "/api/v1/painel/acessos/{$alvo}/atribuicoes";
    $papel = uuidDe('papeis', $leitura);

    $this->postJson($rota, ['papel' => $papel, 'escopo_tipo' => 'igreja', 'escopo' => uuidDe('igrejas', $this->igrejaB)])
        ->assertForbidden()->assertJsonPath('codigo', 'escalada_bloqueada');
    $this->postJson($rota, ['papel' => $papel, 'escopo_tipo' => 'organizacao'])
        ->assertForbidden()->assertJsonPath('codigo', 'escalada_bloqueada');

    $escalada = auditoria('authz.escalation_blocked')[0]->metadados;
    expect($escalada)->toEqual([
        'motivo_codigo' => 'escopo_nao_possuido',
        'permissoes' => ['pessoas.ver'],
        'escopo_tipo' => 'igreja',
        'escopo_id' => $this->igrejaB,
    ]);

    $this->postJson($rota, ['papel' => $papel, 'escopo_tipo' => 'igreja', 'escopo' => uuidDe('igrejas', $this->igrejaA)])
        ->assertCreated()
        ->assertJsonPath('data.escopo_tipo', 'igreja');
});

it('guarda na auditoria as permissões e o escopo antes e depois', function (): void {
    $admin = entrarComoAdministrador($this);
    $papel = criarPapel($this->org, 'Recepção', [Permissao::PessoasVer]);

    $this->putJson('/api/v1/painel/papeis/'.uuidDe('papeis', $papel), [
        'nome' => 'Recepção e cadastro',
        'permissoes' => ['pessoas.ver', 'pessoas.criar'],
    ])->assertOk();

    $edicao = auditoria('autorizacao.papel_atualizado')[0];
    expect($edicao->metadados)->toEqual([
        'permissoes_antes' => ['pessoas.ver'],
        'permissoes_depois' => ['pessoas.criar', 'pessoas.ver'],
        'nome_alterado' => true,
    ])->and($edicao->alvo_tipo)->toBe('papel');

    $regional = criarRegional($this->org, 'Norte');
    $alvoConta = criarContaVerificada(['email' => 'recepcao@exemplo.test', 'celular' => '+5511900000004']);
    $alvo = criarAcesso($this->org, $alvoConta, criarPessoa($this->org, $this->igrejaA));

    $this->postJson('/api/v1/painel/acessos/'.uuidDe('acessos', $alvo).'/atribuicoes', [
        'papel' => uuidDe('papeis', $papel), 'escopo_tipo' => 'regional', 'escopo' => uuidDe('regionais', $regional),
    ])->assertCreated();

    expect(auditoria('autorizacao.atribuicao_concedida')[0]->metadados)->toEqual([
        'acesso_alvo_id' => $alvo,
        'papel_id' => $papel,
        'permissoes' => ['pessoas.criar', 'pessoas.ver'],
        'escopo_tipo' => 'regional',
        'escopo_id' => $regional,
    ])->and((int) auditoria('autorizacao.atribuicao_concedida')[0]->acesso_id)->toBe($admin['acesso']);
});

it('nega a gestão a quem não tem a permissão no escopo da organização', function (): void {
    entrarNoPainel($this->org, $this->igrejaA, [Permissao::PapeisGerir], 'igreja', $this->igrejaA);

    $this->getJson('/api/v1/painel/papeis')->assertForbidden();
    $this->postJson('/api/v1/painel/papeis', ['nome' => 'X', 'permissoes' => ['pessoas.ver']])
        ->assertForbidden()->assertJsonPath('codigo', 'authz.denied');
});

it('nega o painel a conta sem acesso ativo na organização', function (): void {
    usarOrganizacao($this->org);
    logado();

    $this->getJson('/api/v1/painel/papeis')->assertForbidden()->assertJsonPath('codigo', 'acesso_inexistente');
});
