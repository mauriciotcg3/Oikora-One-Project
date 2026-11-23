<?php

namespace App\Modules\Pessoas\Infrastructure;

use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Pessoas\Contracts\PessoasDoProvisionamento;
use App\Modules\Pessoas\Domain\Contato;
use App\Modules\Pessoas\Domain\Vinculo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class PessoasDoProvisionamentoEloquent implements PessoasDoProvisionamento
{
    public function __construct(private AlcanceAtual $alcance) {}

    public function criarAdministrador(int $organizacaoId, int $igrejaId, string $nome, ?string $email, ?string $celular): array
    {
        $celularE164 = null;
        if ($celular !== null && $celular !== '') {
            $celularE164 = Contato::celular($celular) ?? throw new InvalidArgumentException('celular_invalido');
        }

        return $this->alcance->comoSistema($organizacaoId, function () use ($organizacaoId, $igrejaId, $nome, $email, $celularE164): array {
            $pessoa = Pessoa::query()->create([
                'uuid' => (string) Str::uuid(),
                'organizacao_id' => $organizacaoId,
                'igreja_id' => $igrejaId,
                'nome' => Contato::nome($nome),
                'vinculo' => Vinculo::Frequentador->value,
                'entrada_em' => now()->toDateString(),
            ]);

            $contatos = array_filter([
                'email' => $email === null || $email === '' ? null : Contato::email($email),
                'celular' => $celularE164,
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
                'origem' => 'provisionamento',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return ['id' => (int) $pessoa->id, 'uuid' => (string) $pessoa->uuid];
        });
    }
}
