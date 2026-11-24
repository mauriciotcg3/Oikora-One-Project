<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rascunhos_jornada', function (Blueprint $tabela): void {
            $tabela->id()->generatedAs()->always();
            $tabela->organizacao();
            $tabela->foreignId('conta_id')->constrained('contas');
            $tabela->string('tipo', 80);
            $tabela->string('etapa', 80);
            $tabela->jsonb('dados')->default('{}');
            $tabela->text('dados_sensiveis')->nullable();
            $tabela->unsignedInteger('versao')->default(1);
            $tabela->timestampTz('expira_em');
            $tabela->timestampsTz();
            $tabela->unique(['organizacao_id', 'conta_id', 'tipo'], 'rascunhos_jornada_dono_tipo_unico');
            $tabela->index(['organizacao_id', 'expira_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rascunhos_jornada');
    }
};
