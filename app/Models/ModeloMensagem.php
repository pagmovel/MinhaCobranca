<?php

namespace App\Models;

use Database\Factories\ModeloMensagemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['nome', 'texto', 'padrao'])]
class ModeloMensagem extends Model
{
    /** @use HasFactory<ModeloMensagemFactory> */
    use HasFactory;

    protected $table = 'modelos_mensagem';

    protected function casts(): array
    {
        return ['padrao' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
