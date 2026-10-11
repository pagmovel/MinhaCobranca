<?php

namespace App\Services;

use App\Enums\StatusCobranca;
use App\Models\Cobranca;
use App\Models\User;
use Carbon\CarbonImmutable;

/** Relatórios do módulo Cobrança: cada método devolve uma tabela (colunas, linhas e totais) para a tela e para o CSV. */
final class Relatorios
{
    public function faturamento(User $usuario, CarbonImmutable $de, CarbonImmutable $ate, CarbonImmutable $hoje): array
    {
        $fim = $ate->endOfMonth();
        $hoje = $hoje->startOfDay();

        // ponytail: soma em PHP sobre as cobranças do usuário até o fim do período; agrupar no SQL se passar de dezenas de milhares.
        $cobrancas = $usuario->cobrancas()
            ->where('status', '!=', StatusCobranca::Cancelada)
            ->where(fn ($consulta) => $consulta->where('vencimento', '<=', $fim)->orWhereBetween('pago_em', [$de, $fim]))
            ->get(['vencimento', 'pago_em', 'status', 'valor_centavos']);

        $linhas = [];
        for ($mes = $de; $mes->lte($ate); $mes = $mes->addMonthNoOverflow()) {
            $inicioMes = $mes->startOfMonth();
            $fimMes = $mes->endOfMonth();
            $corte = $fimMes->startOfDay()->min($hoje);
            $linha = ['mes' => $mes->format('m/Y'), 'previsto' => 0, 'recebido' => 0, 'inadimplencia' => 0];

            foreach ($cobrancas as $cobranca) {
                /** @var Cobranca $cobranca */
                if ($cobranca->vencimento->betweenIncluded($inicioMes, $fimMes)) {
                    $linha['previsto'] += $cobranca->valor_centavos;
                }
                if ($cobranca->status === StatusCobranca::Paga && $cobranca->pago_em?->betweenIncluded($inicioMes, $fimMes)) {
                    $linha['recebido'] += $cobranca->valor_centavos;
                }
                if ($inicioMes->lte($hoje) && $cobranca->vencimento->lt($corte)
                    && ($cobranca->pago_em === null || $cobranca->pago_em->gt($corte))) {
                    $linha['inadimplencia'] += $cobranca->valor_centavos;
                }
            }

            $linhas[] = $linha;
        }

        return $this->tabela(
            'Faturamento por período',
            'Mês a mês: previsto (vencimentos), recebido (pagamentos do mês) e o que estava em atraso no fim do mês.',
            [
                self::coluna('mes', 'Mês', 'texto'),
                self::coluna('previsto', 'Previsto'),
                self::coluna('recebido', 'Recebido'),
                self::coluna('inadimplencia', 'Em atraso no fim do mês', soma: false),
            ],
            $linhas,
        );
    }

    /** @return array{chave: string, rotulo: string, tipo: string, soma: bool} */
    private static function coluna(string $chave, string $rotulo, string $tipo = 'dinheiro', bool $soma = true): array
    {
        return ['chave' => $chave, 'rotulo' => $rotulo, 'tipo' => $tipo, 'soma' => $soma];
    }

    /**
     * @param  list<array{chave: string, rotulo: string, tipo: string, soma: bool}>  $colunas
     * @param  list<array<string, int|string>>  $linhas
     */
    private function tabela(string $titulo, string $descricao, array $colunas, array $linhas): array
    {
        $totais = [];
        foreach ($colunas as $indice => $coluna) {
            $totais[$coluna['chave']] = match (true) {
                $indice === 0 => 'Total',
                $coluna['soma'] && in_array($coluna['tipo'], ['dinheiro', 'numero'], true) => array_sum(array_column($linhas, $coluna['chave'])),
                default => null,
            };
        }

        return ['titulo' => $titulo, 'descricao' => $descricao, 'colunas' => $colunas, 'linhas' => $linhas, 'totais' => $totais];
    }
}
