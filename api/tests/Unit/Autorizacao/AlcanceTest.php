<?php

use App\Modules\Autorizacao\Application\Alcance;
use App\Modules\Autorizacao\Application\AtribuicoesDePermissao;
use App\Modules\Autorizacao\Application\PoliticaDePermissao;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Organizacao\Contracts\CatalogoIgrejas;

it('expande organização e regional e soma atribuições de igreja', function (): void {
    $atribuicoes = new class implements AtribuicoesDePermissao
    {
        public function doAcesso(int $organizacaoId, int $acessoId): array
        {
            return [
                ['permissao' => Permissao::PessoasVer->value, 'escopo_tipo' => 'organizacao', 'escopo_id' => null],
                ['permissao' => Permissao::PessoasEditar->value, 'escopo_tipo' => 'regional', 'escopo_id' => 10],
                ['permissao' => Permissao::PessoasEditar->value, 'escopo_tipo' => 'igreja', 'escopo_id' => 3],
            ];
        }
    };
    $igrejas = new class implements CatalogoIgrejas
    {
        public function idsDaOrganizacao(int $organizacaoId): array
        {
            return [1, 2, 3];
        }

        public function idsDaRegional(int $organizacaoId, int $regionalId): array
        {
            return [1, 2];
        }

        public function pertenceAOrganizacao(int $organizacaoId, int $igrejaId): bool
        {
            return in_array($igrejaId, [1, 2, 3], true);
        }

        public function igrejaPorUuid(string $uuid): ?int
        {
            return null;
        }

        public function regionalPorUuid(string $uuid): ?int
        {
            return null;
        }

        public function resumo(array $ids): array
        {
            return [];
        }
    };

    $resultado = (new Alcance($atribuicoes, $igrejas))->calcular(1, 20);

    expect($resultado[Permissao::PessoasVer->value])->toBe([1, 2, 3])
        ->and($resultado[Permissao::PessoasEditar->value])->toBe([1, 2, 3]);
});

it('autoriza dentro e nega fora do alcance para toda permissão catalogada', function (Permissao $permissao): void {
    $atribuicoes = new class($permissao) implements AtribuicoesDePermissao
    {
        public function __construct(private readonly Permissao $permissao) {}

        public function doAcesso(int $organizacaoId, int $acessoId): array
        {
            return [[
                'permissao' => $this->permissao->value,
                'escopo_tipo' => 'igreja',
                'escopo_id' => 2,
            ]];
        }
    };
    $igrejas = new class implements CatalogoIgrejas
    {
        public function idsDaOrganizacao(int $organizacaoId): array
        {
            return [1, 2];
        }

        public function idsDaRegional(int $organizacaoId, int $regionalId): array
        {
            return [];
        }

        public function pertenceAOrganizacao(int $organizacaoId, int $igrejaId): bool
        {
            return in_array($igrejaId, [1, 2], true);
        }

        public function igrejaPorUuid(string $uuid): ?int
        {
            return null;
        }

        public function regionalPorUuid(string $uuid): ?int
        {
            return null;
        }

        public function resumo(array $ids): array
        {
            return [];
        }
    };
    $politica = new PoliticaDePermissao(new Alcance($atribuicoes, $igrejas));

    expect($politica->autoriza(1, 20, $permissao, 2))->toBeTrue()
        ->and($politica->autoriza(1, 20, $permissao, 3))->toBeFalse();
})->with(array_combine(
    array_map(fn (Permissao $permissao): string => $permissao->value, Permissao::cases()),
    array_map(fn (Permissao $permissao): array => [$permissao], Permissao::cases()),
));
