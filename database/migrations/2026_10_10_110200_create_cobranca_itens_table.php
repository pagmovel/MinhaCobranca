<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobranca_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cobranca_id')->constrained()->cascadeOnDelete();
            // Referência: o que vale é a cópia abaixo (o item pode mudar de nome ou de preço depois).
            $table->foreignId('produto_servico_id')->nullable()->constrained('produtos_servicos')->nullOnDelete();
            $table->string('nome', 120);
            $table->string('unidade', 20);
            $table->decimal('quantidade', 10, 3);
            $table->unsignedBigInteger('preco_unitario_centavos');
            $table->unsignedBigInteger('total_centavos');
            $table->boolean('principal')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobranca_itens');
    }
};
