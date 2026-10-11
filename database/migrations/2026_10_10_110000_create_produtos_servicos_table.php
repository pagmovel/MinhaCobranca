<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produtos_servicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('nome', 120);
            $table->string('tipo', 20)->default('servico');
            $table->string('unidade', 20)->default('un');
            $table->unsignedBigInteger('preco_centavos')->default(0);
            $table->string('codigo_servico')->nullable();
            $table->string('codigo_nbs', 20)->nullable();
            $table->string('codigo_tributacao_nacional', 20)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'nome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produtos_servicos');
    }
};
