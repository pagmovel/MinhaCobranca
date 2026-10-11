<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Registro só de inclusão das ações sensíveis (corrigir/desfazer baixa, cancelar, editar, encerrar, corrigir). */
    public function up(): void
    {
        Schema::create('auditorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('acao', 40);
            $table->string('alvo_tipo', 20);
            $table->unsignedBigInteger('alvo_id');
            $table->string('resumo', 255);
            $table->json('antes')->nullable();
            $table->json('depois')->nullable();
            $table->text('motivo')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at');

            $table->index(['user_id', 'created_at']);
            $table->index(['alvo_tipo', 'alvo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias');
    }
};
