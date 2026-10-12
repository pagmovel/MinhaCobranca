<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Cliente informal (sem CPF/CNPJ): nulo, não string vazia — distinto no índice único (user_id, documento, apelido). */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('documento', 14)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('documento', 14)->nullable(false)->change();
        });
    }
};
