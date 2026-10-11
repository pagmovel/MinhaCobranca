<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flag opcional de quando usar o modelo automaticamente (pedido do usuário, 2026-10-11): a_vencer, vence_hoje
 * ("VENCENDO" na tela) ou vencida — mesmos valores de Cobranca::situacao(). Nula = não entra na pré-seleção
 * automática, continua só disponível para escolha manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modelos_mensagem', function (Blueprint $table) {
            $table->string('tipo_situacao', 20)->nullable()->after('padrao');
        });
    }

    public function down(): void
    {
        Schema::table('modelos_mensagem', function (Blueprint $table) {
            $table->dropColumn('tipo_situacao');
        });
    }
};
