<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/** Registro só de inclusão (sem updated_at): quem fez o quê, quando, antes/depois e por quê. */
#[Fillable(['acao', 'alvo_tipo', 'alvo_id', 'resumo', 'antes', 'depois', 'motivo', 'ip'])]
class Auditoria extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'antes' => 'array',
            'depois' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @param  Cobranca|Contrato  $alvo */
    public static function registrar(Request $request, string $acao, Model $alvo, string $resumo, ?array $antes = null, ?array $depois = null, ?string $motivo = null): self
    {
        return $request->user()->auditorias()->create([
            'acao' => $acao,
            'alvo_tipo' => strtolower(class_basename($alvo)),
            'alvo_id' => $alvo->getKey(),
            'resumo' => $resumo,
            'antes' => $antes,
            'depois' => $depois,
            'motivo' => $motivo,
            'ip' => $request->ip(),
        ]);
    }
}
