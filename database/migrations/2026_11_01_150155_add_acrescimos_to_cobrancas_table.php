<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cobrancas', function (Blueprint $table) {
            $table->unsignedBigInteger('multa_centavos')->default(0)->after('desconto_centavos');
            $table->unsignedBigInteger('juros_centavos')->default(0)->after('multa_centavos');
            $table->unsignedBigInteger('outros_centavos')->default(0)->after('juros_centavos');
        });
    }

    public function down(): void
    {
        Schema::table('cobrancas', function (Blueprint $table) {
            $table->dropColumn(['multa_centavos', 'juros_centavos', 'outros_centavos']);
        });
    }
};
