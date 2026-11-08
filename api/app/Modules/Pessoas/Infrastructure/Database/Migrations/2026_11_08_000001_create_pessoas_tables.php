<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pessoas', function (Blueprint $tabela): void {
            $tabela->id()->generatedAs()->always();
            $tabela->uuid('uuid')->unique();
            $tabela->organizacao();
            $tabela->foreignComposta('igreja_id', 'igrejas');
            $tabela->string('nome');
            $tabela->string('nome_busca');
            $tabela->date('nascimento')->nullable();
            $tabela->string('sexo', 30)->nullable();
            $tabela->string('foto_path')->nullable();
            $tabela->string('estado_civil', 30)->nullable();
            $tabela->date('data_casamento')->nullable();
            $tabela->text('cpf')->nullable();
            $tabela->text('rg')->nullable();
            $tabela->text('rg_pais')->nullable();
            $tabela->text('necessidades_especiais')->nullable();
            $tabela->text('tipo_sanguineo')->nullable();
            $tabela->char('cpf_hash', 64)->nullable();
            $tabela->boolean('aceitou_jesus')->nullable();
            $tabela->date('conversao_data')->nullable();
            $tabela->string('conversao_canal')->nullable();
            $tabela->date('batismo_data')->nullable();
            $tabela->string('batizado_por')->nullable();
            $tabela->string('igreja_origem')->nullable();
            $tabela->boolean('e_lider')->default(false);
            $tabela->boolean('e_pastor')->default(false);
            $tabela->boolean('novo_convertido')->default(false);
            $tabela->string('escolaridade')->nullable();
            $tabela->string('profissao')->nullable();
            $tabela->string('nacionalidade')->nullable();
            $tabela->string('naturalidade')->nullable();
            $tabela->string('vinculo', 30)->nullable();
            $tabela->string('situacao', 30)->nullable();
            $tabela->bigInteger('tipo_membro_id')->nullable();
            $tabela->string('matricula')->nullable();
            $tabela->date('entrada_em')->nullable();
            $tabela->string('tipo_entrada', 40)->nullable();
            $tabela->bigInteger('como_chegou_id')->nullable();
            $tabela->timestampTz('excluida_em')->nullable();
            $tabela->foreignComposta('excluida_por_acesso_id', 'acessos')->nullable();
            $tabela->text('exclusao_motivo')->nullable();
            $tabela->timestamps();

            $tabela->unique(['organizacao_id', 'cpf_hash']);
            $tabela->unique(['organizacao_id', 'matricula']);
            $tabela->index(['organizacao_id', 'igreja_id']);
        });

        DB::statement('create index pessoas_nome_busca_trgm on pessoas using gin (nome_busca gin_trgm_ops)');

        Schema::create('pessoa_contatos', function (Blueprint $tabela): void {
            $tabela->id()->generatedAs()->always();
            $tabela->uuid('uuid')->unique();
            $tabela->organizacao();
            $tabela->foreignComposta('pessoa_id', 'pessoas');
            $tabela->string('tipo', 20);
            $tabela->string('valor');
            $tabela->boolean('e_whatsapp')->default(false);
            $tabela->boolean('principal')->default(false);
            $tabela->timestampTz('entrega_falhou_em')->nullable();
            $tabela->timestampTz('descadastrado_em')->nullable();
            $tabela->timestamps();

            $tabela->index(['organizacao_id', 'pessoa_id']);
        });

        Schema::create('pessoa_enderecos', function (Blueprint $tabela): void {
            $tabela->id()->generatedAs()->always();
            $tabela->uuid('uuid')->unique();
            $tabela->organizacao();
            $tabela->foreignComposta('pessoa_id', 'pessoas');
            $tabela->string('cep', 20)->nullable();
            $tabela->string('logradouro')->nullable();
            $tabela->string('numero', 30)->nullable();
            $tabela->string('complemento')->nullable();
            $tabela->string('bairro')->nullable();
            $tabela->string('cidade')->nullable();
            $tabela->string('estado', 80)->nullable();
            $tabela->string('pais', 80)->nullable();
            $tabela->timestamps();

            $tabela->index(['organizacao_id', 'pessoa_id']);
        });

        DB::statement("alter table pessoa_contatos add constraint pessoa_contatos_tipo_valido check (tipo in ('celular', 'telefone', 'email'))");

        Schema::table('acessos', function (Blueprint $tabela): void {
            $tabela->foreign(['organizacao_id', 'pessoa_id'])
                ->references(['organizacao_id', 'id'])
                ->on('pessoas');
        });
    }

    public function down(): void
    {
        Schema::table('acessos', function (Blueprint $tabela): void {
            $tabela->dropForeign(['organizacao_id', 'pessoa_id']);
        });

        Schema::dropIfExists('pessoa_enderecos');
        Schema::dropIfExists('pessoa_contatos');
        Schema::dropIfExists('pessoas');
    }
};
