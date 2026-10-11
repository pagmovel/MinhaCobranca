<?php

namespace App\Services;

use App\Models\Contrato;
use App\Models\ContratoItem;
use App\Models\User;
use App\Support\Dinheiro;
use App\Support\ItensDeVenda;
use App\Support\Periodo;
use App\Support\ProRata;
use Carbon\CarbonImmutable;

/** Cobranças que os contratos ativos ainda vão emitir: calculadas na hora, nada é gravado. */
final class PrevisaoDeCobrancas
{
    public function __construct(
        private CalculadoraDePeriodos $calculadora,
        private GeradorDeCobrancas $gerador,
    ) {}

    /**
     * Previstas com vencimento entre `$de` e `$ate`, em ordem de vencimento (desempate: contrato). Mesmos períodos, regra
     * e valores do gerador: o preço é o atual do contrato.
     *
     * @return list<array<string, mixed>>
     */
    public function entre(User $usuario, CarbonImmutable $de, CarbonImmutable $ate): array
    {
        $aPrever = [];

        $contratos = $usuario->contratos()->where('ativo', true)->with(['itens', 'cliente:id,nome,apelido'])->get();
        $existentes = $this->gerador->existentes($contratos);

        foreach ($contratos as $contrato) {
            $periodos = $this->calculadora->entre(
                $contrato->data_inicio,
                $contrato->data_fim,
                $contrato->periodicidade,
                $contrato->dia_vencimento,
                $contrato->modalidade,
                $de,
                $ate,
            );

            foreach ($periodos as $periodo) {
                if ($this->gerador->cobravel($contrato, $periodo, $existentes[$contrato->id] ?? [])) {
                    $aPrever[] = [$contrato, $periodo];
                }
            }
        }

        usort($aPrever, fn (array $a, array $b) => [$a[1]->vencimento, $a[0]->id] <=> [$b[1]->vencimento, $b[0]->id]);

        return array_map(fn (array $par) => $this->prevista(...$par), $aPrever);
    }

    /** @return array<string, mixed> */
    private function prevista(Contrato $contrato, Periodo $periodo): array
    {
        $fim = $periodo->fim;
        $valor = $contrato->valor_centavos;
        $itens = $contrato->itens->map(fn (ContratoItem $item) => [
            'produto_servico_id' => $item->produto_servico_id,
            'total_centavos' => $item->total(),
        ]);

        // Mesma conta de GeradorDeCobrancas::ajustarProporcional; o teste de igualdade com o gerador segura as duas.
        if ($this->gerador->contemOFim($contrato, $periodo) && $contrato->ultimo_periodo === 'proporcional') {
            [$usados, $dias] = ProRata::dias($periodo, $contrato->data_fim);
            $itens = $contrato->itens->map(fn (ContratoItem $item) => [
                'produto_servico_id' => $item->produto_servico_id,
                'total_centavos' => ItensDeVenda::total(
                    ProRata::quantidade(ItensDeVenda::milesimos($item->quantidade), $usados, $dias),
                    $item->preco_unitario_centavos,
                ),
            ]);
            $valor = $itens->isEmpty() ? ProRata::valor($valor, $usados, $dias) : $itens->sum('total_centavos');
            $fim = $contrato->data_fim;
        }

        return [
            'id' => "prevista-{$contrato->id}-{$periodo->inicio->toDateString()}",
            'contrato_id' => $contrato->id,
            'cliente_id' => $contrato->cliente_id,
            'cliente' => $contrato->cliente->exibicao(),
            'cliente_oficial' => $contrato->cliente->nomeOficial(),
            'periodo' => $periodo->inicio->format('d/m/Y').' a '.$fim->format('d/m/Y'),
            'vencimento' => $periodo->vencimento->format('d/m/Y'),
            'valor' => Dinheiro::formatar($valor),
            'valor_centavos' => $valor,
            'situacao' => 'prevista',
            'itens' => $itens->values()->all(),
            'recibo' => null,
            'historico' => [],
            'pago_em' => null,
            'enviado' => false,
            'editavel' => false,
        ];
    }
}
