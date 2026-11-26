<?php

namespace App\Modules\Pessoas\Infrastructure\Consultas;

use App\Modules\Pessoas\Application\Consultas\FiltroPessoas;
use App\Modules\Pessoas\Domain\CampoFicha;
use App\Modules\Pessoas\Infrastructure\Pessoa;
use Illuminate\Database\Eloquent\Builder;

/** Filtro SQL único da lista e das exportações de Pessoas (RN-20). */
final class FiltroPessoasPostgres
{
    /** Colunas de data e booleanas: vazio é só null. */
    private const NAO_TEXTO = [
        CampoFicha::Nascimento, CampoFicha::DataCasamento, CampoFicha::ConversaoData, CampoFicha::BatismoData,
        CampoFicha::AceitouJesus, CampoFicha::Lider, CampoFicha::Pastor, CampoFicha::NovoConvertido,
    ];

    /** @param  Builder<Pessoa>  $consulta */
    public function aplicar(Builder $consulta, FiltroPessoas $filtro): void
    {
        $consulta
            ->when($filtro->igrejaId !== null, fn (Builder $q) => $q->where('pessoas.igreja_id', $filtro->igrejaId))
            ->when($filtro->vinculo !== null, fn (Builder $q) => $q->where('pessoas.vinculo', $filtro->vinculo))
            ->when($filtro->situacao !== null, fn (Builder $q) => $q->where('pessoas.situacao', $filtro->situacao))
            ->when($filtro->tipoMembroId !== null, fn (Builder $q) => $q->where('pessoas.tipo_membro_id', $filtro->tipoMembroId))
            ->when($filtro->mesAniversario !== null, fn (Builder $q) => $q->whereRaw("date_part('month', pessoas.nascimento) = ?", [$filtro->mesAniversario]))
            ->when($filtro->funcaoId !== null, fn (Builder $q) => $q->whereExists(fn ($sub) => $sub->selectRaw('1')
                ->from('pessoa_funcoes as f')
                ->whereColumn('f.pessoa_id', 'pessoas.id')
                ->where('f.funcao_id', $filtro->funcaoId)
                ->where(fn ($periodo) => $periodo->whereNull('f.ate')->orWhere('f.ate', '>=', now()->toDateString()))));

        if ($filtro->fichaIncompleta) {
            $consulta->where(function (Builder $q) use ($filtro): void {
                $algum = false;
                foreach ($filtro->obrigatoriosPorVinculo as $vinculo => $chaves) {
                    $campos = array_values(array_filter(array_map(CampoFicha::tryFrom(...), $chaves)));
                    if ($campos === []) {
                        continue;
                    }
                    $algum = true;
                    $q->orWhere(fn (Builder $doVinculo) => $doVinculo
                        ->where('pessoas.vinculo', $vinculo)
                        ->where(function (Builder $falta) use ($campos): void {
                            foreach ($campos as $campo) {
                                self::falta($falta, $campo);
                            }
                        }));
                }
                if (! $algum) {
                    $q->whereRaw('false');
                }
            });
        }
    }

    /** @param  Builder<Pessoa>  $falta */
    private static function falta(Builder $falta, CampoFicha $campo): void
    {
        match ($campo->tabela()) {
            'pessoa_contatos' => $falta->orWhereNotExists(fn ($c) => $c->selectRaw('1')->from('pessoa_contatos as c')
                ->whereColumn('c.pessoa_id', 'pessoas.id')
                ->where('c.tipo', $campo === CampoFicha::Whatsapp ? 'celular' : $campo->value)
                ->when($campo === CampoFicha::Whatsapp, fn ($w) => $w->where('c.e_whatsapp', true))),
            'pessoa_enderecos' => $falta->orWhereNotExists(fn ($e) => $e->selectRaw('1')->from('pessoa_enderecos as e')
                ->whereColumn('e.pessoa_id', 'pessoas.id')
                ->whereNotNull('e.'.$campo->value)
                ->where('e.'.$campo->value, '<>', '')),
            default => $falta->orWhereNull('pessoas.'.$campo->value)
                ->when(! in_array($campo, self::NAO_TEXTO, true), fn ($t) => $t->orWhere('pessoas.'.$campo->value, '=', '')),
        };
    }
}
