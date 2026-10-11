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
    'nome', 'apelido', 'contato', 'documento', 'inscricao_municipal', 'inscricao_estadual', 'telefone', 'email',
    'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'cep', 'modelo',
])]
class Cliente extends Model
{
    /** @use HasFactory<ClienteFactory> */
    use HasFactory;

    /** Dados do tomador que vão para o snapshot do recibo e para o formulário. O apelido não entra: é só das telas. */
    public const CAMPOS = [
        'nome', 'documento', 'inscricao_municipal', 'inscricao_estadual', 'telefone', 'email',
        'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'cep',
    ];

    /** Mesmo padrão da migração: o model recém-criado já tem o apelido vazio, sem reler do banco. */
    protected $attributes = ['apelido' => ''];

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

    /** Como o cliente aparece nas telas: o apelido (nome curto), ou o nome se não houver. */
    public function exibicao(): string
    {
        return $this->apelido ?: $this->nome;
    }

    /** Nome oficial para mostrar embaixo, menor: só quando o apelido tomou o lugar dele. */
    public function nomeOficial(): ?string
    {
        return $this->apelido ? $this->nome : null;
    }

    /**
     * Formato da autosugestão e dos filtros.
     *
     * @return array{id: int, nome: string, nome_oficial: ?string, documento: string}
     */
    public function paraBusca(): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->exibicao(),
            'nome_oficial' => $this->nomeOficial(),
            'documento' => Documento::formatar($this->documento),
        ];
    }

    /** Ordem de lista pelo que aparece na tela (apelido ou nome). SQL padrão: vale no PostgreSQL e no SQLite. */
    public function scopeOrdemDeExibicao(Builder $consulta): void
    {
        $consulta->orderByRaw("coalesce(nullif(apelido, ''), nome)");
    }

    /** Nome ou apelido contém o termo (sem diferença de maiúsculas) ou o documento começa com ele. Lista e autosugestão. */
    public function scopeComTermo(Builder $consulta, string $termo): void
    {
        $documento = Documento::normalizar($termo);

        $consulta->where(function (Builder $consulta) use ($termo, $documento) {
            $consulta->whereRaw("lower(nome) like ? escape '\\'", [Busca::contem($termo)])
                ->orWhereRaw("lower(apelido) like ? escape '\\'", [Busca::contem($termo)]);

            if ($documento !== '') {
                $consulta->orWhere('documento', 'like', "{$documento}%");
            }
        });
    }
}
