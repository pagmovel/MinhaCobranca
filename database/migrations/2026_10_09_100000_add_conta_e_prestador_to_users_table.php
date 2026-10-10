<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUNAS = [
        'is_admin', 'status', 'documento', 'nome_fantasia', 'inscricao_municipal',
        'inscricao_estadual', 'telefone', 'logradouro', 'numero', 'complemento', 'bairro',
        'cidade', 'uf', 'cep', 'logo', 'modelo_padrao', 'dias_antecedencia',
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
            $table->string('status', 20)->default('pendente')->index();
            $table->string('documento', 14)->nullable();
            $table->string('nome_fantasia')->nullable();
            $table->string('inscricao_municipal', 30)->nullable();
            $table->string('inscricao_estadual', 30)->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('logradouro')->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('complemento')->nullable();
            $table->string('bairro')->nullable();
            $table->string('cidade')->nullable();
            $table->string('uf', 2)->nullable();
            $table->string('cep', 8)->nullable();
            $table->string('logo')->nullable();
            $table->string('modelo_padrao', 20)->default('webiss');
            $table->unsignedSmallInteger('dias_antecedencia')->default(10);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(self::COLUNAS);
        });
    }
};
