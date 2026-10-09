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

    public function nomeArquivo(): string
    {
        return sprintf('recibo-%d-%06d-%s.pdf', $this->ano, $this->numero, Str::slug($this->snapshot['tomador']['nome'] ?? 'cliente'));
    }
}
