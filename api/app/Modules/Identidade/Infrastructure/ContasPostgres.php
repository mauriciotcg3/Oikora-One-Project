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
