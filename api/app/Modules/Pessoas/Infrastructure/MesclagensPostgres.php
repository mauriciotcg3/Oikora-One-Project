<?php

namespace App\Modules\Pessoas\Infrastructure;

use App\Modules\Pessoas\Application\Mesclagem\Mesclagens;
use App\Modules\Pessoas\Domain\CampoFicha;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MesclagensPostgres implements Mesclagens
{
    public function bloquear(array $uuids): array
    {
        return Pessoa::query()
            ->whereIn('uuid', $uuids)
            ->whereNull('excluida_em')
            ->orderBy('id')
            ->lockForUpdate()
            ->toBase()
            ->get()
            ->mapWithKeys(static fn (object $linha): array => [(string) $linha->uuid => (array) $linha])
            ->all();
    }

    public function filhos(int $pessoaId): array
    {
        $ids = static fn (string $tabela, string $coluna = 'pessoa_id'): array => array_values(DB::table($tabela)
            ->where($coluna, $pessoaId)->orderBy('id')->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all());

        return [
            'contatos' => $ids('pessoa_contatos'),
            'enderecos' => $ids('pessoa_enderecos'),
            'relacionamentos' => array_values(array_unique([...$ids('relacionamentos'), ...$ids('relacionamentos', 'relacionada_id')])),
            'funcoes' => $ids('pessoa_funcoes'),
            'eventos' => $ids('eventos_linha_do_tempo'),
        ];
    }

    public function aplicar(int $mantidaId, int $absorvidaId, array $campos): void
    {
        /** @var Pessoa $absorvida */
        $absorvida = Pessoa::query()->whereKey($absorvidaId)->firstOrFail();
        $valores = [];
        foreach ($campos as $campo) {
            if (CampoFicha::tryFrom($campo)?->tabela() === 'pessoas') {
                $valores[$campo] = $absorvida->getAttribute($campo);
            }
        }

        // Primeiro a absorvida solta CPF e matrícula; os valores ficam só no snapshot (U-30).
        Pessoa::query()->whereKey($absorvidaId)->update(['cpf' => null, 'cpf_hash' => null, 'matricula' => null, 'updated_at' => now()]);

        if ($valores !== []) {
            /** @var Pessoa $mantida */
            $mantida = Pessoa::query()->whereKey($mantidaId)->firstOrFail();
            $mantida->fill($valores)->save();
        }
    }

    public function moverFilhos(int $absorvidaId, int $mantidaId): array
    {
        // Relacionamento entre as duas viraria "consigo mesma": sai.
        DB::table('relacionamentos')
            ->where(fn ($q) => $q->where('pessoa_id', $absorvidaId)->where('relacionada_id', $mantidaId))
            ->orWhere(fn ($q) => $q->where('pessoa_id', $mantidaId)->where('relacionada_id', $absorvidaId))
            ->delete();
        // Tipo fixo que a mantida já tem com a mesma pessoa: fica o da mantida.
        DB::statement(<<<'SQL'
            delete from relacionamentos r
             where r.tipo <> 'outro'
               and ((r.pessoa_id = ? and exists (select 1 from relacionamentos m where m.pessoa_id = ? and m.relacionada_id = r.relacionada_id and m.tipo = r.tipo))
                 or (r.relacionada_id = ? and exists (select 1 from relacionamentos m where m.relacionada_id = ? and m.pessoa_id = r.pessoa_id and m.tipo = r.tipo)))
        SQL, [$absorvidaId, $mantidaId, $absorvidaId, $mantidaId]);

        $movidos = $this->filhos($absorvidaId);
        $agora = now();
        foreach (['pessoa_contatos', 'pessoa_enderecos', 'pessoa_funcoes', 'eventos_linha_do_tempo'] as $tabela) {
            DB::table($tabela)->where('pessoa_id', $absorvidaId)->update(['pessoa_id' => $mantidaId, 'updated_at' => $agora]);
        }
        DB::table('relacionamentos')->where('pessoa_id', $absorvidaId)->update(['pessoa_id' => $mantidaId, 'updated_at' => $agora]);
        DB::table('relacionamentos')->where('relacionada_id', $absorvidaId)->update(['relacionada_id' => $mantidaId, 'updated_at' => $agora]);
        // A mantida passa a ter um só contato principal por tipo: o dela, se havia.
        DB::statement(<<<'SQL'
            update pessoa_contatos c set principal = false
             where c.pessoa_id = ? and c.principal
               and exists (select 1 from pessoa_contatos o where o.pessoa_id = c.pessoa_id and o.tipo = c.tipo and o.principal and o.id < c.id)
        SQL, [$mantidaId]);

        return $movidos;
    }

    public function marcarAbsorvida(int $absorvidaId, ?int $porAcessoId): void
    {
        Pessoa::query()->whereKey($absorvidaId)->update([
            'excluida_em' => now(),
            'excluida_por_acesso_id' => $porAcessoId,
            'exclusao_motivo' => 'mesclagem',
            'updated_at' => now(),
        ]);
    }

    public function desfazer(array $snapshot, array $afetados): array
    {
        /** @var array<string, mixed> $antesMantida */
        $antesMantida = $snapshot['mantida'];
        /** @var array<string, mixed> $antesAbsorvida */
        $antesAbsorvida = $snapshot['absorvida'];
        /** @var list<string> $campos */
        $campos = $snapshot['campos_da_absorvida'] ?? [];
        $mantidaId = (int) $afetados['mantida_id'];
        $absorvidaId = (int) $afetados['absorvida_id'];

        /** @var Pessoa $mantida */
        $mantida = Pessoa::query()->whereKey($mantidaId)->lockForUpdate()->firstOrFail();
        $modeloAntesMantida = (new Pessoa)->setRawAttributes($antesMantida, true);
        $modeloAntesAbsorvida = (new Pessoa)->setRawAttributes($antesAbsorvida, true);
        $conflitos = [];
        foreach ($campos as $campo) {
            if ($mantida->getAttribute($campo) === $modeloAntesAbsorvida->getAttribute($campo)) {
                $mantida->setAttribute($campo, $modeloAntesMantida->getAttribute($campo));
            } else {
                $conflitos[] = $campo;
            }
        }
        $mantida->save();

        unset($antesAbsorvida['id']);
        DB::table('pessoas')->where('id', $absorvidaId)->update($antesAbsorvida);

        /** @var array<string, list<int>> $movidos */
        $movidos = $afetados['movidos'] ?? [];
        $agora = now();
        foreach (['contatos' => 'pessoa_contatos', 'enderecos' => 'pessoa_enderecos', 'funcoes' => 'pessoa_funcoes', 'eventos' => 'eventos_linha_do_tempo'] as $chave => $tabela) {
            DB::table($tabela)->whereIn('id', $movidos[$chave] ?? [])->update(['pessoa_id' => $absorvidaId, 'updated_at' => $agora]);
        }
        $relacionamentos = $movidos['relacionamentos'] ?? [];
        DB::table('relacionamentos')->whereIn('id', $relacionamentos)->where('pessoa_id', $mantidaId)
            ->update(['pessoa_id' => $absorvidaId, 'updated_at' => $agora]);
        DB::table('relacionamentos')->whereIn('id', $relacionamentos)->where('relacionada_id', $mantidaId)
            ->update(['relacionada_id' => $absorvidaId, 'updated_at' => $agora]);

        return $conflitos;
    }

    public function apagarAbsorvida(int $absorvidaId): void
    {
        DB::table('eventos_linha_do_tempo')->where('pessoa_id', $absorvidaId)->delete();
        Pessoa::query()->whereKey($absorvidaId)->delete();
    }

    public function evento(int $organizacaoId, int $pessoaId, string $tipo, ?int $autorAcessoId, array $dados): void
    {
        DB::table('eventos_linha_do_tempo')->insert([
            'uuid' => (string) Str::uuid(),
            'organizacao_id' => $organizacaoId,
            'pessoa_id' => $pessoaId,
            'tipo' => $tipo,
            'ocorrido_em' => now(),
            'autor_acesso_id' => $autorAcessoId,
            'origem' => 'painel',
            'dados' => json_encode($dados, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function pares(int $pagina, int $porPagina): array
    {
        // Só pessoas que o filtro de alcance deixa ver, fora da lixeira.
        $visiveis = Pessoa::query()->whereNull('excluida_em')->select('id');

        $base = DB::query()
            ->fromSub(function ($q) use ($visiveis): void {
                $q->from('pessoas as a')
                    ->join('pessoas as b', function ($join): void {
                        $join->on('a.organizacao_id', '=', 'b.organizacao_id')->whereColumn('a.id', '<', 'b.id');
                    })
                    ->whereIn('a.id', $visiveis)
                    ->whereIn('b.id', (clone $visiveis))
                    ->where(function ($criterio): void {
                        $criterio->where(fn ($n) => $n->whereColumn('a.nome_busca', 'b.nome_busca')->whereColumn('a.nascimento', 'b.nascimento'))
                            ->orWhere(fn ($c) => $c->whereNotNull('a.cpf_hash')->whereColumn('a.cpf_hash', 'b.cpf_hash'))
                            ->orWhereExists(fn ($e) => $e->selectRaw('1')->from('pessoa_contatos as ca')
                                ->join('pessoa_contatos as cb', function ($j): void {
                                    $j->whereRaw('lower(ca.valor) = lower(cb.valor)')->where('cb.tipo', 'email');
                                })
                                ->whereColumn('ca.pessoa_id', 'a.id')->whereColumn('cb.pessoa_id', 'b.id')->where('ca.tipo', 'email'));
                    })
                    ->whereNotExists(fn ($n) => $n->selectRaw('1')->from('pares_nao_duplicados as p')
                        ->whereColumn('p.pessoa_a_id', 'a.id')->whereColumn('p.pessoa_b_id', 'b.id'))
                    ->selectRaw('a.id as a, b.id as b')
                    ->selectRaw('(a.nome_busca = b.nome_busca and a.nascimento = b.nascimento) as por_nome')
                    ->selectRaw('(a.cpf_hash is not null and a.cpf_hash = b.cpf_hash) as por_cpf')
                    ->selectRaw("exists (select 1 from pessoa_contatos ca join pessoa_contatos cb on lower(ca.valor) = lower(cb.valor) and cb.tipo = 'email'
                                  where ca.pessoa_id = a.id and cb.pessoa_id = b.id and ca.tipo = 'email') as por_email");
            }, 'pares');

        $total = (clone $base)->count();
        $linhas = $base->orderBy('a')->orderBy('b')->forPage($pagina, $porPagina)->get();

        $itens = [];
        foreach ($linhas as $linha) {
            $criterios = [];
            foreach (['nome_nascimento' => $linha->por_nome, 'cpf' => $linha->por_cpf, 'email' => $linha->por_email] as $criterio => $casou) {
                if ((bool) $casou) {
                    $criterios[] = $criterio;
                }
            }
            $itens[] = ['a' => (int) $linha->a, 'b' => (int) $linha->b, 'criterios' => $criterios];
        }

        return ['itens' => $itens, 'total' => $total];
    }

    /** @param  list<int>  $ids */
    public function resumos(array $ids): array
    {
        return Pessoa::query()->whereIn('id', $ids)->get(['id', 'uuid', 'nome', 'igreja_id', 'nascimento', 'vinculo'])
            ->mapWithKeys(static fn (Pessoa $p): array => [(int) $p->getKey() => [
                'uuid' => $p->uuid,
                'nome' => (string) $p->getAttribute('nome'),
                'igreja_id' => $p->igreja_id,
                'nascimento' => $p->getAttribute('nascimento')?->format('Y-m-d'),
                'vinculo' => $p->vinculo,
            ]])->all();
    }
}
