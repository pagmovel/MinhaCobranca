<?php

namespace App\Services;

use App\Enums\StatusCobranca;
use App\Models\Cobranca;
use App\Models\CobrancaItem;
use App\Models\Contrato;
use App\Models\ContratoItem;
use App\Support\ItensDeVenda;
use App\Support\Periodo;
use App\Support\ProRata;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Encerra um contrato numa data e acerta as cobranças pendentes dele (decisões do usuário, T17.9):
 * vencida ou vencendo hoje continua; período inteiro até o fim continua; período depois do fim é cancelado;
 * período que contém o fim vira proporcional (ou é cancelado, se a pessoa escolher); períodos até o fim que
 * ainda não saíram (pós-pago) são emitidos agora. Pagas e canceladas não mudam.
 */
final class EncerradorDeContratos
{
    public function __construct(
        private CalculadoraDePeriodos $calculadora,
        private GeradorDeCobrancas $gerador,
        private EmissorDeRecibos $emissor,
    ) {}

    /**
     * O que acontece com cada cobrança — a mesma lista serve à prévia e à execução.
     *
     * @return list<array{acao: 'manter'|'cancelar'|'proporcional'|'emitir'|'nao_emitir', cobranca: ?Cobranca, periodo: Periodo, em_curso: bool, valor_centavos: int, usados: ?int, dias: ?int, agendada: bool}>
     */
    public function plano(Contrato $contrato, CarbonImmutable $fim, bool $proporcional, CarbonImmutable $hoje): array
    {
        $contrato->loadMissing('itens.produtoServico');
        $cobrancas = $contrato->cobrancas()->with('itens')->orderBy('periodo_inicio')->get();
        $existentes = $cobrancas->map(fn (Cobranca $cobranca) => $cobranca->periodo_inicio->toDateString())->all();
        $acoes = [];

        foreach ($cobrancas->where('status', StatusCobranca::Pendente) as $cobranca) {
            $periodo = new Periodo($cobranca->periodo_inicio, $cobranca->periodo_fim, $cobranca->vencimento);
            // Período depois do fim não foi prestado: cancela mesmo vencido (o pré-pago vence antes de começar;
            // no recomeço, mantê-lo cobraria o mesmo mês duas vezes). Decisão do usuário em 2026-10-10.
            $acao = match (true) {
                $periodo->inicio->gt($fim) => 'cancelar',
                in_array($cobranca->situacao($hoje), ['vencida', 'vence_hoje'], true) => 'manter',
                $periodo->fim->lte($fim) => 'manter',
                $proporcional => 'proporcional',
                default => 'cancelar',
            };
            // Só conta como "em curso" (a tela oferece a escolha) quando a escolha muda alguma coisa.
            $emCurso = $acao !== 'manter' && $periodo->inicio->lte($fim) && $periodo->fim->gt($fim);

            $acoes[] = $this->acao($acao, $cobranca, $periodo, $emCurso, $fim, $cobranca->itens, $cobranca->valor_centavos);
        }

        // Hoje "distante": a calculadora lista todos os períodos que começam até o fim, devidos ou não.
        $periodos = $this->calculadora->comDiasUteis($contrato->user->diasUteis())->devidos(
            $contrato->data_inicio, $fim, $contrato->periodicidade, $contrato->dia_vencimento, 0,
            $fim->addYears(10), $contrato->modalidade,
        );

        // Fim no futuro: o encerramento é agendado e os períodos ainda não emitidos saem no ritmo de sempre.
        $agendada = $fim->gt($hoje->startOfDay());

        foreach ($periodos as $periodo) {
            if (in_array($periodo->inicio->toDateString(), $existentes, true) || $this->gerador->antesDeCobrar($contrato, $periodo)) {
                continue;
            }

            $emCurso = $periodo->fim->gt($fim);
            $acao = ! $emCurso ? 'emitir' : ($proporcional ? 'proporcional' : 'nao_emitir');
            $acoes[] = $this->acao($acao, null, $periodo, $emCurso, $fim, $contrato->itens, $contrato->valor_centavos, $agendada);
        }

        usort($acoes, fn (array $a, array $b) => $a['periodo']->inicio <=> $b['periodo']->inicio);

        return $acoes;
    }

