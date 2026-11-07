<?php

namespace App\Modules\Identidade\Infrastructure;

use App\Modules\Identidade\Application\ContaLocalizada;
use App\Modules\Identidade\Application\Contas;
use App\Modules\Identidade\Domain\Mascara;
use Illuminate\Support\Facades\DB;

final class ContasPostgres implements Contas
{
    public function localizar(string $identificador): ?ContaLocalizada
    {
        $linha = DB::selectOne('select * from identidade_localizar_conta(?)', [$identificador]);

        return $linha === null ? null : new ContaLocalizada(
            id: (int) $linha->id,
            hashSenha: $linha->password,
            estado: $linha->estado,
            canalDoisFatores: $linha->dois_fatores_canal,
        );
    }

    public function destinoDoCodigo(int $contaId, string $canal): string
    {
        $conta = $this->daPropriaConta($contaId);

        return match (true) {
            $conta === null => '',
            $canal === 'email' && $conta->email !== null => Mascara::email($conta->email),
            $canal === 'whatsapp' && $conta->celular !== null => Mascara::celular($conta->celular),
            default => '',
        };
    }

    public function destinoDeEnvio(int $contaId, string $canal): string
    {
        $conta = $this->daPropriaConta($contaId);

        return (string) ($canal === 'email' ? $conta?->email : $conta?->celular);
    }

    public function bloqueada(int $contaId): bool
    {
        return $this->daPropriaConta($contaId)?->estado !== 'ativa';
    }

    public function canalVerificado(int $contaId, string $identificador): ?string
    {
        $conta = $this->daPropriaConta($contaId);

        return match (true) {
            $conta === null => null,
            $conta->email === $identificador && $conta->email_verificado_em !== null => 'email',
            $conta->celular === $identificador && $conta->celular_verificado_em !== null => 'whatsapp',
            default => null,
        };
    }

    public function redefinirSenha(int $contaId, string $hashSenha): void
    {
        DB::transaction(function () use ($contaId, $hashSenha): void {
            DB::select("select set_config('app.conta_id', ?, true)", [(string) $contaId]);

            Conta::query()->whereKey($contaId)->update(['password' => $hashSenha]);
            DB::table('sessions')->where('user_id', $contaId)->delete();
        });
    }

    /**
     * Lê a conta dentro de uma transação com `app.conta_id`, única forma de passar pela RLS de
     * identidade antes do contexto de organização (OIK-ADR-0011 §3).
     */
    public function daPropriaConta(int $contaId): ?Conta
    {
        return DB::transaction(function () use ($contaId): ?Conta {
            DB::select("select set_config('app.conta_id', ?, true)", [(string) $contaId]);

            return Conta::query()->find($contaId);
        });
    }
}
