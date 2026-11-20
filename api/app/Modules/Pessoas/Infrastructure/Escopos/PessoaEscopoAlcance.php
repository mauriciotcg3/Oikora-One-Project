<?php

namespace App\Modules\Pessoas\Infrastructure\Escopos;

use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Contracts\ContextoDeAlcanceAusente;
use App\Modules\Autorizacao\Domain\Permissao;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filtro global obrigatório de Pessoa (OIK-ADR-0004 §5; RN-09): só as igrejas em que quem consulta tem
 * `pessoas.ver`, pela igreja atual da pessoa. Sem alcance no contexto, a consulta falha com
 * `authz.context_missing` — nunca devolve lista vazia. Só a BuscaDenominacao pode removê-lo.
 */
/** @implements Scope<Model> */
final class PessoaEscopoAlcance implements Scope
{
    /** @param  Builder<covariant Model>  $builder */
    public function apply(Builder $builder, Model $model): void
    {
        $alcance = app(AlcanceAtual::class);

        if (! $alcance->definido()) {
            throw new ContextoDeAlcanceAusente;
        }

        $builder->whereIn($model->qualifyColumn('igreja_id'), $alcance->igrejasCom(Permissao::PessoasVer));
    }
}
