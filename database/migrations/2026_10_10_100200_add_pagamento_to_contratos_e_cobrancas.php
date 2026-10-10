<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['contratos', 'cobrancas'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) use ($tabela) {
                // Existentes ficam "não informar": nenhum recibo ganha bloco de pagamento sem o usuário escolher.
                $table->string('forma_pagamento', 10)->default('nenhuma');
                $table->foreignId('chave_pix_id')->nullable()->constrained('chaves_pix')->restrictOnDelete();

                if ($tabela === 'cobrancas') {
                    $table->string('linha_digitavel', 48)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['contratos', 'cobrancas'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) use ($tabela) {
                $table->dropConstrainedForeignId('chave_pix_id');
                $table->dropColumn($tabela === 'cobrancas' ? ['forma_pagamento', 'linha_digitavel'] : ['forma_pagamento']);
            });
        }
    }
};
