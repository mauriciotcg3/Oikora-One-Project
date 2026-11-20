<?php

namespace App\Modules\Pessoas\Application\Consultas;

use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\Permissao;

/**
 * Detector de duplicidade do cadastro rápido (RN-23; CA-01; U-20). Fora do alcance de quem cadastra, a
 * resposta tem só nome, igreja e vínculo (RN-09). Na lixeira, a ação oferecida é recuperar a ficha.
 */
final readonly class VerificarDuplicidade
{
    public function __construct(
        private BuscaDenominacao $busca,
        private AlcanceAtual $alcance,
    ) {}

    /** Quem pode cadastrar em alguma igreja pode perguntar. */
    public function permitido(): bool
    {
        return $this->alcance->igrejasCom(Permissao::PessoasCriar) !== [];
    }

    /**
     * @return list<array{uuid: string, nome: string, igreja_id: int, vinculo: string|null, motivos: list<string>, na_lixeira: bool, acoes: list<string>}>
     */
    public function executar(CriteriosDuplicidade $criterios): array
    {
        if ($criterios->vazio()) {
            return [];
        }

        return array_map(function (array $pessoa): array {
            $noAlcance = $this->alcance->pode(Permissao::PessoasVer, $pessoa['igreja_id']);
            $acoes = match (true) {
                $pessoa['na_lixeira'] && $this->alcance->pode(Permissao::PessoasExcluir, $pessoa['igreja_id']) => ['recuperar'],
                $pessoa['na_lixeira'] => [],
                $noAlcance => ['abrir'],
                default => [],
            };

            return [...$pessoa, 'acoes' => $acoes];
        }, $this->busca->duplicados($criterios));
    }
}
