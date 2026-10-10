<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class GeradorDeCobrancas
{
    public function __construct(
        private CalculadoraDePeriodos $calculadora,
        private EmissorDeRecibos $emissor,
    ) {}

    /** Cria cobrança + recibo de todo período devido que ainda não existe. Retorna quantas criou. */
    public function gerarPara(User $usuario, CarbonImmutable $hoje): int
    {
        if (! $usuario->perfilCompleto()) {
            return 0;
        }

        $aGerar = [];

        foreach ($usuario->contratos()->where('ativo', true)->get() as $contrato) {
            $existentes = $contrato->cobrancas()->pluck('periodo_inicio')
                ->map(fn ($data) => CarbonImmutable::parse($data)->toDateString())
                ->all();

            $devidos = $this->calculadora->devidos(
                $contrato->data_inicio,
                $contrato->data_fim,
                $contrato->periodicidade,
                $contrato->dia_vencimento,
                $usuario->dias_antecedencia,
                $hoje,
                $contrato->modalidade,
            );

            foreach ($devidos as $periodo) {
                if (! in_array($periodo->inicio->toDateString(), $existentes, true)) {
                    $aGerar[] = [$contrato, $periodo];
                }
            }
        }

        // Numeração dos recibos acompanha a ordem dos vencimentos.
        usort($aGerar, fn (array $a, array $b) => [$a[1]->vencimento, $a[0]->id] <=> [$b[1]->vencimento, $b[0]->id]);

        $criadas = 0;

        foreach ($aGerar as [$contrato, $periodo]) {
            try {
                DB::transaction(function () use ($usuario, $contrato, $periodo) {
                    $cobranca = $usuario->cobrancas()->create([
                        'cliente_id' => $contrato->cliente_id,
                        'contrato_id' => $contrato->id,
                        'periodo_inicio' => $periodo->inicio,
                        'periodo_fim' => $periodo->fim,
                        'vencimento' => $periodo->vencimento,
                        'valor_centavos' => $contrato->valor_centavos,
                        'descricao' => $contrato->descricao,
                        'codigo_servico' => $contrato->codigo_servico,
                        'codigo_nbs' => $contrato->codigo_nbs,
                        'codigo_tributacao_nacional' => $contrato->codigo_tributacao_nacional,
                        'forma_pagamento' => $contrato->forma_pagamento,
                        'chave_pix_id' => $contrato->chave_pix_id,
                    ]);

                    $this->emissor->emitir($cobranca);
                });
                $criadas++;
            } catch (UniqueConstraintViolationException) {
                // Outra aba gerou o mesmo período ao mesmo tempo: ele já existe; segue para o próximo.
            }
        }

        return $criadas;
    }
}
