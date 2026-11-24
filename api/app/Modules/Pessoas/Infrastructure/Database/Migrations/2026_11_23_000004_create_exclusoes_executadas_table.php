<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registro mínimo das exclusões LGPD (OIK-ADR-0005 §8; plan.md §3.5): pessoa, pedido, data e modo, para
 * reaplicar as exclusões depois de restaurar um backup. Sem FK para `pessoas`: no modo `apagar` a
 * ficha some e o registro fica.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exclusoes_executadas', function (Blueprint $tabela): void {
            $tabela->id()->generatedAs()->always();
            $tabela->organizacao();
            $tabela->bigInteger('pessoa_id');
            $tabela->uuid('pessoa_uuid');
            $tabela->string('modo', 20);
            $tabela->string('origem', 30);
            $tabela->timestampTz('pedida_em')->nullable();
            $tabela->timestampTz('executada_em');
            $tabela->timestampsTz();

            $tabela->unique(['organizacao_id', 'pessoa_id']);
        });

        DB::statement("alter table exclusoes_executadas add constraint exclusoes_executadas_modo_valido check (modo in ('anonimizar', 'apagar'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('exclusoes_executadas');
    }
};
