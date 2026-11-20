<?php

use App\Modules\Pessoas\Domain\Completude;

/** OIK-SPEC-0001 T-27 — completude pelos obrigatórios do vínculo (CA-14; U-05). */
it('dá 50% ao membro com só o nascimento quando os obrigatórios são nascimento e CEP', function (): void {
    $completude = Completude::calcular(['nascimento', 'cep'], ['nascimento', 'nome']);

    expect($completude->percentual)->toBe(50)
        ->and($completude->faltando)->toBe(['cep']);
});

it('dá 100% com a lista de obrigatórios vazia', function (): void {
    $completude = Completude::calcular([], []);

    expect($completude->percentual)->toBe(100)
        ->and($completude->faltando)->toBe([]);
});

it('arredonda para baixo, sem dar 100% antes de completar', function (): void {
    expect(Completude::calcular(['nascimento', 'cep', 'cpf'], ['nascimento', 'cep'])->percentual)->toBe(66)
        ->and(Completude::calcular(['nascimento', 'cep', 'cpf'], ['nascimento', 'cep', 'cpf'])->percentual)->toBe(100);
});
