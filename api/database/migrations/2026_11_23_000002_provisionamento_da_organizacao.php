<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Provisionamento da organização (T-23; plan.md §3.3, §4): o identificador estável que torna o comando
 * idempotente, e o convite do primeiro administrador, que nasce sem emissor (a plataforma).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            alter table organizacoes add column identificador varchar(60) null;
            alter table organizacoes add constraint organizacoes_identificador_unico unique (identificador);
            alter table organizacoes add constraint organizacoes_identificador_valido
                check (identificador is null or identificador ~ '^[a-z0-9]+(-[a-z0-9]+)*$');
            alter table convites alter column emitido_por_acesso_id drop not null;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            delete from convites where emitido_por_acesso_id is null;
            alter table convites alter column emitido_por_acesso_id set not null;
            alter table organizacoes drop constraint organizacoes_identificador_valido;
            alter table organizacoes drop constraint organizacoes_identificador_unico;
            alter table organizacoes drop column identificador;
        SQL);
    }
};
