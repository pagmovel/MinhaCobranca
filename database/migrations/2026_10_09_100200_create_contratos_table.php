<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained()->restrictOnDelete();
            $table->text('descricao');
            $table->string('codigo_servico')->nullable();
            $table->string('codigo_nbs', 20)->nullable();
            $table->string('codigo_tributacao_nacional', 20)->nullable();
            $table->unsignedBigInteger('valor_centavos');
            $table->string('periodicidade', 20);
            $table->unsignedTinyInteger('dia_vencimento');
            $table->date('data_inicio');
            $table->date('data_fim')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratos');
    }
};
