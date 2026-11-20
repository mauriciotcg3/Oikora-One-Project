<?php

namespace App\Modules\Avisos\Infrastructure;

use App\Modules\Avisos\Application\CanaisDeAviso;
use App\Modules\Avisos\Application\CanalDeAviso;
use App\Modules\Avisos\Infrastructure\Canais\MetaWhatsApp\MetaWhatsAppAvisos;
use App\Modules\Avisos\Infrastructure\Canais\Postmark\PostmarkAvisos;
use InvalidArgumentException;

/** E-mail pelo Postmark e WhatsApp pelo número da organização na Meta (`oikora.avisos.provedores = real`). */
final readonly class CanaisDeAvisoReais implements CanaisDeAviso
{
    public function __construct(
        private PostmarkAvisos $email,
        private MetaWhatsAppAvisos $whatsapp,
    ) {}

    public function doCanal(string $canal): CanalDeAviso
    {
        return match ($canal) {
            'email' => $this->email,
            'whatsapp' => $this->whatsapp,
            default => throw new InvalidArgumentException('avisos.canal_desconhecido'),
        };
    }
}
