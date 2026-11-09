<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            create table auditoria (
                id bigint generated always as identity,
                organizacao_id bigint not null references organizacoes(id),
                ocorrido_em timestamptz not null default now(),
                evento varchar(100) not null,
                acesso_id bigint null,
                conta_id bigint null,
                operador_id bigint null,
                autorizacao_suporte_id bigint null,
                em_nome_de_pessoa_id bigint null,
                alvo_tipo varchar(80) not null,
                alvo_id varchar(80) not null,
                igreja_id bigint null,
                campos jsonb not null default '[]'::jsonb,
                metadados jsonb not null default '{}'::jsonb,
                canal varchar(30) not null,
                request_id varchar(64) null,
                primary key (id, ocorrido_em),
                constraint auditoria_campos_array check (jsonb_typeof(campos) = 'array'),
                constraint auditoria_metadados_objeto check (jsonb_typeof(metadados) = 'object')
            ) partition by range (ocorrido_em);

            create index auditoria_organizacao_ocorrido_idx on auditoria (organizacao_id, ocorrido_em desc);
            alter table auditoria enable row level security;
            alter table auditoria force row level security;
            create policy isolamento_organizacao on auditoria
                using (organizacao_id = (select app_organizacao_atual()))
                with check (organizacao_id = (select app_organizacao_atual()));
            revoke update, delete, truncate on auditoria from oikora_app;

            create table auditoria_plataforma (
                id bigint generated always as identity primary key,
                ocorrido_em timestamptz not null default now(),
                comando varchar(120) not null,
                operador varchar(120) not null,
                motivo_codigo varchar(80) not null,
                organizacoes jsonb not null default '[]'::jsonb,
                request_id varchar(64) null
            );
            revoke all on auditoria_plataforma from oikora_app;
        SQL);

        $this->criarParticao(Carbon::now('UTC')->startOfMonth());
        $this->criarParticao(Carbon::now('UTC')->startOfMonth()->addMonth());
    }

    private function criarParticao(Carbon $inicio): void
    {
        $fim = $inicio->copy()->addMonth();
        $nome = 'auditoria_'.$inicio->format('Ym');
        $limiteInferior = $inicio->format('Y-m-d H:i:sP');
        $limiteSuperior = $fim->format('Y-m-d H:i:sP');
        DB::statement(
            "create table {$nome} partition of auditoria "
            ."for values from ('{$limiteInferior}') to ('{$limiteSuperior}')",
        );
        DB::statement("alter table {$nome} enable row level security");
        DB::statement("alter table {$nome} force row level security");
        DB::statement(
            "create policy isolamento_organizacao on {$nome} "
            .'using (organizacao_id = (select app_organizacao_atual())) '
            .'with check (organizacao_id = (select app_organizacao_atual()))',
        );
        DB::statement("revoke update, delete, truncate on {$nome} from oikora_app");
    }

    public function down(): void
    {
        DB::unprepared('drop table if exists auditoria_plataforma; drop table if exists auditoria cascade;');
    }
};
