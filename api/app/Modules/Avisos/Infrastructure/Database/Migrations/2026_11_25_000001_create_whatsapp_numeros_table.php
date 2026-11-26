<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_numeros', function (Blueprint $tabela): void {
            $tabela->id()->generatedAs()->always();
            $tabela->organizacao();
            $tabela->string('waba_id', 80);
            $tabela->string('phone_number_id', 80);
            $tabela->string('nome_exibicao', 160);
            $tabela->text('token_cifrado')->nullable();
            $tabela->jsonb('templates')->default('[]');
            $tabela->string('estado', 20);
            $tabela->timestampTz('ligado_em');
            $tabela->timestampTz('desligado_em')->nullable();
            $tabela->timestampsTz();
            $tabela->unique('organizacao_id');
            $tabela->unique(['organizacao_id', 'phone_number_id']);
        });
        DB::statement("alter table whatsapp_numeros add constraint whatsapp_numeros_estado_valido check (estado in ('ligado', 'desligado'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_numeros');
    }
};
