<?php

namespace App\Modules\Pessoas\Application\Consultas;

/** Leitura da ficha sempre pelo filtro de alcance (PessoaEscopoAlcance). */
interface Fichas
{
    /** @return array{id: int, uuid: string, igreja_id: int}|null só se a pessoa está no alcance */
    public function localizar(string $uuid): ?array;

    /**
     * Lê só as colunas pedidas: coluna de categoria não permitida nem entra no SELECT.
     *
     * @param  list<string>  $colunas
     * @return array<string, mixed>
     */
    public function ler(int $id, array $colunas): array;

    /** @return list<array{uuid: string, tipo: string, valor: string, e_whatsapp: bool, principal: bool}> */
    public function contatos(int $id): array;

    /** @return list<array<string, string|null>> */
    public function enderecos(int $id): array;

    /**
     * Quais dos campos estão preenchidos, sem ler os valores (nem decifrar sensíveis): serve à
     * completude, que vale sobre a ficha inteira, qualquer que seja a projeção de quem vê.
     *
     * @param  list<string>  $campos  chaves de `CampoFicha`
     * @return list<string>
     */
    public function preenchidos(int $id, array $campos): array;
}
