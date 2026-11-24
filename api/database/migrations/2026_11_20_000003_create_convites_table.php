<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convites ao painel (OIK-ADR-0003 §2, §5; P FR-002, BR-010; RN-12) — OIK-SPEC-0001 T-22. Link de uso
 * único com token aleatório guardado em hash, válido por 72h e revogável, sob a RLS por organização.
 *
 * O aceite chega sem organização nem conta no contexto. Duas vias estreitas, `SECURITY DEFINER` e com
 * retorno mínimo, cobrem esse trecho: localizar a organização pelo hash do token e criar a conta com a
 * senha que a própria pessoa definiu (a RLS de `contas` só deixa o `oikora_app` gravar a própria conta).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convites', function (Blueprint $tabela): void {
            $tabela->id()->generatedAs()->always();
            $tabela->uuid('uuid')->unique();
            $tabela->organizacao();
            $tabela->foreignComposta('pessoa_id', 'pessoas', 'cascade');
            $tabela->foreignComposta('papel_id', 'papeis');
            $tabela->string('escopo_tipo', 20);
            $tabela->bigInteger('escopo_id')->nullable();
            $tabela->char('token_hash', 64)->unique();
            $tabela->timestampTz('expira_em');
            $tabela->string('estado', 20);
            $tabela->foreignComposta('emitido_por_acesso_id', 'acessos');
            $tabela->timestampTz('aceito_em')->nullable();
            $tabela->foreignComposta('acesso_id', 'acessos')->nullable();
            $tabela->timestampTz('revogado_em')->nullable();
            $tabela->foreignComposta('revogado_por_acesso_id', 'acessos')->nullable();
            $tabela->timestampsTz();

            $tabela->index(['organizacao_id', 'pessoa_id', 'estado']);
            $tabela->index(['estado', 'expira_em']);
        });

        DB::statement(<<<'SQL'
            alter table convites
                add constraint convites_estado_valido check (estado in ('enviado', 'aceito', 'expirado', 'revogado')),
                add constraint convites_escopo_valido check (
                    (escopo_tipo = 'organizacao' and escopo_id is null)
                    or (escopo_tipo in ('regional', 'igreja') and escopo_id is not null)
                )
        SQL);

        DB::unprepared(<<<'SQL'
            create or replace function identidade_organizacao_do_convite(p_token_hash text)
            returns bigint
            language sql stable security definer set search_path = public, pg_temp as $$
                select c.organizacao_id from convites c where c.token_hash = p_token_hash limit 1
            $$;

            create or replace function identidade_criar_conta(p_email text, p_celular text, p_password text)
            returns bigint
            language sql volatile security definer set search_path = public, pg_temp as $$
                insert into contas (uuid, email, celular, password, estado, created_at, updated_at)
                values (gen_random_uuid(), p_email, p_celular, p_password, 'ativa', now(), now())
                returning id
            $$;

            revoke all on function identidade_organizacao_do_convite(text) from public;
            revoke all on function identidade_criar_conta(text, text, text) from public;
            grant execute on function identidade_organizacao_do_convite(text) to oikora_app;
            grant execute on function identidade_criar_conta(text, text, text) to oikora_app;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            drop function if exists identidade_criar_conta(text, text, text);
            drop function if exists identidade_organizacao_do_convite(text);
        SQL);
        Schema::dropIfExists('convites');
    }
};
