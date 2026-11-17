<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Listas de valores (U-09, U-28) e campos obrigatórios por vínculo (BR-011, U-05, U-06) — OIK-SPEC-0001 T-25. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listas_valores', function (Blueprint $tabela): void {
            $tabela->id()->generatedAs()->always();
            $tabela->uuid('uuid')->unique();
            $tabela->organizacao();
            $tabela->string('lista', 30);
            $tabela->string('nome', 80);
            $tabela->boolean('ativo')->default(true);
            $tabela->integer('ordem');
            $tabela->timestamps();

            $tabela->index(['organizacao_id', 'lista', 'ordem']);
        });

        DB::statement("alter table listas_valores add constraint listas_valores_lista_valida check (lista in ('como_chegou', 'motivo_vinculo', 'motivo_inativacao', 'motivo_reativacao', 'tipo_membro', 'funcao'))");
        DB::statement('create unique index listas_valores_nome_unico on listas_valores (organizacao_id, lista, lower(nome))');

        Schema::create('campos_obrigatorios', function (Blueprint $tabela): void {
            $tabela->id()->generatedAs()->always();
            $tabela->organizacao();
            $tabela->string('vinculo', 20);
            $tabela->string('campo', 40);
            $tabela->timestamps();

            $tabela->unique(['organizacao_id', 'vinculo', 'campo']);
        });

        DB::statement("alter table campos_obrigatorios add constraint campos_obrigatorios_vinculo_valido check (vinculo in ('visitante', 'frequentador', 'membro'))");

        // `pessoas` nasceu na T-09 com as colunas, sem FK: a lista ainda não existia.
        Schema::table('pessoas', function (Blueprint $tabela): void {
            $tabela->foreign(['organizacao_id', 'tipo_membro_id'])->references(['organizacao_id', 'id'])->on('listas_valores');
            $tabela->foreign(['organizacao_id', 'como_chegou_id'])->references(['organizacao_id', 'id'])->on('listas_valores');
        });
    }

    public function down(): void
    {
        Schema::table('pessoas', function (Blueprint $tabela): void {
            $tabela->dropForeign(['organizacao_id', 'tipo_membro_id']);
            $tabela->dropForeign(['organizacao_id', 'como_chegou_id']);
        });

        Schema::dropIfExists('campos_obrigatorios');
        Schema::dropIfExists('listas_valores');
    }
};
