<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ultimo_periodo: o que fazer com o período que contém a data de fim (inteiro, proporcional ou nao_cobrar).
     * cobrar_a_partir_de: períodos que começam antes desta data não são gerados (nula = desde o início).
     */
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->string('ultimo_periodo', 12)->default('inteiro');
            $table->date('cobrar_a_partir_de')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn(['ultimo_periodo', 'cobrar_a_partir_de']);
        });
    }
};
