<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tudo opcional: vazio é sem regra. O valor é em pontos-base (15% = 1500) ou em centavos, conforme o tipo. */
    private const COLUNAS = [
        'desconto_pontualidade_tipo', 'desconto_pontualidade_valor',
        'multa_tipo', 'multa_valor',
        'juros_tipo', 'juros_valor',
    ];

    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            foreach (['desconto_pontualidade', 'multa', 'juros'] as $regra) {
                $table->string("{$regra}_tipo")->nullable();
                $table->unsignedBigInteger("{$regra}_valor")->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn(self::COLUNAS);
        });
    }
};
