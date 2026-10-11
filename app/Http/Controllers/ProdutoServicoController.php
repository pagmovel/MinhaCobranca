<?php

namespace App\Http\Controllers;

use App\Enums\TipoItem;
use App\Http\Requests\ProdutoServicoRequest;
use App\Models\ProdutoServico;
use App\Support\Busca;
use App\Support\Dinheiro;
use App\Support\Paginacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProdutoServicoController extends Controller
{
    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'nome' => ['nullable', 'string', 'max:120'],
            'tipo' => ['nullable', Rule::enum(TipoItem::class)],
            'situacao' => ['nullable', Rule::in(['ativos', 'inativos'])],
        ]);

        $consulta = $request->user()->produtosServicos()->orderBy('nome');

        if (filled($filtros['nome'] ?? null)) {
            $consulta->whereRaw("lower(nome) like ? escape '\\'", [Busca::contem($filtros['nome'])]);
        }
        if (filled($filtros['tipo'] ?? null)) {
            $consulta->where('tipo', $filtros['tipo']);
        }
        if (filled($filtros['situacao'] ?? null)) {
            $consulta->where('ativo', $filtros['situacao'] === 'ativos');
        }

        $pagina = $consulta->paginate(25)->withQueryString();

        return Inertia::render('ProdutosServicos/Index', [
            'itens' => collect($pagina->items())->map(fn (ProdutoServico $item) => [
                'id' => $item->id,
                'nome' => $item->nome,
                'tipo' => $item->tipo->rotulo(),
                'unidade' => $item->unidade,
                'preco' => Dinheiro::formatar($item->preco_centavos),
                'ativo' => $item->ativo,
            ]),
            'paginacao' => Paginacao::dados($pagina),
            'filtros' => [
                'nome' => $filtros['nome'] ?? '',
                'tipo' => $filtros['tipo'] ?? '',
                'situacao' => $filtros['situacao'] ?? '',
            ],
            'tipos' => TipoItem::opcoes(),
        ]);
    }

    /** Autosugestão dos formulários de contrato e cobrança: só itens ativos do usuário. */
    public function busca(Request $request): JsonResponse
    {
        $termo = trim((string) $request->query('q', ''));

        if (mb_strlen($termo) < 2) {
            return response()->json([]);
        }

        $itens = $request->user()->produtosServicos()->where('ativo', true)
            ->whereRaw("lower(nome) like ? escape '\\'", [Busca::contem($termo)])
            ->orderBy('nome')->limit(10)->get();

        return response()->json($itens->map(fn (ProdutoServico $item) => [
            'id' => $item->id,
            'nome' => $item->nome,
            'unidade' => $item->unidade,
            'preco' => Dinheiro::formatar($item->preco_centavos),
        ]));
    }

    public function create(): Response
    {
        return $this->formulario(null);
    }

    public function store(ProdutoServicoRequest $request): RedirectResponse
    {
        $request->user()->produtosServicos()->create($request->dados());

        return redirect('/produtos-servicos')->with('sucesso', 'Produto ou serviço salvo.');
    }

    public function edit(Request $request, int $produto): Response
    {
        return $this->formulario($request->user()->produtosServicos()->findOrFail($produto));
    }

    public function update(ProdutoServicoRequest $request, int $produto): RedirectResponse
    {
        $request->user()->produtosServicos()->findOrFail($produto)->update($request->dados());

        return redirect('/produtos-servicos')->with('sucesso', 'Produto ou serviço salvo. Contratos existentes mantêm o preço deles.');
    }

    public function destroy(Request $request, int $produto): RedirectResponse
    {
        $request->user()->produtosServicos()->findOrFail($produto)->delete();

        return redirect('/produtos-servicos')->with('sucesso', 'Produto ou serviço excluído.');
    }

    private function formulario(?ProdutoServico $item): Response
    {
        return Inertia::render('ProdutosServicos/Form', [
            'item' => $item ? [
                'id' => $item->id,
                'nome' => $item->nome,
                'tipo' => $item->tipo->value,
                'unidade' => $item->unidade,
                'preco' => Dinheiro::formatar($item->preco_centavos),
                'codigo_servico' => $item->codigo_servico ?? '',
                'codigo_nbs' => $item->codigo_nbs ?? '',
                'codigo_tributacao_nacional' => $item->codigo_tributacao_nacional ?? '',
                'ativo' => $item->ativo,
            ] : null,
            'tipos' => TipoItem::opcoes(),
        ]);
    }
}
