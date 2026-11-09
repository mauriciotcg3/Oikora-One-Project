<?php

namespace App\Modules\Pessoas\Infrastructure\Console;

use App\Modules\Organizacao\Contracts\ConsultaOrganizacoes;
use App\Modules\Pessoas\Infrastructure\Pessoa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecifrarCamposPessoas extends Command
{
    /** @var string */
    protected $signature = 'oikora:chaves:recifrar';

    /** @var string */
    protected $description = 'Recifra os campos sensíveis de Pessoas com a chave atual';

    public function __construct(private readonly ConsultaOrganizacoes $organizacoes)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $total = 0;
        $organizacoes = $this->organizacoes->idsDisponiveis();

        foreach ($organizacoes as $organizacaoId) {
            $inicio = microtime(true);
            Log::info('pessoas.recifra_iniciada', $this->contexto($organizacaoId, 'started'));

            try {
                $quantidade = DB::transaction(function () use ($organizacaoId): int {
                    DB::select("select set_config('app.organizacao_id', ?, true)", [(string) $organizacaoId]);

                    $recifradas = 0;
                    Pessoa::query()->orderBy('id')->chunkById(100, function ($pessoas) use (&$recifradas): void {
                        foreach ($pessoas as $pessoa) {
                            $valores = [];
                            foreach (['cpf', 'rg', 'rg_pais', 'necessidades_especiais', 'tipo_sanguineo'] as $campo) {
                                $valores[$campo] = $pessoa->{$campo};
                            }

                            $pessoa->forceFill($valores)->save();
                            $recifradas++;
                        }
                    });

                    return $recifradas;
                });
            } catch (Throwable $erro) {
                Log::error('pessoas.recifra_falhou', [
                    ...$this->contexto($organizacaoId, 'failure', $inicio),
                    'error' => $erro::class,
                ]);

                throw $erro;
            }

            $total += $quantidade;
            Log::info('pessoas.recifra_concluida', [
                ...$this->contexto($organizacaoId, 'success', $inicio),
                'count' => $quantidade,
            ]);
        }

        $this->info("{$total} pessoa(s) recifrada(s).");

        return self::SUCCESS;
    }

    /** @return array<string, int|string> */
    private function contexto(int|string $organizacaoId, string $status, ?float $inicio = null): array
    {
        $contexto = [
            'module' => 'Pessoas',
            'action' => 'recifrar_campos',
            'entity' => 'organizacao',
            'entity_id' => (int) $organizacaoId,
            'status' => $status,
        ];

        if ($inicio !== null) {
            $contexto['duration_ms'] = (int) round((microtime(true) - $inicio) * 1000);
        }

        return $contexto;
    }
}
