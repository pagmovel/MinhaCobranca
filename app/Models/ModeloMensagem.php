<?php

namespace App\Models;

use App\Enums\TipoAvisoModelo;
use Database\Factories\ModeloMensagemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['nome', 'texto', 'padrao', 'tipo_situacao'])]
class ModeloMensagem extends Model
{
    /** @use HasFactory<ModeloMensagemFactory> */
    use HasFactory;

    protected $table = 'modelos_mensagem';

    protected function casts(): array
    {
        return ['padrao' => 'boolean', 'tipo_situacao' => TipoAvisoModelo::class];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
