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

    /** Id interno da pessoa da organização, dentro ou fora do alcance (para gravar pares, T-26). */
    public function idPorUuid(string $uuid): ?int;

    /** O CPF já pertence a alguém da organização, inclusive na lixeira (RN-01). */
    public function cpfEmUso(string $cpf): bool;

    /**
     * Possíveis duplicados (RN-23; U-20), inclusive na lixeira, sem os pares marcados como "é outra pessoa"
     * com `$pessoaId`. Só projeção restrita e os motivos (booleanos calculados no banco).
     *
     * @return list<array{uuid: string, nome: string, igreja_id: int, vinculo: string|null, na_lixeira: bool, motivos: list<string>}>
     */
    public function duplicados(CriteriosDuplicidade $criterios, ?int $pessoaId = null, int $limite = 10): array;
}
