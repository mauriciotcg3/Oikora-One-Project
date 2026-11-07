<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contas', function (Blueprint $tabela): void {
            $tabela->id()->generatedAs()->always();
            $tabela->uuid('uuid')->unique();
            $tabela->string('email')->nullable()->unique();
            $tabela->timestampTz('email_verificado_em')->nullable();
            $tabela->string('celular', 20)->nullable()->unique();
            $tabela->timestampTz('celular_verificado_em')->nullable();
            $tabela->string('password');
            $tabela->string('estado', 20)->default('ativa');
            $tabela->string('dois_fatores_canal', 20)->nullable();
            $tabela->text('two_factor_secret')->nullable();
            $tabela->text('two_factor_recovery_codes')->nullable();
            $tabela->timestampTz('two_factor_confirmed_at')->nullable();
            $tabela->timestamps();
        });

        DB::statement(<<<'SQL'
            alter table contas
                add constraint contas_email_minusculo check (email = lower(email)),
                add constraint contas_tem_identificador check (email is not null or celular is not null),
                add constraint contas_estado_valido check (estado in ('ativa', 'bloqueada')),
                add constraint contas_canal_valido check (dois_fatores_canal in ('email', 'whatsapp', 'totp'))
        SQL);

        Schema::create('sessions', function (Blueprint $tabela): void {
            $tabela->string('id')->primary();
            $tabela->foreignId('user_id')->nullable()->index()->constrained('contas')->cascadeOnDelete();
            $tabela->string('ip_address', 45)->nullable();
            $tabela->text('user_agent')->nullable();
            $tabela->longText('payload');
            $tabela->integer('last_activity')->index();
        });

        Schema::create('codigos_verificacao', function (Blueprint $tabela): void {
            $tabela->id()->generatedAs()->always();
            $tabela->foreignId('conta_id')->nullable()->constrained('contas')->cascadeOnDelete();
            $tabela->string('identificador_hash', 64)->nullable();
            $tabela->string('finalidade', 30);
            $tabela->string('codigo_hash');
            $tabela->string('canal', 20);
            $tabela->timestampTz('expira_em');
            $tabela->timestampTz('usado_em')->nullable();
            $tabela->unsignedSmallInteger('tentativas')->default(0);
            $tabela->string('envio_estado', 20)->nullable();
            $tabela->string('envio_id_externo')->nullable();
            $tabela->string('envio_erro')->nullable();
            $tabela->timestamps();
        });

        Schema::create('acessos', function (Blueprint $tabela): void {
            $tabela->id()->generatedAs()->always();
            $tabela->uuid('uuid')->unique();
            $tabela->organizacao();
            $tabela->foreignId('conta_id')->constrained('contas');
            // Sem FK por enquanto: `pessoas` nasce na T-09, que adiciona a FK composta.
            $tabela->bigInteger('pessoa_id');
            $tabela->string('estado', 20)->default('ativo');
            $tabela->string('origem', 20);
            $tabela->timestampTz('revogado_em')->nullable();
            $tabela->foreignComposta('revogado_por_acesso_id', 'acessos')->nullable();
            $tabela->timestamps();
        });

        DB::statement("alter table acessos add constraint acessos_estado_valido check (estado in ('ativo', 'revogado'))");
        DB::statement("create unique index acessos_conta_ativa_unico on acessos (organizacao_id, conta_id) where estado = 'ativo'");
        DB::statement("create unique index acessos_pessoa_ativa_unico on acessos (organizacao_id, pessoa_id) where estado = 'ativo'");

        Schema::create('eventos_autenticacao', function (Blueprint $tabela): void {
            $tabela->id()->generatedAs()->always();
            $tabela->foreignId('conta_id')->nullable()->index()->constrained('contas')->nullOnDelete();
            $tabela->string('identificador_hash', 64);
            $tabela->string('evento', 60);
            $tabela->string('ip_hash', 64);
            $tabela->string('canal', 20)->nullable();
            $tabela->string('request_id', 64)->nullable();
            $tabela->timestampTz('ocorrido_em')->index();
        });

        // Só inclusão para a aplicação (U-31). A purga de 1 ano roda como dono.
        DB::statement('revoke update, delete, truncate on eventos_autenticacao from oikora_app');

        $this->criarRlsDeIdentidade();
    }

    /** RLS de identidade e via estreita antes do contexto (OIK-ADR-0003 §3, plan.md §3.2). */
    private function criarRlsDeIdentidade(): void
    {
        // Sem FORCE, de propósito: as funções SECURITY DEFINER abaixo pertencem ao dono e precisam
        // enxergar a conta antes de existir contexto. `oikora_app` não é dono, então a política vale para ele.
        // O CASE garante que `acessos`, que exige contexto, só é consultada quando há organização.
        DB::unprepared(<<<'SQL'
            alter table contas enable row level security;

            create policy identidade_contas on contas
                using (
                    id = nullif(current_setting('app.conta_id', true), '')::bigint
                    or case
                        when nullif(current_setting('app.organizacao_id', true), '') is null then false
                        else exists (
                            select 1 from acessos a
                            where a.conta_id = contas.id and a.estado = 'ativo'
                        )
                    end
                )
                with check (id = nullif(current_setting('app.conta_id', true), '')::bigint);

            create or replace function identidade_localizar_conta(p_identificador text)
            returns table (id bigint, password text, estado text, dois_fatores_canal text)
            language sql stable security definer set search_path = public, pg_temp as $$
                select c.id, c.password::text, c.estado::text, c.dois_fatores_canal::text
                from contas c
                where c.email = p_identificador or c.celular = p_identificador
                limit 1
            $$;

            create or replace function identidade_identificador_em_uso(p_identificador text)
            returns boolean
            language sql stable security definer set search_path = public, pg_temp as $$
                select exists (select 1 from contas c where c.email = p_identificador or c.celular = p_identificador)
            $$;

            revoke all on function identidade_localizar_conta(text) from public;
            revoke all on function identidade_identificador_em_uso(text) from public;
            grant execute on function identidade_localizar_conta(text) to oikora_app;
            grant execute on function identidade_identificador_em_uso(text) to oikora_app;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            drop function if exists identidade_identificador_em_uso(text);
            drop function if exists identidade_localizar_conta(text);
        SQL);

        Schema::dropIfExists('eventos_autenticacao');
        Schema::dropIfExists('acessos');
        Schema::dropIfExists('codigos_verificacao');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('contas');
    }
};
