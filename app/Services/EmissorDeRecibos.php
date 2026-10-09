<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Recibo;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class EmissorDeRecibos
{
    private const CAMPOS_DO_PRESTADOR = [
        'documento', 'nome_fantasia', 'inscricao_municipal', 'inscricao_estadual', 'telefone',
        'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'cep', 'logo',
    ];

    private const TENTATIVAS = 3;

    public function __construct(private NumeradorDeRecibos $numerador) {}

    public function emitir(Cobranca $cobranca): Recibo
    {
        $agora = CarbonImmutable::now();

        for ($tentativa = 1; ; $tentativa++) {
            try {
                return DB::transaction(fn () => $cobranca->recibo()->create([
                    'user_id' => $cobranca->user_id,
                    'ano' => $agora->year,
                    'numero' => $this->numerador->proximo($cobranca->user_id, $agora->year),
                    'modelo' => $cobranca->cliente->modelo->versaoAtual(),
                    'emitido_em' => $agora,
                    'snapshot' => $this->snapshot($cobranca),
                ]));
            } catch (UniqueConstraintViolationException $erro) {
                // Duas emissões simultâneas pegaram o mesmo número (possível no PostgreSQL): tenta o próximo.
                if ($tentativa >= self::TENTATIVAS) {
                    throw $erro;
                }
            }
        }
    }

    /** Cobrança ainda não enviada foi editada: mesmo número, mesmo layout, dados novos. */
    public function refazer(Recibo $recibo): void
    {
        $recibo->update(['snapshot' => $this->snapshot($recibo->cobranca()->firstOrFail())]);
    }

    public function snapshot(Cobranca $cobranca): array
    {
        $prestador = $cobranca->user()->firstOrFail();
        $tomador = $cobranca->cliente()->firstOrFail();

        return [
            'prestador' => ['nome' => $prestador->name, 'email' => $prestador->email]
                + $prestador->only(self::CAMPOS_DO_PRESTADOR),
            'tomador' => $tomador->only(Cliente::CAMPOS),
            'servico' => [
                'descricao' => $cobranca->descricao,
                'codigo_servico' => $cobranca->codigo_servico,
                'codigo_nbs' => $cobranca->codigo_nbs,
                'codigo_tributacao_nacional' => $cobranca->codigo_tributacao_nacional,
                'valor_centavos' => $cobranca->valor_centavos,
                'periodo_inicio' => $cobranca->periodo_inicio->toDateString(),
                'periodo_fim' => $cobranca->periodo_fim->toDateString(),
                'vencimento' => $cobranca->vencimento->toDateString(),
            ],
        ];
    }
}
