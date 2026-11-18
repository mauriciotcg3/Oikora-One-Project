<?php

namespace App\Modules\Pessoas\Application\Consultas;

use App\Modules\Pessoas\Domain\Completude;

/** Ficha já projetada: só o que quem consulta pode ver, mais a completude e a foto por URL assinada. */
final readonly class Ficha
{
    /**
     * @param  array{uuid: string, nome: string}|null  $igreja
     * @param  array<string, mixed>  $campos
     * @param  list<array<string, mixed>>|null  $contatos  null quando a categoria `contatos` não é visível
     * @param  list<array<string, mixed>>|null  $enderecos
     * @param  list<string>  $categorias  categorias sensíveis visíveis
     * @param  list<string>  $acoes
     */
    public function __construct(
        public string $uuid,
        public ?array $igreja,
        public ?string $vinculo,
        public ?string $situacao,
        public array $campos,
        public ?array $contatos,
        public ?array $enderecos,
        public array $categorias,
        public array $acoes,
        public ?Completude $completude = null,
        public ?string $fotoUrl = null,
    ) {}
}
