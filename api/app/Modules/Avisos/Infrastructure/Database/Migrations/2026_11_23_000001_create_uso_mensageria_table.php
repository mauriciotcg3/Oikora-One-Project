<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Uso de mensageria por organização × mês × canal × categoria (OIK-ADR-0008 §16, §17; plan.md §3.6).
 * Base da cobrança e do limite mensal de WhatsApp; retenção de 5 anos. Sem destino nem conteúdo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uso_mensageria', function (Blueprint $tabela): void {
            $tabela->id()->generatedAs()->always();
            $tabela->organizacao();
            // Primeiro dia do mês, no fuso da organização.
            $tabela->date('mes');
            $tabela->string('canal', 20);
            $tabela->string('categoria', 20);
            $tabela->unsignedInteger('quantidade')->default(0);
            $tabela->decimal('custo_estimado', 12, 4)->default(0);
            $tabela->timestampsTz();

            $tabela->unique(['organizacao_id', 'mes', 'canal', 'categoria']);
        });

        DB::statement("alter table uso_mensageria add constraint uso_mensageria_canal_valido check (canal in ('email', 'whatsapp'))");
        DB::statement('alter table uso_mensageria add constraint uso_mensageria_mes_valido check (extract(day from mes) = 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('uso_mensageria');
    }
};
