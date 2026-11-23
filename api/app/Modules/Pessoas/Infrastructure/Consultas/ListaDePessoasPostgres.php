<?php

namespace App\Modules\Pessoas\Infrastructure\Consultas;

use App\Modules\Pessoas\Application\Consultas\FiltroPessoas;
use App\Modules\Pessoas\Application\Consultas\ListaDePessoas;
use App\Modules\Pessoas\Domain\CampoFicha;
use App\Modules\Pessoas\Infrastructure\Pessoa;
use Illuminate\Database\Eloquent\Builder;

/** Lista pelo model Pessoa: o filtro global de alcance e a RLS se aplicam sempre. */
final class ListaDePessoasPostgres implements ListaDePessoas
{
    /** Colunas de data e booleanas: vazio é só null. */
    private const NAO_TEXTO = [
        CampoFicha::Nascimento, CampoFicha::DataCasamento, CampoFicha::ConversaoData, CampoFicha::BatismoData,
        CampoFicha::AceitouJesus, CampoFicha::Lider, CampoFicha::Pastor, CampoFicha::NovoConvertido,
    ];

    private const ORDEM = [
        'nome' => ['nome_busca', 'asc'],
        'nascimento' => ['nascimento', 'asc'],
        'entrada_em' => ['entrada_em', 'asc'],
        'cadastro' => ['created_at', 'asc'],
    ];

    public function buscar(FiltroPessoas $filtro, bool $comContatos): array
    {
        if ($filtro->semResultado) {
            return ['itens' => [], 'total' => 0];
        }

        $consulta = Pessoa::query()->whereNull('pessoas.excluida_em');
        $this->filtrar($consulta, $filtro);
        $total = (clone $consulta)->count();

        $colunas = ['pessoas.id', 'pessoas.uuid', 'pessoas.nome', 'pessoas.igreja_id', 'pessoas.vinculo', 'pessoas.situacao',
            'pessoas.tipo_membro_id', 'pessoas.nascimento', 'pessoas.matricula', 'pessoas.entrada_em'];
        $consulta->select($colunas);
        if ($comContatos) {
            foreach (['celular', 'email'] as $tipo) {
                $consulta->selectSub(
                    "select c.valor from pessoa_contatos c where c.pessoa_id = pessoas.id and c.tipo = '{$tipo}' order by c.principal desc, c.id limit 1",
                    $tipo,
                );
            }
        }

        $descendente = str_starts_with($filtro->ordem, '-');
        [$coluna] = self::ORDEM[ltrim($filtro->ordem, '-')] ?? self::ORDEM['nome'];
        $consulta->orderByRaw("pessoas.{$coluna} ".($descendente ? 'desc' : 'asc').' nulls last')->orderBy('pessoas.id');

        $itens = array_values($consulta->forPage($filtro->pagina, $filtro->porPagina)->toBase()->get()
            ->map(static function (object $linha) use ($comContatos): array {
                $item = [
                    'id' => (int) $linha->id,
                    'uuid' => (string) $linha->uuid,
                    'nome' => (string) $linha->nome,
                    'igreja_id' => (int) $linha->igreja_id,
                    'vinculo' => $linha->vinculo === null ? null : (string) $linha->vinculo,
                    'situacao' => $linha->situacao === null ? null : (string) $linha->situacao,
                    'tipo_membro_id' => $linha->tipo_membro_id === null ? null : (int) $linha->tipo_membro_id,
                    'nascimento' => $linha->nascimento === null ? null : substr((string) $linha->nascimento, 0, 10),
                    'matricula' => $linha->matricula === null ? null : (string) $linha->matricula,
                    'entrada_em' => $linha->entrada_em === null ? null : substr((string) $linha->entrada_em, 0, 10),
                ];
                if ($comContatos) {
                    $item['celular'] = $linha->celular === null ? null : (string) $linha->celular;
                    $item['email'] = $linha->email === null ? null : (string) $linha->email;
                }

                return $item;
            })->all());

        return ['itens' => $itens, 'total' => $total];
    }

    /** @param  Builder<Pessoa>  $consulta */
    private function filtrar(Builder $consulta, FiltroPessoas $filtro): void
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
                // Nenhum obrigatório configurado: toda ficha está completa (U-05).
                if (! $algum) {
                    $q->whereRaw('false');
                }
            });
        }
    }

    /**
     * "Campo obrigatório vazio", com a mesma regra da completude da ficha (FichasEloquent::preenchidos).
     * As colunas vêm só do catálogo (enum), nunca da requisição.
     *
     * @param  Builder<Pessoa>  $falta
     */
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
