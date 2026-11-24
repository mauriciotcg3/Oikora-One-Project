<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Operações desfazíveis (OIK-ADR-0005 §6; plan.md §3.4): o snapshot fica cifrado com a chave de campos
 * e vive 90 dias. `afetados` guarda só ids internos, para a compensação e para a purga (U-33).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operacoes_desfaziveis', function (Blueprint $tabela): void {
            $tabela->id()->generatedAs()->always();
            $tabela->uuid('uuid')->unique();
            $tabela->organizacao();
            $tabela->string('tipo', 30);
            $tabela->foreignComposta('autor_acesso_id', 'acessos')->nullable();
            $tabela->string('estado', 20)->default('executada');
            $tabela->timestampTz('executada_em');
            $tabela->timestampTz('expira_em');
            $tabela->timestampTz('desfeita_em')->nullable();
            $tabela->foreignComposta('desfeita_por_acesso_id', 'acessos')->nullable();
            // Texto cifrado (AES-256 com a chave de campos); null depois de desfeita ou expirada.
            $tabela->text('snapshot')->nullable();
            $tabela->jsonb('afetados')->default('{}');
            $tabela->timestampsTz();

            $tabela->index(['organizacao_id', 'estado', 'expira_em']);
        });

        DB::statement(<<<'SQL'
            alter table operacoes_desfaziveis
                add constraint operacoes_desfaziveis_tipo_valido check (tipo in ('mesclagem', 'importacao', 'migracao')),
                add constraint operacoes_desfaziveis_estado_valido check (estado in ('executada', 'desfeita', 'expirada')),
                add constraint operacoes_desfaziveis_afetados_objeto check (jsonb_typeof(afetados) = 'object')
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('operacoes_desfaziveis');
    }
};
