<?php

namespace App\Models;

use App\Enums\FamiliaModelo;
use App\Enums\StatusUsuario;
use App\Enums\TemaVisual;
use App\Support\DiasUteis;
use App\Support\Ufs;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name', 'email', 'password', 'documento', 'nome_fantasia', 'inscricao_municipal',
    'inscricao_estadual', 'telefone', 'logradouro', 'numero', 'complemento', 'bairro',
    'cidade', 'uf', 'cep', 'modelo_padrao', 'dias_antecedencia', 'dias_aviso', 'dias_aviso_contrato',
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
        'dias_aviso' => 5,
        'dias_aviso_contrato' => 30,
        'apresentacao_vista' => false,
    ];

    /** Campos sem os quais o recibo sairia com o prestador em branco. */
    public const CAMPOS_DO_PRESTADOR = [
        'name', 'documento', 'telefone', 'logradouro', 'numero', 'bairro', 'cidade', 'uf', 'cep',
    ];

    protected static function booted(): void
    {
        // Sem default em $attributes de propósito: o tema novo vem do .env e a novidade dos temas já nasce vista.
        static::creating(function (User $usuario) {
            $usuario->tema = $usuario->getAttributes()['tema'] ?? TemaVisual::padrao();
            $usuario->novidade_temas_vista ??= true;
        });
    }

    /**
     * Tema como enum. Não é um cast de enum puro porque `from()` estoura com valor desconhecido (coluna
     * corrompida, caso removido do enum) e isso derrubaria toda página: aqui cai no Talão.
     *
     * @return Attribute<TemaVisual, TemaVisual|string>
     */
    protected function tema(): Attribute
    {
        return Attribute::make(
            get: fn (?string $valor) => TemaVisual::tryFrom((string) $valor) ?? TemaVisual::Talao,
            set: fn (TemaVisual|string $valor) => $valor instanceof TemaVisual ? $valor->value : $valor,
        );
    }

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
            'dias_aviso' => 'integer',
            'dias_aviso_contrato' => 'integer',
            'apresentacao_vista' => 'boolean',
            'novidade_temas_vista' => 'boolean',
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

    /**
     * Opções do cadastro rápido de cliente (modal) nos formulários de contrato e cobrança.
     *
     * @return array{modeloPadrao: string, modelos: list<array{valor: string, rotulo: string}>, ufs: list<string>}
     */
    public function opcoesDeCadastroRapido(): array
    {
        return [
            'modeloPadrao' => $this->modelo_padrao->value,
            'modelos' => FamiliaModelo::opcoes(),
            'ufs' => Ufs::TODAS,
        ];
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

    public function auditorias(): HasMany
    {
        return $this->hasMany(Auditoria::class);
    }

    public function recibos(): HasMany
    {
        return $this->hasMany(Recibo::class);
    }

    public function produtosServicos(): HasMany
    {
        return $this->hasMany(ProdutoServico::class);
    }

    public function feriados(): HasMany
    {
        return $this->hasMany(Feriado::class)->orderBy('id');
    }

    public function modelosMensagem(): HasMany
    {
        return $this->hasMany(ModeloMensagem::class)->orderBy('id');
    }

    /** @return list<array{id: int, nome: string, texto: string, padrao: bool, tipo_situacao: ?string}> */
    public function opcoesDeModelosMensagem(): array
    {
        return $this->modelosMensagem()->get()
            ->map(fn (ModeloMensagem $modelo) => [
                'id' => $modelo->id, 'nome' => $modelo->nome, 'texto' => $modelo->texto, 'padrao' => $modelo->padrao,
                'tipo_situacao' => $modelo->tipo_situacao?->value,
            ])
            ->all();
    }

    /** Calendário do prestador: nacionais + a lista local dele (uma consulta). */
    public function diasUteis(): DiasUteis
    {
        return new DiasUteis($this->feriados()->get()->map(fn (Feriado $feriado) => $feriado->paraCalendario())->all());
    }
}
