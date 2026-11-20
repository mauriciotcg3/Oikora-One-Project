<?php

namespace App\Modules\Pessoas\Infrastructure;

use App\Modules\Pessoas\Application\Actions\CadastroDePessoas;
use App\Modules\Pessoas\Application\Actions\DadosCadastroRapido;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Grava o cadastro rápido na transação da requisição (contexto de RLS já gravado). */
final class CadastroDePessoasEloquent implements CadastroDePessoas
{
    public function cadastrar(int $organizacaoId, DadosCadastroRapido $dados, int $autorAcessoId): array
    {
        $hoje = now()->toDateString();

        $pessoa = Pessoa::query()->create([
            'uuid' => (string) Str::uuid(),
            'organizacao_id' => $organizacaoId,
            'igreja_id' => $dados->igrejaId,
            'nome' => $dados->nome,
            'nascimento' => $dados->nascimento,
            'cpf' => $dados->cpf,
            'vinculo' => $dados->vinculo->value,
            'entrada_em' => $hoje,
            'como_chegou_id' => $dados->comoChegouId,
        ]);

        $contatos = array_filter([
            'celular' => $dados->celular,
            'email' => $dados->email,
        ]);
        foreach ($contatos as $tipo => $valor) {
            PessoaContato::query()->create([
                'uuid' => (string) Str::uuid(),
                'organizacao_id' => $organizacaoId,
                'pessoa_id' => $pessoa->id,
                'tipo' => $tipo,
                'valor' => $valor,
                'principal' => true,
            ]);
        }

        DB::table('eventos_linha_do_tempo')->insert([
            'uuid' => (string) Str::uuid(),
            'organizacao_id' => $organizacaoId,
            'pessoa_id' => $pessoa->id,
            'tipo' => 'entrada',
            'ocorrido_em' => now(),
            'motivo_id' => $dados->comoChegouId,
            'autor_acesso_id' => $autorAcessoId,
            'origem' => 'cadastro_rapido',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['id' => (int) $pessoa->id, 'uuid' => (string) $pessoa->uuid];
    }

    public function marcarNaoDuplicadas(int $organizacaoId, int $pessoaId, int $outraId, int $autorAcessoId): void
    {
        DB::table('pares_nao_duplicados')->insertOrIgnore([
            'organizacao_id' => $organizacaoId,
            'pessoa_a_id' => min($pessoaId, $outraId),
            'pessoa_b_id' => max($pessoaId, $outraId),
            'confirmado_por_acesso_id' => $autorAcessoId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
