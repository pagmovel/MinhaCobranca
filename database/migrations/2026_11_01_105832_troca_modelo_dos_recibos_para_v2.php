<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fase 26: o v2 substitui o v1 (sem desconto sai idêntico). Troca só o campo `modelo`; número, snapshot e datas ficam.
 * Escreve em todos os recibos: em produção roda sozinha no deploy (`migrate --force`).
 * `DB::table` de propósito: o enum `ModeloRecibo` já não tem os casos v1, e o cast quebraria ao ler a linha antiga.
 */
return new class extends Migration
{
    private const TROCAS = ['webiss-v1' => 'webiss-v2', 'danfse-v1' => 'danfse-v2'];

    public function up(): void
    {
        foreach (self::TROCAS as $antigo => $novo) {
            DB::table('recibos')->where('modelo', $antigo)->update(['modelo' => $novo]);
        }
    }

    public function down(): void
    {
        foreach (self::TROCAS as $antigo => $novo) {
            DB::table('recibos')->where('modelo', $novo)->update(['modelo' => $antigo]);
        }
    }
};
