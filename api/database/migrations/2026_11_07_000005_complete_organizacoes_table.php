<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizacoes', function (Blueprint $tabela): void {
            $tabela->string('estado', 20)->default('ativa');
            $tabela->string('fuso', 64)->default('America/Sao_Paulo');
            $tabela->string('logo_path')->nullable();
            $tabela->string('cor_primaria', 7)->nullable();
            $tabela->string('modo_matricula', 20)->default('manual');
            $tabela->unsignedInteger('limite_whatsapp_mensal')->nullable();
        });

        DB::statement(<<<'SQL'
            alter table organizacoes
                add constraint organizacoes_estado_valido check (estado in ('ativa', 'em_migracao')),
                add constraint organizacoes_modo_matricula_valido check (modo_matricula in ('manual', 'automatica')),
                add constraint organizacoes_cor_primaria_valida check (cor_primaria is null or cor_primaria ~ '^#[0-9A-Fa-f]{6}$'),
                add constraint organizacoes_limite_whatsapp_mensal_valido
                    check (limite_whatsapp_mensal is null or limite_whatsapp_mensal >= 0)
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            alter table organizacoes
                drop constraint organizacoes_estado_valido,
                drop constraint organizacoes_modo_matricula_valido,
                drop constraint organizacoes_cor_primaria_valida,
                drop constraint organizacoes_limite_whatsapp_mensal_valido
        SQL);

        Schema::table('organizacoes', function (Blueprint $tabela): void {
            $tabela->dropColumn([
                'estado',
                'fuso',
                'logo_path',
                'cor_primaria',
                'modo_matricula',
                'limite_whatsapp_mensal',
            ]);
        });
    }
};
