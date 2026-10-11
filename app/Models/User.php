<?php

namespace App\Models;

use App\Enums\FamiliaModelo;
use App\Enums\StatusUsuario;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name', 'email', 'password', 'documento', 'nome_fantasia', 'inscricao_municipal',
    'inscricao_estadual', 'telefone', 'logradouro', 'numero', 'complemento', 'bairro',
    'cidade', 'uf', 'cep', 'modelo_padrao', 'dias_antecedencia',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Mesmos defaults da migração: o objeto recém-criado precisa deles antes de ser relido do banco. */
    protected $attributes = [
        'is_admin' => false,
        'status' => 'pendente',
        'modelo_padrao' => 'webiss',
        'dias_antecedencia' => 10,
    ];

    /** Campos sem os quais o recibo sairia com o prestador em branco. */
    public const CAMPOS_DO_PRESTADOR = [
        'name', 'documento', 'telefone', 'logradouro', 'numero', 'bairro', 'cidade', 'uf', 'cep',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'status' => StatusUsuario::class,
            'modelo_padrao' => FamiliaModelo::class,
            'dias_antecedencia' => 'integer',
        ];
    }

    public function aprovado(): bool
    {
        return $this->status === StatusUsuario::Aprovado;
    }

    public function perfilCompleto(): bool
    {
        foreach (self::CAMPOS_DO_PRESTADOR as $campo) {
            if (blank($this->{$campo})) {
                return false;
            }
        }

        return true;
    }

    public function chavesPix(): HasMany
    {
        return $this->hasMany(ChavePix::class);
    }

    /** @return list<array{id: int, rotulo: string}> */
    public function opcoesDeChavePix(): array
    {
        return $this->chavesPix()->orderBy('id')->get()
            ->map(fn (ChavePix $chave) => ['id' => $chave->id, 'rotulo' => $chave->rotulo()])
            ->all();
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class);
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class);
    }

    public function cobrancas(): HasMany
    {
        return $this->hasMany(Cobranca::class);
    }

    public function recibos(): HasMany
    {
        return $this->hasMany(Recibo::class);
    }

    public function produtosServicos(): HasMany
    {
        return $this->hasMany(ProdutoServico::class);
    }
}
