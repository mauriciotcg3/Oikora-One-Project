<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Contexto ausente é erro, nunca lista vazia (OIK-ADR-0002 §4). Depois de um set_config local,
        // o valor volta a ser texto vazio, e não nulo: os dois casos contam como ausente.
        DB::unprepared(<<<'SQL'
            create or replace function app_organizacao_atual() returns bigint
            language plpgsql stable as $$
            declare
                valor text := current_setting('app.organizacao_id', true);
            begin
                if valor is null or valor = '' then
                    raise exception 'tenant.context.missing';
                end if;
                return valor::bigint;
            end
            $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('drop function if exists app_organizacao_atual()');
    }
};
