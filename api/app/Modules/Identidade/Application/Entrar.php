<?php

namespace App\Modules\Identidade\Application;

use App\Modules\Identidade\Domain\Identificador;
use Illuminate\Contracts\Hashing\Hasher;

/** Confere identificador e senha, sem abrir sessão: isso cabe a quem recebeu a requisição. */
final class Entrar
{
    public function __construct(
        private readonly Contas $contas,
        private readonly EventosAutenticacao $eventos,
        private readonly LimiteDeTentativas $limite,
        private readonly Hasher $senhas,
    ) {}

    /**
     * @return int id da conta autenticada
     *
     * @throws FalhaDeLogin
     */
    public function executar(string $identificador, string $senha, string $ip): int
    {
        $normalizado = Identificador::normalizar($identificador);
        $identificadorResumo = $this->resumir($normalizado);
        $ipResumo = $this->resumir($ip);

        $espera = $this->limite->segundosDeBloqueio($identificadorResumo, $ipResumo);
        if ($espera > 0) {
            $this->eventos->registrar('auth.login.limite', null, $identificadorResumo, $ipResumo);

            throw FalhaDeLogin::muitasTentativas($espera);
        }

        $conta = $this->contas->localizar($normalizado);

        if (! $this->senhaConfere($conta, $senha)) {
            $this->limite->registrarFalha($identificadorResumo, $ipResumo);
            $this->eventos->registrar('auth.login.falha', $conta?->id, $identificadorResumo, $ipResumo);

            throw FalhaDeLogin::credenciais();
        }

        /** @var ContaLocalizada $conta */
        if ($conta->bloqueada()) {
            $this->eventos->registrar('auth.login.conta_bloqueada', $conta->id, $identificadorResumo, $ipResumo);

            throw FalhaDeLogin::contaBloqueada();
        }

        $this->limite->liberarIdentificador($identificadorResumo);

        // A senha conferiu, mas a sessão só abre depois do segundo fator (T-12).
        if ($conta->canalDoisFatores !== null) {
            $canal = $conta->canalDoisFatores;
            $this->eventos->registrar('auth.login.dois_fatores_pendente', $conta->id, $identificadorResumo, $ipResumo, $canal);

            throw FalhaDeLogin::doisFatores($canal, $this->contas->destinoDoCodigo($conta->id, $canal));
        }

        $this->eventos->registrar('auth.login.sucesso', $conta->id, $identificadorResumo, $ipResumo);

        return $conta->id;
    }

    private function senhaConfere(?ContaLocalizada $conta, string $senha): bool
    {
        if ($conta === null) {
            // Gasta o mesmo tempo de uma conferência real: o tempo de resposta não revela se a conta existe.
            $this->senhas->make($senha);

            return false;
        }

        return $this->senhas->check($senha, $conta->hashSenha);
    }

    private function resumir(string $valor): string
    {
        return hash_hmac('sha256', $valor, (string) config('oikora.chave_resumo'));
    }
}
