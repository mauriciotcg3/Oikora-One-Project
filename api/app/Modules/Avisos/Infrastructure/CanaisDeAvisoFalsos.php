<?php

namespace App\Modules\Avisos\Infrastructure;

use App\Modules\Avisos\Application\CanaisDeAviso;
use App\Modules\Avisos\Application\CanalDeAviso;
use App\Modules\Avisos\Infrastructure\Canais\MetaWhatsApp\MetaWhatsAppFalso;
use App\Modules\Avisos\Infrastructure\Canais\Postmark\PostmarkFalso;
use InvalidArgumentException;

/** Sem provedor real ainda: e-mail pelo Postmark falso, WhatsApp pelo Meta falso (OIK-ADR-0010 §2). */
final readonly class CanaisDeAvisoFalsos implements CanaisDeAviso
{
    public function __construct(
        private PostmarkFalso $email,
        private MetaWhatsAppFalso $whatsapp,
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
