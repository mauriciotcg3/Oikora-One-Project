<?php

use App\Modules\Auditoria\Application\Auditoria;
use App\Modules\Auditoria\Domain\CodigoFalhaAuditoria;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;

it('registra somente nomes de campos e metadados catalogados na mesma transação', function (): void {
    $organizacaoId = criarOrganizacao('Auditada');

    noContexto($organizacaoId, null, fn () => app(Auditoria::class)->registrar(
        EventoAuditoria::ExportacaoConcluida,
        $organizacaoId,
        'exportacao',
        12,
        campos: ['nome', 'situacao'],
        metadados: ['categorias' => ['cadastro', 'contatos'], 'contagem' => 4],
        canal: 'job',
        requestId: 'req-123',
    ));

    $linha = DB::connection('pgsql_dono')->table('auditoria')->first();

    expect($linha->evento)->toBe('exportacao.concluida')
        ->and(json_decode($linha->campos, true))->toBe(['nome', 'situacao'])
        ->and(json_decode($linha->metadados, true))->toMatchArray(['categorias' => ['cadastro', 'contatos'], 'contagem' => 4])
        ->and($linha->request_id)->toBe('req-123');
});

it('recusa metadado fora do catálogo e texto livre', function (array $metadados): void {
    $organizacaoId = criarOrganizacao('Metadados');
    noContexto($organizacaoId, null, fn () => app(Auditoria::class)->registrar(
        EventoAuditoria::ExportacaoNegada,
        $organizacaoId,
        'exportacao',
        1,
        metadados: $metadados,
    ));
})->with([
    'chave desconhecida' => [['observacao' => 'codigo']],
    'texto livre' => [['motivo_codigo' => 'porque a pessoa pediu']],
])->throws(InvalidArgumentException::class);

it('desfaz o registro quando a ação falha', function (): void {
    $organizacaoId = criarOrganizacao('Rollback');

    try {
        noContexto($organizacaoId, null, function () use ($organizacaoId): void {
            app(Auditoria::class)->registrar(EventoAuditoria::PessoaCriada, $organizacaoId, 'pessoa', 99);
            throw new RuntimeException('falha simulada');
        });
    } catch (RuntimeException) {
        // A asserção abaixo comprova o rollback da mesma transação.
    }

    expect(DB::connection('pgsql_dono')->table('auditoria')->count())->toBe(0);
});

it('registra a tentativa que falhou somente depois do rollback da transação de negócio', function (): void {
    $organizacaoId = criarOrganizacao('Falha auditada');
    Context::add('request_id', 'req-falha');
    Context::add('trace_id', '4bf92f3577b34da6a3ce929d0e0e4736');

    try {
        noContexto($organizacaoId, null, function () use ($organizacaoId): void {
            app(Auditoria::class)->registrar(EventoAuditoria::PessoaCriada, $organizacaoId, 'pessoa', 99);
            throw new RuntimeException('falha simulada');
        });
    } catch (RuntimeException) {
        $registrada = app(Auditoria::class)->tentarRegistrarFalha(
            EventoAuditoria::PessoaCriada,
            CodigoFalhaAuditoria::ExecucaoInesperada,
            $organizacaoId,
            'pessoa',
            99,
        );
    }

    $linha = DB::connection('pgsql_dono')->table('auditoria')->sole();
    expect($registrada ?? false)->toBeTrue()
        ->and($linha->resultado)->toBe('falha')
        ->and($linha->codigo_falha)->toBe('execucao_inesperada')
        ->and($linha->request_id)->toBe('req-falha')
        ->and($linha->trace_id)->toBe('4bf92f3577b34da6a3ce929d0e0e4736');
});

it('não troca a falha original quando a própria auditoria da falha é recusada', function (): void {
    $organizacaoId = criarOrganizacao('Falha preservada');

    $registrada = app(Auditoria::class)->tentarRegistrarFalha(
        EventoAuditoria::PessoaCriada,
        CodigoFalhaAuditoria::RegraNegocio,
        $organizacaoId,
        'pessoa',
        99,
        metadados: ['segredo' => 'nunca'],
    );

    expect($registrada)->toBeFalse()
        ->and(DB::connection('pgsql_dono')->table('auditoria')->count())->toBe(0);
});
