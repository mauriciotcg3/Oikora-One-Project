<?php

use App\Modules\Autorizacao\Domain\Permissao;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('papeis', function (Blueprint $tabela): void {
            $tabela->id()->generatedAs()->always();
            $tabela->uuid('uuid')->unique();
            $tabela->organizacao();
            $tabela->string('nome');
            $tabela->string('sistema', 30)->nullable();
            $tabela->timestamps();
            $tabela->unique(['organizacao_id', 'nome']);
        });

        Schema::create('papel_permissoes', function (Blueprint $tabela): void {
            $tabela->id()->generatedAs()->always();
            $tabela->organizacao();
            $tabela->foreignComposta('papel_id', 'papeis', 'cascade');
            $tabela->string('permissao', 80);
            $tabela->unique(['organizacao_id', 'papel_id', 'permissao']);
        });

        Schema::create('atribuicoes_papel', function (Blueprint $tabela): void {
            $tabela->id()->generatedAs()->always();
            $tabela->uuid('uuid')->unique();
            $tabela->organizacao();
            $tabela->foreignComposta('acesso_id', 'acessos', 'cascade');
            $tabela->foreignComposta('papel_id', 'papeis', 'cascade');
            $tabela->string('escopo_tipo', 20);
            $tabela->bigInteger('escopo_id')->nullable();
            $tabela->timestamps();
            $tabela->unique(['organizacao_id', 'acesso_id', 'papel_id', 'escopo_tipo', 'escopo_id']);
        });

        $permissoes = collect(Permissao::cases())
            ->map(fn (Permissao $permissao): string => "'".str_replace("'", "''", $permissao->value)."'")
            ->implode(', ');

        DB::statement("alter table papel_permissoes add constraint papel_permissoes_catalogo check (permissao in ({$permissoes}))");
        DB::statement("alter table papeis add constraint papeis_sistema_valido check (sistema in ('administrador', 'secretaria'))");
        DB::statement(<<<'SQL'
            alter table atribuicoes_papel add constraint atribuicoes_papel_escopo_valido check (
                (escopo_tipo = 'organizacao' and escopo_id is null)
                or (escopo_tipo in ('regional', 'igreja') and escopo_id is not null)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('atribuicoes_papel');
        Schema::dropIfExists('papel_permissoes');
        Schema::dropIfExists('papeis');
    }
};
