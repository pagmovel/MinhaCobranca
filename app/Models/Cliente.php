<?php

namespace App\Models;

use App\Enums\FamiliaModelo;
use App\Support\Busca;
use App\Support\Documento;
use Database\Factories\ClienteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'nome', 'documento', 'inscricao_municipal', 'inscricao_estadual', 'telefone', 'email',
    'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'cep', 'modelo',
])]
class Cliente extends Model
{
    /** @use HasFactory<ClienteFactory> */
    use HasFactory;

    /** Dados do tomador que vão para o snapshot do recibo e para o formulário. */
    public const CAMPOS = [
        'nome', 'documento', 'inscricao_municipal', 'inscricao_estadual', 'telefone', 'email',
        'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'cep',
    ];

    protected function casts(): array
    {
        return ['modelo' => FamiliaModelo::class];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class);
    }

    public function cobrancas(): HasMany
    {
        return $this->hasMany(Cobranca::class);
    }

    /** Nome contém o termo (sem diferença de maiúsculas) ou o documento começa com ele. Lista e autosugestão. */
    public function scopeComTermo(Builder $consulta, string $termo): void
    {
        $documento = Documento::normalizar($termo);

        $consulta->where(function (Builder $consulta) use ($termo, $documento) {
            $consulta->whereRaw("lower(nome) like ? escape '\\'", [Busca::contem($termo)]);

            if ($documento !== '') {
                $consulta->orWhere('documento', 'like', "{$documento}%");
            }
        });
    }
}
