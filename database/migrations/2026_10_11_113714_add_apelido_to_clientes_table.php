<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Apelido do cliente (T17.13): o mesmo CPF/CNPJ pode ter mais de um cadastro se o apelido for diferente (cada regional
 * é um cliente). Vazio e não nulo: com nulo, dois cadastros sem apelido passariam pelo índice único.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('apelido', 60)->default('')->after('nome');
            $table->dropUnique(['user_id', 'documento']);
            $table->unique(['user_id', 'documento', 'apelido']);
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'documento', 'apelido']);
            $table->unique(['user_id', 'documento']);
            $table->dropColumn('apelido');
        });
    }
};
