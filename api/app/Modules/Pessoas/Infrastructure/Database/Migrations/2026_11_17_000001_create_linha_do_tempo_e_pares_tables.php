<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Linha do tempo da Pessoa (OIK-ADR-0005 §1) e pares marcados como "é outra pessoa" (P FR-007) —
 * OIK-SPEC-0001 T-26. As duas tabelas ficam sob a RLS por organização.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_linha_do_tempo', function (Blueprint $tabela): void {
            $tabela->id()->generatedAs()->always();
            $tabela->uuid('uuid')->unique();
            $tabela->organizacao();
            $tabela->foreignComposta('pessoa_id', 'pessoas');
            $tabela->string('tipo', 30);
            $tabela->timestampTz('ocorrido_em');
            $tabela->foreignComposta('motivo_id', 'listas_valores')->nullable();
            $tabela->text('motivo_texto')->nullable();
            $tabela->foreignComposta('autor_acesso_id', 'acessos')->nullable();
            $tabela->string('origem', 30);
            $tabela->jsonb('dados')->nullable();
            $tabela->timestamps();

            $tabela->index(['organizacao_id', 'pessoa_id', 'ocorrido_em']);
        });

        DB::statement(<<<'SQL'
            alter table eventos_linha_do_tempo add constraint eventos_linha_do_tempo_tipo_valido check (tipo in (
                'entrada', 'vinculo_alterado', 'situacao_alterada', 'tipo_membro_alterado', 'transferencia',
                'mesclagem', 'mesclagem_desfeita', 'excluida', 'recuperada', 'registro_manual'
            ))
        SQL);

        Schema::create('pares_nao_duplicados', function (Blueprint $tabela): void {
            $tabela->id()->generatedAs()->always();
            $tabela->organizacao();
            $tabela->foreignComposta('pessoa_a_id', 'pessoas', 'cascade');
            $tabela->foreignComposta('pessoa_b_id', 'pessoas', 'cascade');
            $tabela->foreignComposta('confirmado_por_acesso_id', 'acessos')->nullable();
            $tabela->timestamps();

            $tabela->unique(['organizacao_id', 'pessoa_a_id', 'pessoa_b_id']);
        });

        DB::statement('alter table pares_nao_duplicados add constraint pares_nao_duplicados_ordem check (pessoa_a_id < pessoa_b_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('pares_nao_duplicados');
        Schema::dropIfExists('eventos_linha_do_tempo');
    }
};
