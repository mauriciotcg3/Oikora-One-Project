<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Só o necessário para a RLS; a T-08 completa estado, fuso, logo e demais campos.
        Schema::create('organizacoes', function (Blueprint $tabela): void {
            $tabela->id()->generatedAs()->always();
            $tabela->uuid('uuid')->unique();
            $tabela->string('nome');
            $tabela->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizacoes');
    }
};
