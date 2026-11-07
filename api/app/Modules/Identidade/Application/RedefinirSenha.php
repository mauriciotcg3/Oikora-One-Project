<?php

namespace App\Modules\Identidade\Application;

use Illuminate\Contracts\Hashing\Hasher;

/** Troca a senha pelo token da recuperação e encerra todas as sessões da conta (OIK-ADR-0003 §8). */
final class RedefinirSenha
{
    public function __construct(
        private readonly Codigos $codigos,
        private readonly Contas $contas,
        private readonly EventosAutenticacao $eventos,
        private readonly Hasher $senhas,
    ) {}

    /** @return bool false quando o token não vale: inventado, usado ou vencido */
    public function executar(string $token, string $senha, string $ip): bool
    {
        $guardado = $this->codigos->abertoPorHash(Resumo::de($token), EmissaoDeCodigos::RECUPERAR_SENHA);

        if ($guardado === null || ! $this->codigos->consumir($guardado->id)) {
            return false;
        }

        $this->contas->redefinirSenha($guardado->contaId, $this->senhas->make($senha));

        $this->eventos->registrar(
            'auth.senha.redefinida',
            $guardado->contaId,
            $guardado->identificadorResumo ?? Resumo::de((string) $guardado->contaId),
            Resumo::de($ip),
            $guardado->canal,
        );

        return true;
    }
}
