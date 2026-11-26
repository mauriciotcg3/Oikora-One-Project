<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exportacoes', function (Blueprint $tabela): void {
            $tabela->id()->generatedAs()->always();
            $tabela->uuid('uuid')->unique();
            $tabela->organizacao();
            $tabela->foreignComposta('acesso_id', 'acessos');
            $tabela->bigInteger('conta_id');
            $tabela->string('tipo', 30);
            $tabela->jsonb('filtro')->default('{}');
            $tabela->jsonb('ids_selecionados')->default('[]');
            $tabela->jsonb('categorias')->default('[]');
            $tabela->string('formato', 10);
            $tabela->string('estado', 20);
            $tabela->string('motivo_codigo', 40)->nullable();
            $tabela->string('arquivo_path', 500)->nullable();
            $tabela->unsignedInteger('contagem')->nullable();
            $tabela->timestampTz('concluida_em')->nullable();
            $tabela->timestampTz('expira_em');
            $tabela->timestampsTz();
            $tabela->index(['organizacao_id', 'acesso_id', 'created_at']);
        });
        DB::statement("alter table exportacoes add constraint exportacoes_tipo_valido check (tipo in ('pessoas', 'aniversarios', 'novos_convertidos'))");
        DB::statement("alter table exportacoes add constraint exportacoes_formato_valido check (formato in ('xlsx', 'csv'))");
        DB::statement("alter table exportacoes add constraint exportacoes_estado_valido check (estado in ('pendente', 'processando', 'concluida', 'negada', 'expirada'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('exportacoes');
    }
};
