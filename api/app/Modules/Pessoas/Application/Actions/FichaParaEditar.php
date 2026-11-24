<?php

namespace App\Modules\Pessoas\Application\Actions;

use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Pessoas\Application\Configuracao\Resultado;
use App\Modules\Pessoas\Application\Consultas\BuscaDenominacao;
use App\Modules\Pessoas\Application\Consultas\Fichas;
use Illuminate\Support\Facades\Log;

/**
 * Localiza a Pessoa no alcance e confere a permissão na igreja dela, como a ficha (LerFicha): fora do
 * alcance, 403 se a pessoa existe na organização e 404 se não existe.
 */
final readonly class FichaParaEditar
{
    public function __construct(
        private AlcanceAtual $alcance,
        private Fichas $fichas,
        private BuscaDenominacao $busca,
    ) {}

    /** @return array{id: int, uuid: string, igreja_id: int}|Resultado */
    public function localizar(string $uuid, Permissao $permissao = Permissao::PessoasEditar): array|Resultado
    {
        $pessoa = $this->fichas->localizar($uuid);
        if ($pessoa === null) {
            return $this->busca->existe($uuid) ? self::negar(Permissao::PessoasVer) : Resultado::falha(404, 'pessoa_inexistente');
        }
        if (! $this->alcance->pode($permissao, $pessoa['igreja_id'])) {
            return self::negar($permissao);
        }

        return $pessoa;
    }

    public static function negar(Permissao $permissao): Resultado
    {
        Log::info('authz.denied', ['permissao' => $permissao->value]);

        return Resultado::falha(403, 'authz.denied');
    }
}
