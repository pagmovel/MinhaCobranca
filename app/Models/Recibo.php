<?php

namespace App\Models;

use App\Enums\ModeloRecibo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['user_id', 'ano', 'numero', 'modelo', 'emitido_em', 'snapshot'])]
class Recibo extends Model
{
    protected function casts(): array
    {
        return [
            'ano' => 'integer',
            'numero' => 'integer',
            'modelo' => ModeloRecibo::class,
            'emitido_em' => 'immutable_datetime',
            'snapshot' => 'array',
        ];
    }

    public function cobranca(): BelongsTo
    {
        return $this->belongsTo(Cobranca::class);
    }

    public function numeroFormatado(): string
    {
        return sprintf('%d/%06d', $this->ano, $this->numero);
    }

    /**
     * Nome do arquivo do PDF. Com o apelido atual do cliente, o nome fica curto quando a razão social é extensa (pedido do
     * usuário, 2026-10-11); só o nome do arquivo usa o apelido, o conteúdo do recibo não.
     */
    public function nomeArquivo(?string $apelido = null): string
    {
        return sprintf('recibo-%d-%06d-%s.pdf', $this->ano, $this->numero, Str::slug($apelido ?: ($this->snapshot['tomador']['nome'] ?? 'cliente')));
    }
}
