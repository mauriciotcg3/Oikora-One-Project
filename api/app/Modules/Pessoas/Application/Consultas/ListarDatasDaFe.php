<?php

namespace App\Modules\Pessoas\Application\Consultas;

use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Organizacao\Contracts\ConsultaOrganizacoes;
use DateTimeImmutable;

final readonly class ListarDatasDaFe
{
    public function __construct(
        private AlcanceAtual $alcance,
        private ConsultaOrganizacoes $organizacoes,
        private DatasDaFe $datas,
    ) {}

    public function permitido(): bool
    {
        return $this->igrejas() !== [];
    }

    /** @return array{itens: list<array<string, mixed>>, criterio: array<string, mixed>} */
    public function novosConvertidos(): array
    {
        $igrejas = $this->igrejas();

        return [
            'itens' => $this->datas->novosConvertidos($igrejas),
            'criterio' => ['categoria' => 'fe', 'novo_convertido' => true, 'lixeira' => false, 'alcance' => 'igrejas_autorizadas'],
        ];
    }

    /** @return array{itens: list<array<string, mixed>>, criterio: array<string, mixed>} */
    public function aniversarios(string $periodo): array
    {
        $perfil = $this->organizacoes->perfilDeEnvio($this->alcance->ator()->organizacaoId);
        $inicio = new DateTimeImmutable('today', new \DateTimeZone($perfil->fuso));
        $fim = match ($periodo) {
            'dia' => $inicio,
            'semana' => $inicio->modify('+6 days'),
            default => $inicio->modify('last day of this month'),
        };

        return [
            'itens' => $this->datas->aniversarios($this->igrejas(), $inicio, $fim),
            'criterio' => ['categoria' => 'fe', 'periodo' => $periodo, 'inicio' => $inicio->format('Y-m-d'), 'fim' => $fim->format('Y-m-d'), 'fuso' => $perfil->fuso, 'lixeira' => false],
        ];
    }

    /** @return list<int> */
    private function igrejas(): array
    {
        return array_values(array_intersect(
            $this->alcance->igrejasCom(Permissao::PessoasVer),
            $this->alcance->igrejasCom(Permissao::FeVer),
        ));
    }
}