    /**
     * Executa o plano, tudo ou nada. Fim até hoje encerra na hora (inativo); fim no futuro fica agendado: o contrato
     * continua ativo com a data de fim e a escolha do último período, que o gerador respeita quando chegar a hora.
     */
    public function encerrar(Contrato $contrato, CarbonImmutable $fim, bool $proporcional, CarbonImmutable $hoje): void
    {
        DB::transaction(function () use ($contrato, $fim, $proporcional, $hoje) {
            $plano = $this->plano($contrato, $fim, $proporcional, $hoje);

            // Antes das emissões: o gerador lê a data de fim e a escolha do último período do contrato.
            $contrato->update([
                'data_fim' => $fim,
                'ultimo_periodo' => $proporcional ? 'proporcional' : 'nao_cobrar',
                'ativo' => $fim->gt($hoje->startOfDay()),
            ]);

            foreach ($plano as $acao) {
                if ($acao['agendada']) {
                    continue;
                }

                $cobranca = $acao['cobranca'];

                match (true) {
                    $acao['acao'] === 'cancelar' => $this->cancelar($cobranca),
                    // Período ainda não emitido: o gerador já sai proporcional se for o último.
                    $cobranca === null && in_array($acao['acao'], ['emitir', 'proporcional'], true) => $this->gerador->criar($contrato, $acao['periodo']),
                    $acao['acao'] === 'proporcional' => $this->proporcional($cobranca, $acao['periodo'], $fim),
                    default => null,
                };
            }
        });
    }

    private function proporcional(Cobranca $cobranca, Periodo $periodo, CarbonImmutable $fim): void
    {
        if ($cobranca->editavel()) {
            $this->gerador->ajustarProporcional($cobranca, $periodo, $fim);
            $this->emissor->refazer($cobranca->recibo()->firstOrFail());

            return;
        }

        // Já enviada: o cliente tem o recibo cheio. Cancela e emite outra (avulsa: o índice único
        // contrato+início bloquearia uma segunda cobrança do mesmo período no contrato).
        $nova = $cobranca->replicate(['status', 'enviado_em', 'pago_em']);
        $nova->forceFill(['contrato_id' => null, 'status' => StatusCobranca::Pendente])->save();
        $nova->itens()->createMany($cobranca->itens->map(fn (CobrancaItem $item) => $item->only($item->getFillable()))->all());
        $this->cancelar($cobranca);
        $this->gerador->ajustarProporcional($nova, $periodo, $fim);
        $this->emissor->emitir($nova);
    }

    private function cancelar(Cobranca $cobranca): void
    {
        $cobranca->status = StatusCobranca::Cancelada;
        $cobranca->save();
    }

    /** @param  Collection<int, ContratoItem|CobrancaItem>  $itens */
    private function acao(string $acao, ?Cobranca $cobranca, Periodo $periodo, bool $emCurso, CarbonImmutable $fim, Collection $itens, int $valor, bool $agendada = false): array
    {
        $usados = $dias = null;

        if ($acao === 'proporcional') {
            [$usados, $dias] = ProRata::dias($periodo, $fim);
            $valor = $itens->isEmpty() ? ProRata::valor($valor, $usados, $dias) : $itens->sum(fn ($item) => ItensDeVenda::total(
                ProRata::quantidade(ItensDeVenda::milesimos($item->quantidade), $usados, $dias),
                $item->preco_unitario_centavos,
            ));
        }

        return ['acao' => $acao, 'cobranca' => $cobranca, 'periodo' => $periodo, 'em_curso' => $emCurso,
            'valor_centavos' => $valor, 'usados' => $usados, 'dias' => $dias, 'agendada' => $agendada];
    }
}
