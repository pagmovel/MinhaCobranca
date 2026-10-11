<?php

namespace App\Models;

use App\Support\DiasUteis;
use Database\Factories\FeriadoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Feriado local do prestador (estadual ou municipal), que a regra do dia útil soma aos nacionais (Fase 23). */
#[Fillable(['descricao', 'tipo', 'dia', 'mes', 'data'])]
class Feriado extends Model
{
    /** @use HasFactory<FeriadoFactory> */
    use HasFactory;

    /** `anual`: dia/mês todo ano; `corpus_christi`: Páscoa + 60 dias; `unico`: uma data só. */
    public const TIPOS = ['anual', 'corpus_christi', 'unico'];

    private const MESES = [
        'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
        'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
    ];

    protected function casts(): array
    {
        return ['dia' => 'integer', 'mes' => 'integer', 'data' => 'immutable_date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Como a tela mostra: "2 de julho, todo ano", "Corpus Christi (data móvel): 04/06 em 2026", "13/08/2026, só nessa data". */
    public function quando(int $ano): string
    {
        return match ($this->tipo) {
            'anual' => "{$this->dia} de ".self::MESES[$this->mes - 1].', todo ano',
            'corpus_christi' => 'Corpus Christi (data móvel): '.DiasUteis::pascoa($ano)->addDays(60)->format('d/m')." em {$ano}",
            default => $this->data->format('d/m/Y').', só nessa data',
        };
    }

    /** @return array{descricao: string, tipo: string, dia: ?int, mes: ?int, data: ?string} formato de `DiasUteis` */
    public function paraCalendario(): array
    {
        return [
            'descricao' => $this->descricao,
            'tipo' => $this->tipo,
            'dia' => $this->dia,
            'mes' => $this->mes,
            'data' => $this->data?->toDateString(),
        ];
    }
}
