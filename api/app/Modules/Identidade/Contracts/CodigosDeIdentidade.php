<?php

namespace App\Modules\Identidade\Contracts;

/** Códigos de identidade: Identidade decide e guarda; Avisos só entrega e devolve o resultado. */
interface CodigosDeIdentidade
{
    /**
     * Cria o código, invalida o anterior da mesma finalidade e pede a entrega.
     *
     * @return int id em `codigos_verificacao`
     */
    public function emitir(int $contaId, string $finalidade, string $canal, ?string $identificadorResumo = null): int;

    /** Devolve null quando não há o que entregar: já foi entregue, foi usado ou venceu. */
    public function paraEnvio(int $codigoId): ?CodigoParaEnvio;

    public function registrarEnvio(int $codigoId, string $estado, string $canal, ?string $idExterno = null, ?string $erro = null): void;
}
