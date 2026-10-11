<?php

namespace App\Services;

use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\ContratoItem;
use App\Models\User;
use App\Support\ItensDeVenda;
use App\Support\Periodo;
use App\Support\ProRata;
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

        foreach ($usuario->contratos()->where('ativo', true)->with('itens.produtoServico')->get() as $contrato) {
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
                if (! in_array($periodo->inicio->toDateString(), $existentes, true)
                    && ! $this->antesDeCobrar($contrato, $periodo)
                    && ! ($this->contemOFim($contrato, $periodo) && $contrato->ultimo_periodo === 'nao_cobrar')) {
                    $aGerar[] = [$contrato, $periodo];
                }
            }
        }

        // Numeração dos recibos acompanha a ordem dos vencimentos.
        usort($aGerar, fn (array $a, array $b) => [$a[1]->vencimento, $a[0]->id] <=> [$b[1]->vencimento, $b[0]->id]);

        $criadas = 0;

        foreach ($aGerar as [$contrato, $periodo]) {
            try {
                DB::transaction(fn () => $this->criar($contrato, $periodo));
                $criadas++;
            } catch (UniqueConstraintViolationException) {
                // Outra aba gerou o mesmo período ao mesmo tempo: ele já existe; segue para o próximo.
            }
        }

        return $criadas;
    }

    /** Cobrança + recibo de um período do contrato (o chamador abre a transação). */
    public function criar(Contrato $contrato, Periodo $periodo): Cobranca
    {
        $cobranca = $contrato->user->cobrancas()->create([
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

        // Cópia: mudar o contrato ou o catálogo depois não altera esta cobrança.
        $cobranca->itens()->createMany($contrato->itens->map(fn (ContratoItem $item) => [
            'produto_servico_id' => $item->produto_servico_id,
            'nome' => $item->produtoServico->nome,
            'unidade' => $item->produtoServico->unidade,
            'quantidade' => $item->quantidade,
            'preco_unitario_centavos' => $item->preco_unitario_centavos,
            'total_centavos' => $item->total(),
            'principal' => $item->principal,
        ])->all());

        // Último período de contrato com fim (encerramento agendado ou imediato) escolhido como proporcional.
        if ($this->contemOFim($contrato, $periodo) && $contrato->ultimo_periodo === 'proporcional') {
            $this->ajustarProporcional($cobranca, $periodo, $contrato->data_fim);
        }

        $this->emissor->emitir($cobranca);

        return $cobranca;
    }

    /**
     * Deixa a cobrança proporcional aos dias usados até o fim: quantidade de cada item × usados ÷ dias (sem itens, o
     * valor), período até o fim e a linha "Proporcional: …" na descrição. O recibo fica por conta do chamador.
     */
    public function ajustarProporcional(Cobranca $cobranca, Periodo $periodo, CarbonImmutable $fim): void
    {
        [$usados, $dias] = ProRata::dias($periodo, $fim);
        $itens = $cobranca->itens()->get();

        foreach ($itens as $item) {
            $milesimos = ProRata::quantidade(ItensDeVenda::milesimos($item->quantidade), $usados, $dias);
            $item->update([
                'quantidade' => ItensDeVenda::quantidade($milesimos),
                'total_centavos' => ItensDeVenda::total($milesimos, $item->preco_unitario_centavos),
            ]);
        }

        $linha = sprintf('Proporcional: %d de %d dias (%s a %s)', $usados, $dias, $periodo->inicio->format('d/m/Y'), $fim->format('d/m/Y'));

        $cobranca->update([
            'valor_centavos' => $itens->isEmpty() ? ProRata::valor($cobranca->valor_centavos, $usados, $dias) : $itens->sum('total_centavos'),
            'periodo_fim' => $fim,
            // A linha digitável traz o valor cheio dentro do código: o boleto precisa ser refeito com o valor novo.
            'linha_digitavel' => null,
            'descricao' => trim($cobranca->descricao) === '' ? $linha : rtrim($cobranca->descricao)."\n".$linha,
        ]);
    }

    /** Períodos anteriores ao "cobrar a partir de" (a pessoa escolheu não gerar os retroativos). */
    public function antesDeCobrar(Contrato $contrato, Periodo $periodo): bool
    {
        return $contrato->cobrar_a_partir_de !== null && $periodo->inicio->lt($contrato->cobrar_a_partir_de);
    }

    private function contemOFim(Contrato $contrato, Periodo $periodo): bool
    {
        return $contrato->data_fim !== null && $periodo->fim->gt($contrato->data_fim);
    }
}
