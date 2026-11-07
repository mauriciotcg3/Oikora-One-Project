<?php

namespace App\Modules\Identidade\Infrastructure;

use App\Modules\Identidade\Application\CodigoGuardado;
use App\Modules\Identidade\Application\Codigos;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

final class CodigosPostgres implements Codigos
{
    public function criar(
        int $contaId,
        string $finalidade,
        string $canal,
        string $hash,
        string $cifrado,
        DateTimeInterface $expiraEm,
        ?string $identificadorResumo,
    ): int {
        return DB::transaction(function () use ($contaId, $finalidade, $canal, $hash, $cifrado, $expiraEm, $identificadorResumo): int {
            $this->abertos()
                ->where('conta_id', $contaId)
                ->where('finalidade', $finalidade)
                ->update(['usado_em' => now(), 'codigo_cifrado' => null, 'updated_at' => now()]);

            return (int) DB::table('codigos_verificacao')->insertGetId([
                'conta_id' => $contaId,
                'identificador_hash' => $identificadorResumo,
                'finalidade' => $finalidade,
                'codigo_hash' => $hash,
                'codigo_cifrado' => $cifrado,
                'canal' => $canal,
                'expira_em' => $expiraEm,
                'envio_estado' => 'pendente',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function aberto(int $id): ?CodigoGuardado
    {
        return $this->paraGuardado($this->abertos()->where('id', $id)->first());
    }

    public function abertoDaConta(int $contaId, string $finalidade): ?CodigoGuardado
    {
        return $this->paraGuardado(
            $this->abertos()->where('conta_id', $contaId)->where('finalidade', $finalidade)->orderByDesc('id')->first(),
        );
    }

    public function abertoPorHash(string $hash, string $finalidade): ?CodigoGuardado
    {
        return $this->paraGuardado($this->abertos()->where('codigo_hash', $hash)->where('finalidade', $finalidade)->first());
    }

    public function registrarErro(int $id): void
    {
        DB::table('codigos_verificacao')->where('id', $id)->increment('tentativas');
    }

    public function consumir(int $id): bool
    {
        return $this->abertos()->where('id', $id)
            ->update(['usado_em' => now(), 'codigo_cifrado' => null, 'updated_at' => now()]) === 1;
    }

    public function registrarEnvio(int $id, string $estado, string $canal, ?string $idExterno, ?string $erro): void
    {
        DB::table('codigos_verificacao')->where('id', $id)->update([
            'envio_estado' => $estado,
            'canal' => $canal,
            'envio_id_externo' => $idExterno,
            'envio_erro' => $erro,
            'codigo_cifrado' => null,
            'updated_at' => now(),
        ]);
    }

    /** A hora vem da aplicação, não do banco, para a validade seguir o mesmo relógio da emissão. */
    private function abertos(): Builder
    {
        return DB::table('codigos_verificacao')->whereNull('usado_em')->where('expira_em', '>', now());
    }

    private function paraGuardado(?stdClass $linha): ?CodigoGuardado
    {
        return $linha === null ? null : new CodigoGuardado(
            id: (int) $linha->id,
            contaId: (int) $linha->conta_id,
            finalidade: $linha->finalidade,
            hash: $linha->codigo_hash,
            canal: $linha->canal,
            tentativas: (int) $linha->tentativas,
            cifrado: $linha->codigo_cifrado,
            identificadorResumo: $linha->identificador_hash,
        );
    }
}
