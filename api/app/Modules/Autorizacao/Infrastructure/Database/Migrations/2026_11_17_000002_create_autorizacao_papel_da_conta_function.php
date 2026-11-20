<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * RN-16 (T-16): o 2FA é obrigatório para quem tem o papel de sistema `administrador` em alguma organização.
 * A pergunta acontece no login, antes do contexto de RLS; a função devolve só um booleano.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            create or replace function autorizacao_conta_tem_papel_sistema(p_conta_id bigint, p_sistema text)
            returns boolean
            language sql stable security definer set search_path = public, pg_temp as $$
                select exists (
                    select 1
                    from atribuicoes_papel ap
                    join papeis p on p.organizacao_id = ap.organizacao_id and p.id = ap.papel_id
                    join acessos a on a.organizacao_id = ap.organizacao_id and a.id = ap.acesso_id
                    where a.conta_id = p_conta_id and a.estado = 'ativo' and p.sistema = p_sistema
                )
            $$;

            revoke all on function autorizacao_conta_tem_papel_sistema(bigint, text) from public;
            grant execute on function autorizacao_conta_tem_papel_sistema(bigint, text) to oikora_app;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('drop function if exists autorizacao_conta_tem_papel_sistema(bigint, text)');
    }
};
