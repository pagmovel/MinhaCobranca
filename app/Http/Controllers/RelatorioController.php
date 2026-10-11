<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Relatorios;
use App\Support\BlocoDePaginas;
use App\Support\Dinheiro;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            'formato' => ['nullable', Rule::in(['csv'])],
            ...$this->regras(),
        ]);
        [$de, $ate] = $this->periodo($filtros, $hoje);

        // O CSV leva todas as linhas, pelo mesmo link de antes.
        if (($filtros['formato'] ?? null) === 'csv') {
            if ($erro = $this->erroDoPeriodo($de, $ate)) {
                return back()->withErrors(['ate' => $erro]);
            }

            return $this->csv($this->tabela($tipo, $relatorios, $request->user(), $de, $ate, $hoje), "relatorio-{$tipo}-{$de->format('Y-m')}-a-{$ate->format('Y-m')}.csv");
        }

        // A tabela vem por ajax de /relatorios/{tipo}/dados; o período é conferido lá (erro embaixo do "Até").
        return Inertia::render('Relatorios/Ver', ['tipo' => $tipo, 'filtros' => $this->paraTela($filtros, $de, $ate)]);
    }

    /** Dados do relatório (JSON, só leitura): um bloco de páginas; os totais são sempre do relatório inteiro. */
    public function dados(Request $request, string $tipo, Relatorios $relatorios): JsonResponse
    {
        $hoje = CarbonImmutable::today();
        $filtros = $request->validate($this->regras());
        [$de, $ate] = $this->periodo($filtros, $hoje);
        if ($erro = $this->erroDoPeriodo($de, $ate)) {
            throw ValidationException::withMessages(['ate' => $erro]);
        }

        $tabela = $this->tabela($tipo, $relatorios, $request->user(), $de, $ate, $hoje);
        $linhas = collect($tabela['linhas']);
        // O faturamento não pagina: tem no máximo 36 linhas, e o gráfico precisa de todas.
        $bloco = $tipo === 'faturamento'
            ? BlocoDePaginas::de($linhas, 1, max(1, $linhas->count()))
            : BlocoDePaginas::de($linhas, (int) ($filtros['pagina'] ?? 1), (int) ($filtros['por_pagina'] ?? 25));

        return response()->json([
            'titulo' => $tabela['titulo'],
            'descricao' => $tabela['descricao'],
            'colunas' => $tabela['colunas'],
            'linhas' => $bloco['itens']->all(),
            'totais' => $tabela['totais'],
            'pagina' => $bloco['pagina'],
            'filtros' => $this->paraTela($filtros, $de, $ate),
        ]);
    }

    /** @return array<string, list<mixed>> */
    private function regras(): array
    {
        return ['de' => ['nullable', 'date_format:Y-m'], 'ate' => ['nullable', 'date_format:Y-m'], ...BlocoDePaginas::regras()];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} padrão: os últimos 12 meses */
    private function periodo(array $filtros, CarbonImmutable $hoje): array
    {
        return [
            CarbonImmutable::createFromFormat('Y-m-d', ($filtros['de'] ?? $hoje->startOfMonth()->subMonthsNoOverflow(11)->format('Y-m')).'-01')->startOfDay(),
            CarbonImmutable::createFromFormat('Y-m-d', ($filtros['ate'] ?? $hoje->format('Y-m')).'-01')->startOfDay(),
        ];
    }

    private function erroDoPeriodo(CarbonImmutable $de, CarbonImmutable $ate): ?string
    {
        return match (true) {
            $de->gt($ate) => 'O fim do período deve ser igual ou depois do início.',
            $de->diffInMonths($ate) >= 36 => 'Escolha um período de até 36 meses.',
            default => null,
        };
    }

    /** @return array<string, mixed> */
    private function tabela(string $tipo, Relatorios $relatorios, User $usuario, CarbonImmutable $de, CarbonImmutable $ate, CarbonImmutable $hoje): array
    {
        return match ($tipo) {
            'faturamento' => $relatorios->faturamento($usuario, $de, $ate, $hoje),
            'clientes' => $relatorios->porCliente($usuario, $de, $ate, $hoje),
            'produtos' => $relatorios->porProduto($usuario, $de, $ate, $hoje),
            'a-receber' => $relatorios->aReceber($usuario, $hoje),
        };
    }

    /** @return array{de: string, ate: string, pagina: int, por_pagina: int} */
    private function paraTela(array $filtros, CarbonImmutable $de, CarbonImmutable $ate): array
    {
        return ['de' => $de->format('Y-m'), 'ate' => $ate->format('Y-m'), 'pagina' => (int) ($filtros['pagina'] ?? 1), 'por_pagina' => (int) ($filtros['por_pagina'] ?? 25)];
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
