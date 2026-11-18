<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contato de resposta da organização: o "responder-para" dos e-mails de aviso (OIK-ADR-0008 §9), mantido
 * na configuração da organização (T-24).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizacoes', function (Blueprint $tabela): void {
            $tabela->string('email_contato')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organizacoes', function (Blueprint $tabela): void {
            $tabela->dropColumn('email_contato');
        });
    }
};
