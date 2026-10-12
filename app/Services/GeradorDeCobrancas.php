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
use Illuminate\Support\Collection;
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

        $contratos = $usuario->contratos()->where('ativo', true)->with('itens.produtoServico')->get();
        $existentes = $this->existentes($contratos);
        $calculadora = $this->calculadora->comDiasUteis($usuario->diasUteis());

        foreach ($contratos as $contrato) {
            $devidos = $calculadora->devidos(
                $contrato->data_inicio,
                $contrato->data_fim,
                $contrato->periodicidade,
                $contrato->dia_vencimento,
                $usuario->dias_antecedencia,
                $hoje,
                $contrato->modalidade,
            );

            foreach ($devidos as $periodo) {
                if ($this->cobravel($contrato, $periodo, $existentes[$contrato->id] ?? [])) {
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

    /**
     * Desfaz `ajustarProporcional`: refaz os itens a partir do contrato (quantidade e preço cheios) e devolve o
     * período original. Usado ao renovar um contrato com encerramento agendado (T17.11). O recibo fica por conta
     * do chamador.
     */
    public function desfazerProporcional(Cobranca $cobranca, Contrato $contrato, Periodo $periodoCheio): void
    {
        $contrato->loadMissing('itens.produtoServico');

        $cobranca->itens()->delete();
        $cobranca->itens()->createMany($contrato->itens->map(fn (ContratoItem $item) => [
            'produto_servico_id' => $item->produto_servico_id,
            'nome' => $item->produtoServico->nome,
            'unidade' => $item->produtoServico->unidade,
            'quantidade' => $item->quantidade,
            'preco_unitario_centavos' => $item->preco_unitario_centavos,
            'total_centavos' => $item->total(),
        ])->all());

        $cobranca->update([
            'periodo_fim' => $periodoCheio->fim,
            'valor_centavos' => $contrato->itens->isEmpty() ? $contrato->valor_centavos : $cobranca->itens()->sum('total_centavos'),
            'descricao' => $contrato->descricao,
        ]);
    }

    /**
     * Início (Y-m-d) dos períodos que já têm cobrança, em qualquer situação, por contrato: uma consulta para todos.
     *
     * @param  Collection<int, Contrato>  $contratos
     * @return array<int, list<string>>
     */
    public function existentes(Collection $contratos): array
    {
        // toBase: sem montar um model por cobrança (com 100 contratos de 10 anos, seriam ~12 mil por abertura do Painel).
        // A data vem crua do banco ("Y-m-d" no PostgreSQL, "Y-m-d H:i:s" no SQLite dos testes): os 10 primeiros caracteres.
        return Cobranca::query()->toBase()->whereIn('contrato_id', $contratos->modelKeys())
            ->get(['contrato_id', 'periodo_inicio'])
            ->groupBy('contrato_id')
            ->map(fn (Collection $linhas) => $linhas->map(fn (object $linha) => substr((string) $linha->periodo_inicio, 0, 10))->all())
            ->all();
    }

    /**
     * O período vira cobrança? Não, se já existe (inclusive cancelada), se é anterior ao "cobrar a partir de" ou se é o
     * que contém o fim com "não cobrar". A geração e a previsão usam esta mesma regra.
     *
     * @param  list<string>  $existentes
     */
    public function cobravel(Contrato $contrato, Periodo $periodo, array $existentes): bool
    {
        return ! in_array($periodo->inicio->toDateString(), $existentes, true)
            && ! $this->antesDeCobrar($contrato, $periodo)
            && ! ($this->contemOFim($contrato, $periodo) && $contrato->ultimo_periodo === 'nao_cobrar');
    }

    /** Períodos anteriores ao "cobrar a partir de" (a pessoa escolheu não gerar os retroativos). */
    public function antesDeCobrar(Contrato $contrato, Periodo $periodo): bool
    {
        return $contrato->cobrar_a_partir_de !== null && $periodo->inicio->lt($contrato->cobrar_a_partir_de);
    }

    public function contemOFim(Contrato $contrato, Periodo $periodo): bool
    {
        return $contrato->data_fim !== null && $periodo->fim->gt($contrato->data_fim);
    }
}
