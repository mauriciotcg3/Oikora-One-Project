<?php

use App\Modules\Auditoria\Application\Auditoria;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('impede a aplicação de atualizar ou excluir auditoria', function (string $operacao): void {
    $organizacaoId = criarOrganizacao('Imutável');
    noContexto($organizacaoId, null, fn () => app(Auditoria::class)
        ->registrar(EventoAuditoria::PessoaCriada, $organizacaoId, 'pessoa', 1));

    noContexto($organizacaoId, null, fn () => $operacao === 'update'
        ? DB::table('auditoria')->update(['canal' => 'violado'])
        : DB::table('auditoria')->delete());
})->with(['update', 'delete'])->throws(QueryException::class, 'permission denied');
