<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Relacionamentos com tipos fixos e inversa gravada junto (P FR-012; U-24) e funções com período
 * (P FR-013) — OIK-SPEC-0001 T-29. Tudo sob a RLS por organização; as FKs compostas impedem apontar para
 * pessoa ou item de lista de outra organização (OIK-ADR-0002 §6). A FK de `pessoas.tipo_membro_id` já
 * existe desde a T-25.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relacionamentos', function (Blueprint $tabela): void {
            $tabela->id()->generatedAs()->always();
            $tabela->uuid('uuid')->unique();
            $tabela->organizacao();
            $tabela->foreignComposta('pessoa_id', 'pessoas', 'cascade');
            $tabela->foreignComposta('relacionada_id', 'pessoas', 'cascade');
            $tabela->string('tipo', 30);
            $tabela->text('descricao')->nullable();
            $tabela->uuid('par_uuid');
            $tabela->foreignComposta('criado_por_acesso_id', 'acessos')->nullable();
            $tabela->timestamps();

            $tabela->index(['organizacao_id', 'pessoa_id']);
            $tabela->index(['organizacao_id', 'par_uuid']);
        });

        DB::statement(<<<'SQL'
            alter table relacionamentos
                add constraint relacionamentos_tipo_valido check (tipo in (
                    'conjuge', 'pai_mae', 'filho', 'irmao', 'avo', 'neto', 'responsavel_legal', 'dependente', 'outro'
                )),
                add constraint relacionamentos_outra_pessoa check (pessoa_id <> relacionada_id),
                add constraint relacionamentos_descricao_do_outro check ((tipo = 'outro') = (descricao is not null))
        SQL);
        // "Outro" pode repetir com descrições diferentes; os tipos fixos, não.
        DB::statement(<<<'SQL'
            create unique index relacionamentos_unico_fixo on relacionamentos (organizacao_id, pessoa_id, relacionada_id, tipo)
                where tipo <> 'outro'
        SQL);

        Schema::create('pessoa_funcoes', function (Blueprint $tabela): void {
            $tabela->id()->generatedAs()->always();
            $tabela->uuid('uuid')->unique();
            $tabela->organizacao();
            $tabela->foreignComposta('pessoa_id', 'pessoas', 'cascade');
            $tabela->foreignComposta('funcao_id', 'listas_valores');
            $tabela->date('desde');
            $tabela->date('ate')->nullable();
            $tabela->foreignComposta('atribuida_por_acesso_id', 'acessos')->nullable();
            $tabela->timestamps();

            $tabela->index(['organizacao_id', 'pessoa_id']);
        });

        DB::statement('alter table pessoa_funcoes add constraint pessoa_funcoes_periodo check (ate is null or ate >= desde)');
    }

    public function down(): void
    {
        Schema::dropIfExists('pessoa_funcoes');
        Schema::dropIfExists('relacionamentos');
    }
};
