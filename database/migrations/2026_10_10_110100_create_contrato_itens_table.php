<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contrato_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained()->cascadeOnDelete();
            $table->foreignId('produto_servico_id')->constrained('produtos_servicos')->restrictOnDelete();
            $table->decimal('quantidade', 10, 3);
            $table->unsignedBigInteger('preco_unitario_centavos');
            $table->boolean('principal')->default(false);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrato_itens');
    }
};
