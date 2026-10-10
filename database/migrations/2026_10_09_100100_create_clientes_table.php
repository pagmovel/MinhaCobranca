<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('nome');
            $table->string('documento', 14);
            $table->string('inscricao_municipal', 30)->nullable();
            $table->string('inscricao_estadual', 30)->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('logradouro')->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('complemento')->nullable();
            $table->string('bairro')->nullable();
            $table->string('cidade')->nullable();
            $table->string('uf', 2)->nullable();
            $table->string('cep', 8)->nullable();
            $table->string('modelo', 20);
            $table->timestamps();

            $table->unique(['user_id', 'documento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
