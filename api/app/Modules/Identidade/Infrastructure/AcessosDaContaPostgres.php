<?php

namespace App\Modules\Identidade\Infrastructure;

use App\Modules\Identidade\Application\AcessosDaConta;
use App\Modules\Identidade\Application\OrganizacaoDaConta;
use Illuminate\Support\Facades\DB;

final class AcessosDaContaPostgres implements AcessosDaConta
{
    public function organizacoes(int $contaId): array
    {
        /** @var list<object{organizacao_id: int|string, organizacao_uuid: string, organizacao_nome: string}> $linhas */
        $linhas = DB::select('select * from identidade_acessos_ativos_da_conta(?)', [$contaId]);

        return array_map(
            static fn (object $linha): OrganizacaoDaConta => new OrganizacaoDaConta(
                (int) $linha->organizacao_id,
                (string) $linha->organizacao_uuid,
                (string) $linha->organizacao_nome,
            ),
            $linhas,
        );
    }
}
