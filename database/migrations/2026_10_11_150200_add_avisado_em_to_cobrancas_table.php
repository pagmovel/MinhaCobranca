<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cobrancas', function (Blueprint $table) {
            $table->timestamp('avisado_em')->nullable()->after('enviado_em');
        });
    }

    public function down(): void
    {
        Schema::table('cobrancas', function (Blueprint $table) {
            $table->dropColumn('avisado_em');
        });
    }
};
