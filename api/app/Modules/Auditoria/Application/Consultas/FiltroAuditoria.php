<?php

namespace App\Modules\Auditoria\Application\Consultas;

use DateTimeImmutable;

/** Filtros da consulta, já validados e resolvidos para ids internos pela borda. */
final readonly class FiltroAuditoria
{
    public function __construct(
        public ?DateTimeImmutable $de = null,
        public ?DateTimeImmutable $ate = null,
        public ?string $evento = null,
        public ?int $atorAcessoId = null,
        public ?int $igrejaId = null,
        public ?string $alvoTipo = null,
        public ?string $alvoId = null,
        public ?string $busca = null,
        public ?string $resultado = null,
        public int $pagina = 1,
        public int $porPagina = 25,
    ) {}
}
