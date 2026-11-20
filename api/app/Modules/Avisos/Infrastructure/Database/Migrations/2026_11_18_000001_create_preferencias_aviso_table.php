<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Preferências de aviso conta × organização × tipo × canal, ligadas por padrão: só a escolha feita pela
 * pessoa vira linha (OIK-ADR-0008 §13; plan.md §3.6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preferencias_aviso', function (Blueprint $tabela): void {
            $tabela->id()->generatedAs()->always();
            $tabela->organizacao();
            $tabela->foreignId('conta_id')->constrained('contas');
            $tabela->string('tipo', 60);
            $tabela->string('canal', 20);
            $tabela->boolean('ligado')->default(true);
            $tabela->timestampsTz();

            $tabela->unique(['organizacao_id', 'conta_id', 'tipo', 'canal']);
        });

        DB::statement("alter table preferencias_aviso add constraint preferencias_aviso_canal_valido check (canal in ('email', 'whatsapp'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('preferencias_aviso');
    }
};
