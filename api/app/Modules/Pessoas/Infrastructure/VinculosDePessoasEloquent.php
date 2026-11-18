<?php

namespace App\Modules\Pessoas\Infrastructure;

use App\Modules\Pessoas\Application\Actions\VinculosDePessoas;
use App\Modules\Pessoas\Domain\Situacao;
use App\Modules\Pessoas\Domain\Vinculo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Pela Pessoa já localizada no alcance, na transação da requisição (contexto de RLS gravado). */
final class VinculosDePessoasEloquent implements VinculosDePessoas
{
    public function estado(int $id): array
    {
        $pessoa = Pessoa::query()->select(['id', 'vinculo', 'situacao', 'matricula', 'novo_convertido'])->whereKey($id)->firstOrFail();
        $vinculo = $pessoa->getAttribute('vinculo');
        $situacao = $pessoa->getAttribute('situacao');
        $matricula = $pessoa->getAttribute('matricula');

        return [
            'vinculo' => is_string($vinculo) ? Vinculo::tryFrom($vinculo) : null,
            'situacao' => Situacao::daColuna(is_string($situacao) ? $situacao : null),
            'matricula' => is_string($matricula) && $matricula !== '' ? $matricula : null,
            'novo_convertido' => (bool) $pessoa->getAttribute('novo_convertido'),
        ];
    }

    public function alterarVinculo(int $id, Vinculo $vinculo, ?string $matricula): void
    {
        $pessoa = Pessoa::query()->whereKey($id)->firstOrFail();
        $pessoa->forceFill([
            'vinculo' => $vinculo->value,
            'matricula' => $vinculo->temMatricula() ? $matricula : null,
            'novo_convertido' => false,
        ])->save();
    }

    public function alterarSituacao(int $id, Situacao $situacao): void
    {
        $pessoa = Pessoa::query()->whereKey($id)->firstOrFail();
        $pessoa->forceFill(['situacao' => $situacao->value])->save();
    }

    public function registrarEvento(int $organizacaoId, int $id, string $tipo, int $motivoId, array $dados, int $autorAcessoId): void
    {
        DB::table('eventos_linha_do_tempo')->insert([
            'uuid' => (string) Str::uuid(),
            'organizacao_id' => $organizacaoId,
            'pessoa_id' => $id,
            'tipo' => $tipo,
            'ocorrido_em' => now(),
            'motivo_id' => $motivoId,
            'autor_acesso_id' => $autorAcessoId,
            'origem' => 'painel',
            'dados' => json_encode($dados, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
