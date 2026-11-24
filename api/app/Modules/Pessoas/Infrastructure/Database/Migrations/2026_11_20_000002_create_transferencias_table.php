<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pedidos de transferência entre igrejas (P FR-031, FLOW-009; RN-08; U-21) — OIK-SPEC-0001 T-34. Um único
 * pedido `solicitada` por pessoa, garantido também no banco (único parcial). Sob a RLS por organização.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transferencias', function (Blueprint $tabela): void {
            $tabela->id()->generatedAs()->always();
            $tabela->uuid('uuid')->unique();
            $tabela->organizacao();
            $tabela->foreignComposta('pessoa_id', 'pessoas', 'cascade');
            $tabela->foreignComposta('igreja_origem_id', 'igrejas');
            $tabela->foreignComposta('igreja_destino_id', 'igrejas');
            $tabela->string('estado', 20);
            $tabela->foreignComposta('solicitada_por_acesso_id', 'acessos');
            $tabela->foreignComposta('decidida_por_acesso_id', 'acessos')->nullable();
            $tabela->timestampTz('decidida_em')->nullable();
            $tabela->text('justificativa')->nullable();
            $tabela->timestampsTz();

            $tabela->index(['organizacao_id', 'estado']);
        });

        DB::statement(<<<'SQL'
            alter table transferencias
                add constraint transferencias_estado_valido check (estado in ('solicitada', 'aprovada', 'recusada')),
                add constraint transferencias_igrejas_distintas check (igreja_origem_id <> igreja_destino_id),
                add constraint transferencias_recusa_justificada check (estado <> 'recusada' or justificativa is not null)
        SQL);
        DB::statement(<<<'SQL'
            create unique index transferencias_uma_pendente on transferencias (organizacao_id, pessoa_id)
                where estado = 'solicitada'
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('transferencias');
    }
};
