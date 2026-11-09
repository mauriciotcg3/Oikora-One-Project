<?php

namespace App\Shared\Tenancy;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Database\Schema\Grammars\PostgresGrammar;
use Illuminate\Support\ServiceProvider;

/** Macros de migration do isolamento por organização (plan.md §2, OIK-ADR-0002 §4 e §6). */
class TenancyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Blueprint::macro('organizacao', function (): ColumnDefinition {
            /** @var Blueprint $this */
            $coluna = $this->bigInteger('organizacao_id');
            $this->foreign('organizacao_id')->references('id')->on('organizacoes');
            $this->unique(['organizacao_id', 'id']);
            $this->addCommand('politicaOrganizacao');

            return $coluna;
        });

        Blueprint::macro('foreignComposta', function (
            string $coluna,
            string $tabela,
            string $aoExcluir = 'no action',
        ): ColumnDefinition {
            /** @var Blueprint $this */
            $definicao = $this->bigInteger($coluna);
            $chave = $this->foreign(['organizacao_id', $coluna])->references(['organizacao_id', 'id'])->on($tabela);
            if ($aoExcluir !== 'no action') {
                $chave->onDelete($aoExcluir);
            }

            return $definicao;
        });

        // O `select` em volta da função é necessário: sem ele, o planejador do Postgres a avalia ao estimar
        // a consulta, e a RLS de `contas` falharia mesmo no ramo que não consulta `acessos`. Assim ela roda
        // uma vez por consulta, só na execução, e o índice de `organizacao_id` continua utilizável.
        PostgresGrammar::macro('compilePoliticaOrganizacao', function (Blueprint $tabela): array {
            $nome = '"'.$tabela->getTable().'"';

            return [
                "alter table {$nome} enable row level security",
                "alter table {$nome} force row level security",
                "create policy isolamento_organizacao on {$nome} "
                    .'using (organizacao_id = (select app_organizacao_atual())) '
                    .'with check (organizacao_id = (select app_organizacao_atual()))',
            ];
        });
    }
}
