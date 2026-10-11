<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Pessoa de contato (ex.: "Wendel") para mensagens menos formais — não entra no snapshot do recibo, mesmo padrão do apelido. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('contato', 100)->nullable()->after('apelido');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('contato');
        });
    }
};
