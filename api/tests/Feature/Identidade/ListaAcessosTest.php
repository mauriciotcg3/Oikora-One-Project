<?php

use App\Modules\Autorizacao\Domain\Permissao;

/** `GET /painel/acessos` (plan.md §7), base da tela de acessos e convites (T-40). */
beforeEach(function (): void {
    $this->org = criarOrganizacao('Acessos');
    $this->igreja = criarIgreja($this->org, 'Sede');
});

it('lista os acessos com a Pessoa e os papéis, e os convites válidos, sem contato', function (): void {
    $admin = entrarNoPainel($this->org, $this->igreja, [Permissao::AcessosGerir], sistema: 'administrador');
    $pessoa = criarPessoa($this->org, $this->igreja, ['nome' => 'Convidada']);
    criarContato($this->org, $pessoa, 'email', 'convidada@exemplo.test');
    $revogada = criarPessoa($this->org, $this->igreja, ['nome' => 'Antiga']);
    criarAcesso($this->org, criarConta(['email' => 'antiga@exemplo.test', 'celular' => '+5511933332222']), $revogada, 'revogado');
    inserirComoDono('convites', [
        'organizacao_id' => $this->org, 'pessoa_id' => $pessoa, 'papel_id' => $admin['papel'], 'escopo_tipo' => 'organizacao',
        'token_hash' => hash('sha256', 'x'), 'expira_em' => now()->addDay(), 'estado' => 'enviado', 'emitido_por_acesso_id' => $admin['acesso'],
    ]);

    $resposta = $this->getJson('/api/v1/painel/acessos')->assertOk();

    expect($resposta->json('data'))->toHaveCount(2)
        ->and($resposta->json('data.0.estado'))->toBe('ativo')
        ->and($resposta->json('data.0.papeis.0.nome'))->toBe('Administrador')
        ->and($resposta->json('data.0.meta.acoes'))->toBe(['revogar'])
        ->and($resposta->json('data.1.estado'))->toBe('revogado')
        ->and($resposta->json('data.1.meta.acoes'))->toBe([])
        ->and($resposta->json('convites.0.pessoa.nome'))->toBe('Convidada')
        ->and(json_encode($resposta->json()))->not->toContain('convidada@exemplo.test');
});

it('sem acessos.gerir na organização → 403', function (): void {
    entrarNoPainel($this->org, $this->igreja, [Permissao::AcessosGerir], 'igreja', $this->igreja);

    $this->getJson('/api/v1/painel/acessos')->assertForbidden();
});
