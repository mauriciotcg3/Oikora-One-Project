<?php

namespace App\Modules\Jornadas\Infrastructure\Console;

use App\Modules\Jornadas\Infrastructure\Jobs\ExpirarRascunhosJornada;
use App\Modules\Organizacao\Contracts\ConsultaOrganizacoes;
use Illuminate\Console\Command;

final class ExpirarRascunhos extends Command
{
    protected $signature = 'jornadas:expirar-rascunhos';

    protected $description = 'Distribui a expiração dos rascunhos por organização';

    public function handle(ConsultaOrganizacoes $organizacoes): int
    {
        $ids = $organizacoes->idsDisponiveis();
        foreach ($ids as $organizacaoId) {
            ExpirarRascunhosJornada::dispatch($organizacaoId);
        }
        $this->info(count($ids).' organização(ões) na fila.');

        return self::SUCCESS;
    }
}
