<?php

namespace App\Modules\Identidade\Application;

use App\Modules\Identidade\Contracts\CodigosDeIdentidade;

/**
 * Elevação do painel (OIK-ADR-0003 §9; RN-16; CA-10): senha e, quando a conta tem 2FA, o segundo fator.
 * Administrador sem 2FA cadastrado não eleva. Não toca na sessão: isso cabe a quem recebeu a requisição.
 */
final class ElevarPainel
{
    public function __construct(
        private readonly Contas $contas,
        private readonly SenhaDaConta $senha,
        private readonly ExigenciaDeDoisFatores $exigencia,
        private readonly RegraDoCanal $regra,
        private readonly CodigosDeIdentidade $codigos,
        private readonly ConfirmarDoisFatores $confirmar,
        private readonly EventosAutenticacao $eventos,
    ) {}

    /**
     * @return string|null canal do segundo fator usado; null quando a conta não tem 2FA
     *
     * @throws FalhaDeElevacao
     */
    public function executar(int $contaId, string $senha, ?string $codigo, ?string $codigoRecuperacao, string $ip): ?string
    {
        $contaResumo = Resumo::de((string) $contaId);
        $ipResumo = Resumo::de($ip);

        if ($this->contas->bloqueada($contaId)) {
            throw FalhaDeElevacao::de('conta_bloqueada');
        }

        try {
            $senhaConfere = $this->senha->confirmar($contaId, $senha);
        } catch (FalhaDeDesafio) {
            throw FalhaDeElevacao::de('muitas_tentativas');
        }

        if (! $senhaConfere) {
            $this->eventos->registrar('auth.elevacao.falha', $contaId, $contaResumo, $ipResumo);

            throw FalhaDeElevacao::de('senha_invalida');
        }

        $canal = $this->contas->segundoFator($contaId)?->canal;

        if ($canal === null) {
            if ($this->exigencia->obrigatorio($contaId)) {
                $this->eventos->registrar('auth.elevacao.sem_dois_fatores', $contaId, $contaResumo, $ipResumo);

                throw FalhaDeElevacao::de('dois_fatores_cadastro_necessario');
            }

            return null;
        }

        if ($codigo === null && $codigoRecuperacao === null) {
            throw $this->pedirSegundoFator($contaId, $canal);
        }

        try {
            $this->confirmar->executar($contaId, $codigo, $codigoRecuperacao, $contaResumo, $ip);
        } catch (FalhaDeDesafio $falha) {
            throw FalhaDeElevacao::de($falha->motivo === 'codigo_incorreto' ? 'codigo_invalido' : $falha->motivo);
        }

        return $codigoRecuperacao !== null ? 'recuperacao' : $canal;
    }

    private function pedirSegundoFator(int $contaId, string $preferido): FalhaDeElevacao
    {
        $canal = $this->regra->escolher($contaId, $preferido);

        if ($canal === null) {
            return FalhaDeElevacao::de('canal_indisponivel');
        }

        if ($canal === 'totp') {
            return FalhaDeElevacao::doisFatoresNecessario('totp', null);
        }

        $this->codigos->emitir($contaId, EmissaoDeCodigos::DOIS_FATORES, $canal);

        return FalhaDeElevacao::doisFatoresNecessario($canal, $this->contas->destinoDoCodigo($contaId, $canal));
    }
}
