<?php

namespace App\Http\Controllers;

use App\Enums\TipoItem;
use App\Http\Requests\ProdutoServicoRequest;
use App\Models\ContratoItem;
use App\Models\ProdutoServico;
use App\Rules\ValorEmReais;
use App\Support\Busca;
use App\Support\Dinheiro;
use App\Support\ItensDeVenda;
use App\Support\Paginacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
        $registro = $request->user()->produtosServicos()->findOrFail($produto);

        if ($registro->contratoItens()->exists() || $registro->cobrancaItens()->exists()) {
            return back()->with('erro', 'Este item está em contratos ou cobranças. Desative-o em vez de excluir.');
        }

        $registro->delete();

        return redirect('/produtos-servicos')->with('sucesso', 'Produto ou serviço excluído.');
    }

    public function reajuste(Request $request, int $produto): Response
    {
        $item = $request->user()->produtosServicos()->findOrFail($produto);

        $usos = $item->contratoItens()
            ->whereHas('contrato', fn ($contrato) => $contrato->where('user_id', $request->user()->id)->where('ativo', true))
            ->with('contrato.cliente:id,nome')
            ->get();

        return Inertia::render('ProdutosServicos/Reajuste', [
            'item' => [
                'id' => $item->id,
                'nome' => $item->nome,
                'unidade' => $item->unidade,
                'preco' => Dinheiro::formatar($item->preco_centavos),
            ],
            'contratos' => $usos->map(fn (ContratoItem $uso) => [
                'id' => $uso->contrato_id,
                'cliente' => $uso->contrato->cliente->nome,
                'resumo' => strtok($uso->contrato->descricao, "\n"),
                'quantidade' => ItensDeVenda::formatarQuantidade(ItensDeVenda::milesimos($uso->quantidade)),
                'preco' => Dinheiro::formatar($uso->preco_unitario_centavos),
            ])->sortBy('cliente')->values(),
        ]);
    }

    /** Novo preço só nos contratos escolhidos (ativos e que usam o item); cobranças já geradas não mudam. */
    public function reajustar(Request $request, int $produto): RedirectResponse
    {
        $item = $request->user()->produtosServicos()->findOrFail($produto);
        $dados = $request->validate([
            'contratos' => ['required', 'array', 'min:1'],
            'contratos.*' => ['integer'],
            'preco' => ['required', new ValorEmReais(aceitaZero: true)],
        ]);
        $preco = Dinheiro::paraCentavos($dados['preco']);

        $contratos = $request->user()->contratos()->where('ativo', true)->whereIn('id', $dados['contratos'])
            ->whereHas('itens', fn ($itens) => $itens->where('produto_servico_id', $item->id))
            ->with('itens')->orderBy('id')->get();

        if ($contratos->isEmpty()) {
            throw ValidationException::withMessages(['contratos' => 'Escolha pelo menos um contrato ativo que usa este item.']);
        }

        DB::transaction(function () use ($contratos, $item, $preco) {
            foreach ($contratos as $contrato) {
                $contrato->itens->where('produto_servico_id', $item->id)
                    ->each->update(['preco_unitario_centavos' => $preco]);
                $soma = $contrato->itens->sum(fn (ContratoItem $linha) => $linha->total());

                if ($soma < 1 || $soma > ValorEmReais::MAXIMO_CENTAVOS) {
                    throw ValidationException::withMessages([
                        'preco' => 'Com este preço, o total de algum contrato ficaria fora do limite (R$ 0,01 a R$ 99.999.999,99).',
                    ]);
                }

                $contrato->update(['valor_centavos' => $soma]);
            }
        });

        return redirect('/produtos-servicos')
            ->with('sucesso', "Preço reajustado em {$contratos->count()} contrato(s). Vale para as próximas cobranças.");
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
