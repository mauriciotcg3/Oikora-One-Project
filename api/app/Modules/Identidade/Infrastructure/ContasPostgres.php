<?php

namespace App\Modules\Identidade\Infrastructure;

use App\Modules\Identidade\Application\ContaLocalizada;
use App\Modules\Identidade\Application\Contas;
use App\Modules\Identidade\Application\SegundoFator;
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

    public function contatoVerificado(int $contaId, string $canal): bool
    {
        $conta = $this->daPropriaConta($contaId);

        return ($canal === 'email' ? $conta?->email_verificado_em : $conta?->celular_verificado_em) !== null;
    }

    public function marcarVerificado(int $contaId, string $canal): void
    {
        $coluna = $canal === 'email' ? 'email_verificado_em' : 'celular_verificado_em';

        $this->atualizar($contaId, [$coluna => now()]);
    }

    public function hashDaSenha(int $contaId): string
    {
        return (string) $this->daPropriaConta($contaId)?->password;
    }

    public function segundoFator(int $contaId): ?SegundoFator
    {
        $conta = $this->daPropriaConta($contaId);

        return $conta === null ? null : new SegundoFator(
            canal: $conta->dois_fatores_canal,
            segredoCifrado: $conta->two_factor_secret,
            recuperacao: $this->resumosDe($conta),
        );
    }

    public function guardarSegredoTotp(int $contaId, string $segredoCifrado): void
    {
        $this->atualizar($contaId, ['two_factor_secret' => $segredoCifrado, 'two_factor_confirmed_at' => null]);
    }

    public function ativarDoisFatores(int $contaId, string $canal, array $resumosDeRecuperacao): void
    {
        $totp = $canal === 'totp';

        $this->atualizar($contaId, [
            'dois_fatores_canal' => $canal,
            'two_factor_recovery_codes' => json_encode($resumosDeRecuperacao),
            'two_factor_confirmed_at' => $totp ? now() : null,
            ...($totp ? [] : ['two_factor_secret' => null]),
        ]);
    }

    public function desativarDoisFatores(int $contaId): void
    {
        $this->atualizar($contaId, [
            'dois_fatores_canal' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
    }

    public function consumirCodigoDeRecuperacao(int $contaId, string $resumo): bool
    {
        return DB::transaction(function () use ($contaId, $resumo): bool {
            DB::select("select set_config('app.conta_id', ?, true)", [(string) $contaId]);

            // A trava impede que duas requisições gastem o mesmo código ao mesmo tempo.
            $conta = Conta::query()->whereKey($contaId)->lockForUpdate()->first();
            $resumos = $conta === null ? [] : $this->resumosDe($conta);

            if (! in_array($resumo, $resumos, true)) {
                return false;
            }

            $restantes = array_values(array_diff($resumos, [$resumo]));
            Conta::query()->whereKey($contaId)->update(['two_factor_recovery_codes' => json_encode($restantes)]);

            return true;
        });
    }

    public function redefinirSenha(int $contaId, string $hashSenha): void
    {
        DB::transaction(function () use ($contaId, $hashSenha): void {
            $this->atualizar($contaId, ['password' => $hashSenha]);
            DB::table('sessions')->where('user_id', $contaId)->delete();
        });
    }

    /** @param array<string, mixed> $valores */
    private function atualizar(int $contaId, array $valores): void
    {
        DB::transaction(function () use ($contaId, $valores): void {
            DB::select("select set_config('app.conta_id', ?, true)", [(string) $contaId]);

            Conta::query()->whereKey($contaId)->update($valores);
        });
    }

    /** @return list<string> */
    private function resumosDe(Conta $conta): array
    {
        /** @var list<string> $resumos */
        $resumos = json_decode((string) $conta->two_factor_recovery_codes, true) ?: [];

        return $resumos;
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
