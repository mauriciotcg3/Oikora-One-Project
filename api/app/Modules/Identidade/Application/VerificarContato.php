<?php

namespace App\Modules\Identidade\Application;

use App\Modules\Identidade\Contracts\CodigosDeIdentidade;
use Illuminate\Cache\RateLimiter;

/** Prova que a pessoa recebe mensagens no e-mail ou no celular da conta (OIK-ADR-0003 §4). */
final class VerificarContato
{
    private const CANAIS = ['email' => 'email', 'celular' => 'whatsapp'];

    public function __construct(
        private readonly Contas $contas,
        private readonly CodigosDeIdentidade $codigos,
        private readonly ConferirCodigo $conferir,
        private readonly EventosAutenticacao $eventos,
        private readonly RateLimiter $limitador,
    ) {}

    /**
     * @return bool false quando a conta não tem esse contato
     *
     * @throws FalhaDeDesafio
     */
    public function pedir(int $contaId, string $tipo): bool
    {
        $canal = self::CANAIS[$tipo];

        if ($this->contas->destinoDeEnvio($contaId, $canal) === '') {
            return false;
        }

        if ($this->contas->contatoVerificado($contaId, $canal)) {
            return true;
        }

        $chave = 'identidade:verificacao:'.$tipo.':'.$contaId;

        if ($this->limitador->tooManyAttempts($chave, (int) config('oikora.verificacao.pedidos'))) {
            throw FalhaDeDesafio::muitasTentativas();
        }

        $this->limitador->hit($chave, (int) config('oikora.verificacao.janela_segundos'));
        $this->codigos->emitir($contaId, 'verificar_'.$tipo, $canal);

        return true;
    }

    /** @throws FalhaDeDesafio */
    public function confirmar(int $contaId, string $tipo, string $codigo, string $ip): void
    {
        $guardado = $this->conferir->executar($contaId, 'verificar_'.$tipo, $codigo);

        $this->contas->marcarVerificado($contaId, self::CANAIS[$tipo]);
        $this->eventos->registrar('auth.contato.verificado', $contaId, Resumo::de((string) $contaId), Resumo::de($ip), $guardado->canal);
    }
}
