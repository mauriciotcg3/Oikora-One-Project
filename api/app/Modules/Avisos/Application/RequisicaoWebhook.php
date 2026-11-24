<?php

namespace App\Modules\Avisos\Application;

final readonly class RequisicaoWebhook
{
    /**
     * @param  array<string, string|null>  $cabecalhos
     * @param  array<string, mixed>  $parametros
     */
    public function __construct(
        public string $url,
        public array $cabecalhos,
        public array $parametros,
        public string $corpo,
        public ?string $ip = null,
    ) {}
}
