<?php

use App\Modules\Avisos\Domain\EstadoEnvio;
use App\Modules\Avisos\Infrastructure\Console\CriarParticoesEnvios;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Caixa de saída transacional (OIK-ADR-0008 §2, §4; plan.md §3.6). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avisos', function (Blueprint $tabela): void {
            $tabela->id()->generatedAs()->always();
            $tabela->uuid('uuid')->unique();
            $tabela->organizacao();
            $tabela->string('tipo', 60);
            $tabela->foreignId('conta_id')->nullable()->constrained('contas');
            $tabela->foreignComposta('pessoa_id', 'pessoas')->nullable();
            // Quem recebe, para a chave de idempotência: `conta:ID` ou `pessoa:ID`.
            $tabela->string('destinatario', 40);
            $tabela->string('referencia_tipo', 40);
            $tabela->string('referencia_id', 80);
            $tabela->string('chave', 80)->default('');
            // Caminho do link universal (OIK-ADR-0008 §7); o texto é montado na leitura.
            $tabela->string('destino')->nullable();
            $tabela->timestampTz('lido_em')->nullable();
            $tabela->timestampsTz();
            $tabela->unique(['organizacao_id', 'tipo', 'referencia_tipo', 'referencia_id', 'destinatario', 'chave'], 'avisos_idempotencia');
            $tabela->index(['organizacao_id', 'conta_id', 'lido_em']);
        });

        DB::statement("alter table avisos add constraint avisos_destinatario_valido check (destinatario ~ '^(conta|pessoa):[0-9]+$')");

        $estados = implode(', ', array_map(static fn (EstadoEnvio $e): string => "'{$e->value}'", EstadoEnvio::cases()));

        // Particionada por mês do aviso: a unicidade (aviso_id, canal) precisa da chave de partição, e
        // `aviso_criado_em` é a mesma para todos os envios do aviso.
        DB::unprepared(<<<SQL
            create table envios (
                id bigint generated always as identity,
                organizacao_id bigint not null references organizacoes(id),
                aviso_id bigint not null,
                aviso_criado_em timestamptz not null,
                canal varchar(20) not null,
                provedor varchar(30) null,
                destino_mascarado varchar(120) null,
                destino_hash char(64) null,
                id_externo varchar(120) null,
                estado varchar(20) not null default 'pendente',
                tentativas smallint not null default 0,
                erro_codigo varchar(60) null,
                agendado_para timestamptz null,
                enfileirado_em timestamptz null,
                enviando_em timestamptz null,
                enviado_em timestamptz null,
                entregue_em timestamptz null,
                lido_em timestamptz null,
                falhou_em timestamptz null,
                request_id varchar(128) null,
                created_at timestamptz not null default now(),
                updated_at timestamptz not null default now(),
                primary key (id, aviso_criado_em),
                constraint envios_aviso_canal_unico unique (aviso_id, canal, aviso_criado_em),
                constraint envios_canal_valido check (canal in ('email', 'whatsapp')),
                constraint envios_estado_valido check (estado in ({$estados})),
                foreign key (organizacao_id, aviso_id) references avisos (organizacao_id, id)
            ) partition by range (aviso_criado_em);

            create index envios_pendentes_idx on envios (estado, enfileirado_em) where estado = 'pendente';
            create index envios_id_idx on envios (id);
            alter table envios enable row level security;
            alter table envios force row level security;
            create policy isolamento_organizacao on envios
                using (organizacao_id = (select app_organizacao_atual()))
                with check (organizacao_id = (select app_organizacao_atual()));
        SQL);

        $inicio = Carbon::now('UTC')->startOfMonth();
        foreach ([$inicio, $inicio->copy()->addMonth()] as $mes) {
            foreach (CriarParticoesEnvios::comandosDaParticao($mes) as $comando) {
                DB::statement($comando);
            }
        }
    }

    public function down(): void
    {
        DB::unprepared('drop table if exists envios cascade;');
        Schema::dropIfExists('avisos');
    }
};
