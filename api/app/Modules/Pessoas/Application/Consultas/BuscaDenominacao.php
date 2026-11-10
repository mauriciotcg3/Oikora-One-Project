<?php

namespace App\Modules\Pessoas\Application\Consultas;

/**
 * A única consulta que sai do filtro de alcance (OIK-ADR-0004 §6; RN-09; CA-20): busca por nome, CPF
 * exato ou contato na organização inteira e devolve só identificador público, nome, igreja e vínculo,
 * escolhidos no SELECT. Ainda exige ator no contexto (fail-closed) e respeita a RLS.
 */
interface BuscaDenominacao
{
    /** @return list<array{uuid: string, nome: string, igreja_id: int, vinculo: string|null}> */
    public function buscar(string $termo, int $limite = 20): array;

    /** A pessoa existe na organização, dentro ou fora do alcance de quem pergunta. */
    public function existe(string $uuid): bool;
}
