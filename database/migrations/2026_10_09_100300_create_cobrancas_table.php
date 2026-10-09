<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobrancas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained()->restrictOnDelete();
            $table->foreignId('contrato_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('periodo_inicio');
            $table->date('periodo_fim');
            $table->date('vencimento');
            $table->unsignedBigInteger('valor_centavos');
            $table->text('descricao');
            $table->string('codigo_servico')->nullable();
            $table->string('codigo_nbs', 20)->nullable();
            $table->string('codigo_tributacao_nacional', 20)->nullable();
            $table->string('status', 20)->default('pendente')->index();
            $table->date('pago_em')->nullable();
            $table->timestamp('enviado_em')->nullable();
            $table->timestamps();

            // Idempotência da geração: um período por contrato. Avulsas (contrato nulo) não colidem.
            $table->unique(['contrato_id', 'periodo_inicio']);
            $table->index(['user_id', 'vencimento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobrancas');
    }
};
