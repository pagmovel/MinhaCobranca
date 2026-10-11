<?php

namespace App\Models;

use App\Enums\FormaPagamento;
use App\Enums\StatusCobranca;
use App\Support\Dinheiro;
use Carbon\CarbonImmutable;
use Database\Factories\CobrancaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'cliente_id', 'contrato_id', 'periodo_inicio', 'periodo_fim', 'vencimento', 'valor_centavos',
    'descricao', 'codigo_servico', 'codigo_nbs', 'codigo_tributacao_nacional',
    'forma_pagamento', 'chave_pix_id', 'linha_digitavel',
])]
class Cobranca extends Model
{
    /** @use HasFactory<CobrancaFactory> */
    use HasFactory;

    /** Mesmo default da migração (objeto recém-criado precisa dele antes de ser relido). */
    protected $attributes = ['status' => 'pendente', 'forma_pagamento' => 'nenhuma'];

    protected function casts(): array
    {
        return [
            'periodo_inicio' => 'immutable_date',
            'periodo_fim' => 'immutable_date',
            'vencimento' => 'immutable_date',
            'pago_em' => 'immutable_date',
            'enviado_em' => 'immutable_datetime',
            'avisado_em' => 'immutable_datetime',
            'status' => StatusCobranca::class,
            'valor_centavos' => 'integer',
            'forma_pagamento' => FormaPagamento::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function chavePix(): BelongsTo
    {
        return $this->belongsTo(ChavePix::class);
    }

    public function recibo(): HasOne
    {
        return $this->hasOne(Recibo::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(CobrancaItem::class)->orderBy('id');
    }

    public function auditorias(): HasMany
    {
        return $this->hasMany(Auditoria::class, 'alvo_id')->where('alvo_tipo', 'cobranca')->orderBy('id');
    }

    public function situacao(CarbonImmutable $hoje): string
    {
        return match ($this->status) {
            StatusCobranca::Paga => 'paga',
            StatusCobranca::Cancelada => 'cancelada',
            StatusCobranca::Pendente => match (true) {
                $this->vencimento->lt($hoje->startOfDay()) => 'vencida',
                $this->vencimento->isSameDay($hoje) => 'vence_hoje',
                default => 'a_vencer',
            },
        };
    }

    public function editavel(): bool
    {
        return $this->status === StatusCobranca::Pendente && $this->enviado_em === null;
    }

    /** Linha usada no painel e na lista de cobranças. */
    public function paraLista(CarbonImmutable $hoje): array
    {
        return [
            'id' => $this->id,
            'cliente' => $this->cliente->exibicao(),
            'cliente_oficial' => $this->cliente->nomeOficial(),
            'vencimento' => $this->vencimento->format('d/m/Y'),
            'periodo' => $this->periodo_inicio->format('d/m/Y').' a '.$this->periodo_fim->format('d/m/Y'),
            'valor' => Dinheiro::formatar($this->valor_centavos),
            'valor_centavos' => $this->valor_centavos,
            'situacao' => $this->situacao($hoje),
            'pago_em' => $this->pago_em?->format('d/m/Y'),
            'enviado' => $this->enviado_em !== null,
            'editavel' => $this->editavel(),
            // Só quando a lista carregou as auditorias (evita uma consulta por linha).
            'historico' => $this->relationLoaded('auditorias')
                ? $this->auditorias->map(fn (Auditoria $registro) => $registro->created_at->format('d/m/Y').' · '.$registro->resumo)->all()
                : [],
            'pagamento' => [
                'forma_pagamento' => $this->forma_pagamento->value,
                'chave_pix_id' => $this->chave_pix_id ?? '',
                'linha_digitavel' => $this->linha_digitavel ?? '',
            ],
            'recibo' => $this->recibo ? [
                'numero' => $this->recibo->numeroFormatado(),
                'pdf_url' => "/recibos/{$this->recibo->id}/pdf",
                'pdf_nome' => $this->recibo->nomeArquivo($this->cliente->apelido),
            ] : null,
        ];
    }
}
