<?php

namespace App\Models;

use App\Enums\StatusCobranca;
use App\Support\Dinheiro;
use Carbon\CarbonImmutable;
use Database\Factories\CobrancaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'cliente_id', 'contrato_id', 'periodo_inicio', 'periodo_fim', 'vencimento', 'valor_centavos',
    'descricao', 'codigo_servico', 'codigo_nbs', 'codigo_tributacao_nacional',
])]
class Cobranca extends Model
{
    /** @use HasFactory<CobrancaFactory> */
    use HasFactory;

    /** Mesmo default da migração (objeto recém-criado precisa dele antes de ser relido). */
    protected $attributes = ['status' => 'pendente'];

    protected function casts(): array
    {
        return [
            'periodo_inicio' => 'immutable_date',
            'periodo_fim' => 'immutable_date',
            'vencimento' => 'immutable_date',
            'pago_em' => 'immutable_date',
            'enviado_em' => 'immutable_datetime',
            'status' => StatusCobranca::class,
            'valor_centavos' => 'integer',
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

    public function recibo(): HasOne
    {
        return $this->hasOne(Recibo::class);
    }

    public function situacao(CarbonImmutable $hoje): string
    {
        return match ($this->status) {
            StatusCobranca::Paga => 'paga',
            StatusCobranca::Cancelada => 'cancelada',
            StatusCobranca::Pendente => $this->vencimento->lt($hoje->startOfDay()) ? 'vencida' : 'a_vencer',
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
            'cliente' => $this->cliente->nome,
            'vencimento' => $this->vencimento->format('d/m/Y'),
            'periodo' => $this->periodo_inicio->format('d/m/Y').' a '.$this->periodo_fim->format('d/m/Y'),
            'valor' => Dinheiro::formatar($this->valor_centavos),
            'situacao' => $this->situacao($hoje),
            'pago_em' => $this->pago_em?->format('d/m/Y'),
            'enviado' => $this->enviado_em !== null,
            'editavel' => $this->editavel(),
            'recibo' => $this->recibo ? [
                'numero' => $this->recibo->numeroFormatado(),
                'pdf_url' => "/recibos/{$this->recibo->id}/pdf",
                'pdf_nome' => $this->recibo->nomeArquivo(),
            ] : null,
        ];
    }
}
