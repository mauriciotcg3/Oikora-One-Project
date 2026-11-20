<?php

namespace App\Shared\Arquivos;

use DateTimeImmutable;

/** URL assinada de envio, com o caminho que o arquivo terá e quando a URL deixa de valer. */
final readonly class UrlDeEnvio
{
    /** @param  array<string, string>  $cabecalhos */
    public function __construct(
        public string $url,
        public array $cabecalhos,
        public string $caminho,
        public DateTimeImmutable $expiraEm,
    ) {}
}
