<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O payload do job leva só o id (OIK-ADR-0008 §3), então o código precisa ficar legível até a entrega.
     * Guardá-lo cifrado permite que uma nova tentativa envie o mesmo código; a coluna é limpa depois.
     */
    public function up(): void
    {
        Schema::table('codigos_verificacao', function (Blueprint $tabela): void {
            $tabela->text('codigo_cifrado')->nullable();
            $tabela->index(['conta_id', 'finalidade']);
            $tabela->index('codigo_hash');
        });
    }

    public function down(): void
    {
        Schema::table('codigos_verificacao', function (Blueprint $tabela): void {
            $tabela->dropIndex(['conta_id', 'finalidade']);
            $tabela->dropIndex(['codigo_hash']);
            $tabela->dropColumn('codigo_cifrado');
        });
    }
};
