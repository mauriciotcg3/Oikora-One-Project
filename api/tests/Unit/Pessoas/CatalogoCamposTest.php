<?php

use App\Modules\Pessoas\Domain\CampoFicha;

function valorDoCatalogo(string|BackedEnum $valor): string
{
    return $valor instanceof BackedEnum ? (string) $valor->value : $valor;
}

it('classifica todo campo da ficha em uma categoria e seção aprovadas', function (): void {
    $categorias = ['geral', 'saude', 'documentos', 'contatos', 'fe'];
    $secoes = ['pessoais', 'documentos', 'contatos', 'endereco', 'saude', 'eclesiasticos', 'adicionais'];

    foreach (CampoFicha::cases() as $campo) {
        expect(valorDoCatalogo($campo->categoria()))->toBeIn($categorias)
            ->and(valorDoCatalogo($campo->secao()))->toBeIn($secoes);
    }
});

it('mantém as categorias sensíveis alinhadas aos dados funcionais', function (): void {
    $porCategoria = collect(CampoFicha::cases())
        ->groupBy(fn (CampoFicha $campo): string => valorDoCatalogo($campo->categoria()))
        ->map(fn ($campos) => $campos->pluck('value')->sort()->values()->all());

    expect($porCategoria->get('documentos'))->toBe(['cpf', 'rg', 'rg_pais'])
        ->and($porCategoria->get('saude'))->toBe(['necessidades_especiais', 'tipo_sanguineo'])
        ->and($porCategoria->get('contatos'))->toBe([
            'bairro',
            'celular',
            'cep',
            'cidade',
            'complemento',
            'e_whatsapp',
            'email',
            'estado',
            'logradouro',
            'numero',
            'pais',
            'telefone',
        ])
        ->and($porCategoria->get('fe'))->toBe([
            'aceitou_jesus',
            'batismo_data',
            'batizado_por',
            'conversao_canal',
            'conversao_data',
            'e_lider',
            'e_pastor',
            'igreja_origem',
            'novo_convertido',
        ]);
});
