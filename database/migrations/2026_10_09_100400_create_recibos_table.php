<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recibos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cobranca_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('ano');
            $table->unsignedInteger('numero');
            $table->string('modelo', 20);
            $table->timestamp('emitido_em');
            $table->json('snapshot');
            $table->timestamps();

            $table->unique(['user_id', 'ano', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recibos');
    }
};
