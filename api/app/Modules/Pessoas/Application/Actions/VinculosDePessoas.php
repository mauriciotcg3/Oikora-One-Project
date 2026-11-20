<?php

namespace App\Modules\Pessoas\Application\Actions;

use App\Modules\Pessoas\Domain\Situacao;
use App\Modules\Pessoas\Domain\Vinculo;

/** Persistência de vínculo, situação e matrícula, e o evento na linha do tempo (OIK-ADR-0005 §1). */
interface VinculosDePessoas
{
    /**
     * Lê e bloqueia a Pessoa até o fim da transação da requisição, serializando as transições.
     *
     * @return array{vinculo: Vinculo|null, situacao: Situacao, matricula: string|null, novo_convertido: bool}
     */
    public function estado(int $id): array;

    /** Grava o novo vínculo e a matrícula; mudar o vínculo desliga `novo_convertido` (RN-18). */
    public function alterarVinculo(int $id, Vinculo $vinculo, ?string $matricula): void;

    public function alterarSituacao(int $id, Situacao $situacao): void;

    /** @param  array<string, string|null>  $dados  valores codificados (de/para), nunca texto livre */
    public function registrarEvento(int $organizacaoId, int $id, string $tipo, int $motivoId, array $dados, int $autorAcessoId): void;
}
