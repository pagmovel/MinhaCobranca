<?php

namespace App\Models;

use App\Enums\FormaPagamento;
use App\Enums\Modalidade;
use App\Enums\Periodicidade;
use Database\Factories\ContratoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'cliente_id', 'descricao', 'codigo_servico', 'codigo_nbs', 'codigo_tributacao_nacional',
    'valor_centavos', 'periodicidade', 'modalidade', 'dia_vencimento', 'data_inicio', 'data_fim', 'ativo', 'forma_pagamento', 'chave_pix_id',
    'ultimo_periodo', 'cobrar_a_partir_de',
    'desconto_pontualidade_tipo', 'desconto_pontualidade_valor', 'multa_tipo', 'multa_valor', 'juros_tipo', 'juros_valor',
])]
class Contrato extends Model
{
    /** @use HasFactory<ContratoFactory> */
    use HasFactory;

    /** Mesmo default da migração (objeto recém-criado precisa dele antes de ser relido). */
    protected $attributes = ['modalidade' => 'prepago', 'forma_pagamento' => 'nenhuma', 'ultimo_periodo' => 'inteiro'];

    protected function casts(): array
    {
        return [
            'valor_centavos' => 'integer',
            'periodicidade' => Periodicidade::class,
            'modalidade' => Modalidade::class,
            'dia_vencimento' => 'integer',
            'data_inicio' => 'immutable_date',
            'data_fim' => 'immutable_date',
            'cobrar_a_partir_de' => 'immutable_date',
            'ativo' => 'boolean',
            'forma_pagamento' => FormaPagamento::class,
            'desconto_pontualidade_valor' => 'integer',
            'multa_valor' => 'integer',
            'juros_valor' => 'integer',
        ];
    }

    /**
     * As condições de pagamento do contrato, ou null se não tem nenhuma. Regra sem tipo ou com valor 0 é sem regra.
     *
     * @return ?array{desconto: ?array{tipo: string, valor: int}, multa: ?array{tipo: string, valor: int}, juros: ?array{tipo: string, valor: int}}
     */
    public function regrasDePagamento(): ?array
    {
        $regras = [];

        foreach (['desconto' => 'desconto_pontualidade', 'multa' => 'multa', 'juros' => 'juros'] as $nome => $coluna) {
            $tipo = $this->{"{$coluna}_tipo"};
            $valor = $this->{"{$coluna}_valor"};
            $regras[$nome] = $tipo !== null && $valor > 0 ? ['tipo' => $tipo, 'valor' => $valor] : null;
        }

        return array_filter($regras) === [] ? null : $regras;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function chavePix(): BelongsTo
    {
        return $this->belongsTo(ChavePix::class);
    }

    public function cobrancas(): HasMany
    {
        return $this->hasMany(Cobranca::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(ContratoItem::class)->orderBy('ordem');
    }
}
