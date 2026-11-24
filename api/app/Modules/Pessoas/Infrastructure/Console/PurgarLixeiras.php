<?php

namespace App\Modules\Pessoas\Infrastructure\Console;

use App\Modules\Organizacao\Contracts\ConsultaOrganizacoes;
use App\Modules\Pessoas\Application\Jobs\PurgarLixeira;
use Illuminate\Console\Command;

/** `pessoas:purgar-lixeira`, diário: um job por organização, cada um com o próprio contexto (OIK-ADR-0008 §5). */
final class PurgarLixeiras extends Command
{
    protected $signature = 'pessoas:purgar-lixeira';

    protected $description = 'Distribui a purga da lixeira de Pessoas por organização';

    public function handle(ConsultaOrganizacoes $organizacoes): int
    {
        $ids = $organizacoes->idsDisponiveis();
        foreach ($ids as $organizacaoId) {
            PurgarLixeira::dispatch($organizacaoId);
        }
        $this->info(count($ids).' organização(ões) na fila.');

        return self::SUCCESS;
    }
}
