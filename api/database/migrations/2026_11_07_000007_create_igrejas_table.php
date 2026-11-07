<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('igrejas', function (Blueprint $tabela): void {
            $tabela->id()->generatedAs()->always();
            $tabela->uuid('uuid')->unique();
            $tabela->organizacao();
            $tabela->foreignComposta('regional_id', 'regionais')->nullable();
            $tabela->string('nome');
            $tabela->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('igrejas');
    }
};
