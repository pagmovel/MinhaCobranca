<?php

namespace App\Models;

use App\Enums\TipoChavePix;
use Database\Factories\ChavePixFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tipo', 'chave', 'apelido'])]
class ChavePix extends Model
{
    /** @use HasFactory<ChavePixFactory> */
    use HasFactory;

    protected $table = 'chaves_pix';

    protected function casts(): array
    {
        return ['tipo' => TipoChavePix::class];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Como aparece nas listas de escolha: "Nubank PJ — 11.222.333/0001-81". */
    public function rotulo(): string
    {
        return ($this->apelido ?: $this->tipo->rotulo()).' — '.$this->tipo->formatar($this->chave);
    }
}
