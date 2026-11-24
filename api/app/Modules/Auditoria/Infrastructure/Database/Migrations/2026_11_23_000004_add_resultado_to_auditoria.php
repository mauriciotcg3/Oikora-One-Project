<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            alter table auditoria
                add column resultado varchar(10) not null default 'sucesso',
                add column codigo_falha varchar(100) null,
                add column trace_id varchar(64) null,
                add constraint auditoria_resultado_valido check (resultado in ('sucesso', 'falha')),
                add constraint auditoria_falha_coerente check (
                    (resultado = 'sucesso' and codigo_falha is null)
                    or (resultado = 'falha' and codigo_falha is not null)
                );
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            alter table auditoria
                drop constraint if exists auditoria_falha_coerente,
                drop constraint if exists auditoria_resultado_valido,
                drop column if exists trace_id,
                drop column if exists codigo_falha,
                drop column if exists resultado;
        SQL);
    }
};
