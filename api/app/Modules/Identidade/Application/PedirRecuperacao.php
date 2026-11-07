<?php

namespace App\Modules\Identidade\Application;

use App\Modules\Identidade\Contracts\CodigosDeIdentidade;
use App\Modules\Identidade\Domain\Identificador;
use Illuminate\Cache\RateLimiter;

/** Aceita o pedido da mesma forma exista ou não a conta (OIK-ADR-0003 §4 e §8). */
final class PedirRecuperacao
{
    public function __construct(
        private readonly Contas $contas,
        private readonly CodigosDeIdentidade $codigos,
        private readonly EventosAutenticacao $eventos,
        private readonly RateLimiter $limitador,
    ) {}

    /** @return bool false quando passou do limite de pedidos */
    public function executar(string $identificador, string $ip): bool
    {
        $normalizado = Identificador::normalizar($identificador);
        $identificadorResumo = Resumo::de($normalizado);
        $ipResumo = Resumo::de($ip);

        if (! $this->dentroDoLimite($identificadorResumo, $ipResumo)) {
            $this->eventos->registrar('auth.senha.recuperacao_limite', null, $identificadorResumo, $ipResumo);

            return false;
        }

        $conta = $this->contas->localizar($normalizado);
        $canal = $conta === null ? null : $this->contas->canalVerificado($conta->id, $normalizado);

        if ($conta !== null && $canal !== null) {
            $this->codigos->emitir($conta->id, EmissaoDeCodigos::RECUPERAR_SENHA, $canal, $identificadorResumo);
        }

        $this->eventos->registrar('auth.senha.recuperacao_pedida', $conta?->id, $identificadorResumo, $ipResumo, $canal);

        return true;
    }

    private function dentroDoLimite(string $identificadorResumo, string $ipResumo): bool
    {
        $janela = (int) config('oikora.recuperacao.janela_segundos');
        $porIdentificador = 'recuperacao:identificador:'.$identificadorResumo;
        $porIp = 'recuperacao:ip:'.$ipResumo;

        if ($this->limitador->tooManyAttempts($porIdentificador, (int) config('oikora.recuperacao.pedidos_por_identificador'))
            || $this->limitador->tooManyAttempts($porIp, (int) config('oikora.recuperacao.pedidos_por_ip'))) {
            return false;
        }

        $this->limitador->hit($porIdentificador, $janela);
        $this->limitador->hit($porIp, $janela);

        return true;
    }
}
