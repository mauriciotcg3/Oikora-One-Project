<?php

use App\Modules\Autorizacao\Domain\Permissao;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitações e concessões temporárias de permissão (OIK-ADR-0012 §6–§11; OIK-SPEC-0001 T-47). As quatro
 * tabelas ficam sob a RLS por organização, com FKs compostas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitacoes_permissao', function (Blueprint $tabela): void {
            $tabela->id()->generatedAs()->always();
            $tabela->uuid('uuid')->unique();
            $tabela->organizacao();
            $tabela->foreignComposta('solicitante_acesso_id', 'acessos');
            $tabela->string('estado', 20)->default('pendente');
            $tabela->text('justificativa');
            $tabela->smallInteger('duracao_horas');
            $tabela->foreignComposta('decidida_por_acesso_id', 'acessos')->nullable();
            $tabela->timestampTz('decidida_em')->nullable();
            $tabela->text('motivo_decisao')->nullable();
            $tabela->integer('versao')->default(1);
            $tabela->timestamps();

            $tabela->index(['organizacao_id', 'estado']);
            $tabela->index(['organizacao_id', 'solicitante_acesso_id']);
        });

        Schema::create('solicitacao_permissoes', function (Blueprint $tabela): void {
            $tabela->id()->generatedAs()->always();
            $tabela->organizacao();
            $tabela->foreignComposta('solicitacao_id', 'solicitacoes_permissao', 'cascade');
            $tabela->string('permissao', 80);
            $tabela->string('escopo_tipo', 20);
            $tabela->bigInteger('escopo_id')->nullable();
            $tabela->timestamps();
        });

        Schema::create('concessoes_permissao', function (Blueprint $tabela): void {
            $tabela->id()->generatedAs()->always();
            $tabela->uuid('uuid')->unique();
            $tabela->organizacao();
            $tabela->foreignComposta('solicitacao_id', 'solicitacoes_permissao');
            $tabela->foreignComposta('acesso_id', 'acessos');
            $tabela->timestampTz('inicia_em');
            $tabela->timestampTz('expira_em');
            $tabela->timestampTz('revogada_em')->nullable();
            $tabela->foreignComposta('revogada_por_acesso_id', 'acessos')->nullable();
            $tabela->text('motivo_revogacao')->nullable();
            $tabela->timestamps();

            // Uma solicitação gera no máximo uma concessão, mesmo com decisões concorrentes (§9).
            $tabela->unique(['organizacao_id', 'solicitacao_id']);
            $tabela->index(['organizacao_id', 'acesso_id', 'expira_em']);
        });

        Schema::create('concessao_permissoes', function (Blueprint $tabela): void {
            $tabela->id()->generatedAs()->always();
            $tabela->organizacao();
            $tabela->foreignComposta('concessao_id', 'concessoes_permissao', 'cascade');
            $tabela->string('permissao', 80);
            $tabela->string('escopo_tipo', 20);
            $tabela->bigInteger('escopo_id')->nullable();
            $tabela->timestamps();
        });

        $permissoes = collect(Permissao::cases())
            ->filter(fn (Permissao $permissao): bool => $permissao->solicitavel())
            ->map(fn (Permissao $permissao): string => "'".$permissao->value."'")
            ->implode(', ');

        DB::statement("alter table solicitacoes_permissao add constraint solicitacoes_permissao_estado_valido check (estado in ('pendente', 'aprovada', 'recusada', 'cancelada', 'expirada'))");
        DB::statement('alter table solicitacoes_permissao add constraint solicitacoes_permissao_duracao_valida check (duracao_horas between 1 and 72)');
        DB::statement("alter table concessoes_permissao add constraint concessoes_permissao_ate_72h check (expira_em > inicia_em and expira_em <= inicia_em + interval '72 hours')");
        DB::statement("alter table solicitacao_permissoes add constraint solicitacao_permissoes_solicitavel check (permissao in ({$permissoes}))");
        DB::statement("alter table concessao_permissoes add constraint concessao_permissoes_solicitavel check (permissao in ({$permissoes}))");

        foreach (['solicitacao_permissoes', 'concessao_permissoes'] as $tabela) {
            DB::statement(<<<SQL
                alter table {$tabela} add constraint {$tabela}_escopo_valido check (
                    (escopo_tipo = 'organizacao' and escopo_id is null)
                    or (escopo_tipo in ('regional', 'igreja') and escopo_id is not null)
                )
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('concessao_permissoes');
        Schema::dropIfExists('concessoes_permissao');
        Schema::dropIfExists('solicitacao_permissoes');
        Schema::dropIfExists('solicitacoes_permissao');
    }
};
