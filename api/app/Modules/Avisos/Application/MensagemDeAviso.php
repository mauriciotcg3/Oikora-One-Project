<?php

namespace App\Modules\Avisos\Application;

use App\Modules\Avisos\Domain\TipoAviso;

/**
 * O que o Adapter recebe: tipo (template), destino, link e quem assina. Nada da ficha entra aqui: o
 * texto vem do template, com o nome da organização e o link (OIK-ADR-0008 §7, §9).
 */
final readonly class MensagemDeAviso
{
    public function __construct(
        public TipoAviso $tipo,
        public string $canal,
        public string $destino,
        public ?string $link,
        public string $chaveDeIdempotencia,
        public int $organizacaoId = 0,
        public string $nomeOrganizacao = '',
        public ?string $responderPara = null,
        public ?string $linkDescadastro = null,
    ) {}
}
