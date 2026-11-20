<?php

namespace App\Modules\Identidade\Infrastructure\Console;

use App\Modules\Identidade\Application\Convites;
use Illuminate\Console\Command;

/** Comando diário: marca `expirado` o convite enviado há mais de 72h (RN-12; T-22). */
final class ExpirarConvites extends Command
{
    protected $signature = 'convites:expirar';

    protected $description = 'Marca como expirados os convites ao painel que passaram do prazo';

    public function handle(Convites $convites): int
    {
        $this->info("Convites expirados: {$convites->expirarVencidos()}");

        return self::SUCCESS;
    }
}
