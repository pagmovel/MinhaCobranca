<?php

namespace App\Http\Controllers;

use App\Services\Relatorios;
use App\Support\Dinheiro;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RelatorioController extends Controller
{
    public const TIPOS = ['faturamento', 'clientes', 'produtos', 'a-receber'];

    public function index(): Response
    {
        return Inertia::render('Relatorios/Index');
    }

    public function show(Request $request, string $tipo, Relatorios $relatorios): Response|RedirectResponse|StreamedResponse
    {
        $hoje = CarbonImmutable::today();
        $filtros = $request->validate([
            'de' => ['nullable', 'date_format:Y-m'],
            'ate' => ['nullable', 'date_format:Y-m'],
            'formato' => ['nullable', Rule::in(['csv'])],
        ]);

        $de = CarbonImmutable::createFromFormat('Y-m-d', ($filtros['de'] ?? $hoje->startOfMonth()->subMonthsNoOverflow(11)->format('Y-m')).'-01')->startOfDay();
        $ate = CarbonImmutable::createFromFormat('Y-m-d', ($filtros['ate'] ?? $hoje->format('Y-m')).'-01')->startOfDay();

        if ($de->gt($ate)) {
            return back()->withErrors(['ate' => 'O fim do período deve ser igual ou depois do início.']);
        }
        if ($de->diffInMonths($ate) >= 36) {
            return back()->withErrors(['ate' => 'Escolha um período de até 36 meses.']);
        }

        $usuario = $request->user();
        $tabela = match ($tipo) {
            'faturamento' => $relatorios->faturamento($usuario, $de, $ate, $hoje),
            'clientes' => $relatorios->porCliente($usuario, $de, $ate, $hoje),
            'produtos' => $relatorios->porProduto($usuario, $de, $ate, $hoje),
            'a-receber' => $relatorios->aReceber($usuario, $hoje),
        };

        if (($filtros['formato'] ?? null) === 'csv') {
            return $this->csv($tabela, "relatorio-{$tipo}-{$de->format('Y-m')}-a-{$ate->format('Y-m')}.csv");
        }

        return Inertia::render('Relatorios/Ver', [
            'tipo' => $tipo,
            'tabela' => $tabela,
            'filtros' => ['de' => $de->format('Y-m'), 'ate' => $ate->format('Y-m')],
        ]);
    }

    /** CSV para o Excel em português: `;` separa, BOM marca o UTF-8, dinheiro com vírgula decimal. */
    private function csv(array $tabela, string $nome): StreamedResponse
    {
        return response()->streamDownload(function () use ($tabela) {
            $saida = fopen('php://output', 'w');
            fwrite($saida, "\xEF\xBB\xBF");

            // PHP 8.4 avisa se o `escape` de fputcsv não for informado: '' desliga o escape fora do padrão RFC 4180.
            $escrever = fn (array $valores) => fputcsv($saida, array_map($this->celula(...), $valores), ';', '"', '');

            $escrever(array_column($tabela['colunas'], 'rotulo'));
            foreach ($tabela['linhas'] as $linha) {
                $escrever($this->valores($tabela['colunas'], $linha));
            }
            $escrever($this->valores($tabela['colunas'], $tabela['totais']));

            fclose($saida);
        }, $nome, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return list<string> */
    private function valores(array $colunas, array $linha): array
    {
        return array_map(function (array $coluna) use ($linha) {
            $valor = $linha[$coluna['chave']] ?? null;

            return $coluna['tipo'] === 'dinheiro' && is_int($valor) ? Dinheiro::formatar($valor) : (string) ($valor ?? '');
        }, $colunas);
    }

    /** Texto que começa com = + - @ viraria fórmula no Excel (injeção de CSV): vai como texto, com apóstrofo. */
    private function celula(string $valor): string
    {
        return preg_match('/^[=+\-@\t\r]/', $valor) ? "'".$valor : $valor;
    }
}
