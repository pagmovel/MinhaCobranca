<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quem já existe fica com o visual atual (talão) e com o aviso dos temas pendente;
     * o model dá o tema do `.env` e marca o aviso como visto para quem se cadastra daqui para frente.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('tema', 20)->default('talao')->after('apresentacao_vista');
            $table->boolean('novidade_temas_vista')->default(false)->after('tema');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['tema', 'novidade_temas_vista']);
        });
    }
};
