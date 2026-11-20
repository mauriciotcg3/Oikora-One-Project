<?php

use App\Modules\Pessoas\Domain\TransicaoVinculoInvalida;
use App\Modules\Pessoas\Domain\Vinculo;

/** OIK-SPEC-0001 T-28 — máquina de estados do vínculo (RN-07; CA-06). */
it('avança um passo por vez: Visitante → Frequentador → Membro', function (): void {
    expect(Vinculo::Visitante->avancarPara(Vinculo::Frequentador))->toBe(Vinculo::Frequentador)
        ->and(Vinculo::Frequentador->avancarPara(Vinculo::Membro))->toBe(Vinculo::Membro)
        ->and(Vinculo::Membro->proximo())->toBeNull();
});

it('recusa pular de Visitante para Membro, dizendo que precisa passar por Frequentador', function (): void {
    try {
        Vinculo::Visitante->avancarPara(Vinculo::Membro);
        $this->fail('Deveria recusar.');
    } catch (TransicaoVinculoInvalida $recusa) {
        expect($recusa->getMessage())->toContain('precisa passar por Frequentador')
            ->and($recusa->proximo)->toBe(Vinculo::Frequentador);
    }
});

it('recusa voltar de etapa ou repetir o vínculo', function (Vinculo $de, Vinculo $para, string $mensagem): void {
    expect(fn () => $de->avancarPara($para))->toThrow(TransicaoVinculoInvalida::class, $mensagem);
})->with([
    'membro para frequentador' => [Vinculo::Membro, Vinculo::Frequentador, 'não volta de etapa'],
    'frequentador para visitante' => [Vinculo::Frequentador, Vinculo::Visitante, 'não volta de etapa'],
    'membro para visitante' => [Vinculo::Membro, Vinculo::Visitante, 'não volta de etapa'],
    'o mesmo vínculo' => [Vinculo::Frequentador, Vinculo::Frequentador, 'já tem esse vínculo'],
]);

it('só o membro tem matrícula', function (): void {
    expect(Vinculo::Membro->temMatricula())->toBeTrue()
        ->and(Vinculo::Frequentador->temMatricula())->toBeFalse()
        ->and(Vinculo::Visitante->temMatricula())->toBeFalse();
});
