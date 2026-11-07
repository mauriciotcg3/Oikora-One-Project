<?php

namespace App\Modules\Identidade\Application;

use DateTimeInterface;

/** Persistência de `codigos_verificacao`. "Aberto" é o código sem uso e dentro da validade. */
interface Codigos
{
    /** Cria o código e invalida os abertos da mesma conta e finalidade. */
    public function criar(
        int $contaId,
        string $finalidade,
        string $canal,
        string $hash,
        string $cifrado,
        DateTimeInterface $expiraEm,
        ?string $identificadorResumo,
    ): int;

    public function aberto(int $id): ?CodigoGuardado;

    public function abertoDaConta(int $contaId, string $finalidade): ?CodigoGuardado;

    public function abertoPorHash(string $hash, string $finalidade): ?CodigoGuardado;

    public function registrarErro(int $id): void;

    /** Marca o uso. Devolve false se outra requisição usou o código antes. */
    public function consumir(int $id): bool;

    public function registrarEnvio(int $id, string $estado, string $canal, ?string $idExterno, ?string $erro): void;
}
