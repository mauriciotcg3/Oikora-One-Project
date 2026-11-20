<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Via estreita da organização ativa (OIK-ADR-0003 §12, T-16): lista as organizações em que a conta tem
 * acesso ativo antes de existir contexto de RLS. Retorno mínimo: id, uuid e nome da organização.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            create or replace function identidade_acessos_ativos_da_conta(p_conta_id bigint)
            returns table (organizacao_id bigint, organizacao_uuid uuid, organizacao_nome text)
            language sql stable security definer set search_path = public, pg_temp as $$
                select o.id, o.uuid, o.nome::text
                from acessos a
                join organizacoes o on o.id = a.organizacao_id
                where a.conta_id = p_conta_id and a.estado = 'ativo'
                order by o.nome, o.id
            $$;

            revoke all on function identidade_acessos_ativos_da_conta(bigint) from public;
            grant execute on function identidade_acessos_ativos_da_conta(bigint) to oikora_app;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('drop function if exists identidade_acessos_ativos_da_conta(bigint)');
    }
};
