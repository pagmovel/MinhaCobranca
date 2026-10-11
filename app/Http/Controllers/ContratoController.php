<?php

namespace App\Http\Controllers;

use App\Enums\FormaPagamento;
use App\Enums\Modalidade;
use App\Enums\Periodicidade;
use App\Http\Requests\ContratoRequest;
use App\Models\Contrato;
use App\Models\ContratoItem;
use App\Support\Dinheiro;
use App\Support\ItensDeVenda;
use App\Support\Paginacao;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ContratoController extends Controller
{
    public function index(Request $request): Response
    {
        $usuario = $request->user();
        $filtros = $request->validate([
            'cliente_id' => ['nullable', 'integer'],
            'situacao' => ['nullable', Rule::in(['ativos', 'encerrados'])],
            'modalidade' => ['nullable', Rule::enum(Modalidade::class)],
            'forma_pagamento' => ['nullable', Rule::enum(FormaPagamento::class)],
            'produto_servico_id' => ['nullable', 'integer'],
        ]);

        $consulta = $usuario->contratos()->with('cliente:id,nome')
            ->orderByDesc('ativo')->orderBy('data_inicio')->orderBy('id');

        foreach (['cliente_id', 'modalidade', 'forma_pagamento'] as $campo) {
            if (filled($filtros[$campo] ?? null)) {
                $consulta->where($campo, $filtros[$campo]);
            }
        }
        if (filled($filtros['situacao'] ?? null)) {
            $consulta->where('ativo', $filtros['situacao'] === 'ativos');
        }
        if (filled($filtros['produto_servico_id'] ?? null)) {
            $consulta->whereHas('itens', fn ($itens) => $itens->where('produto_servico_id', $filtros['produto_servico_id']));
        }

        $pagina = $consulta->paginate(25)->withQueryString();

        return Inertia::render('Contratos/Index', [
            'contratos' => collect($pagina->items())->map(fn (Contrato $contrato) => [
                'id' => $contrato->id,
                'cliente' => $contrato->cliente->nome,
                'resumo' => strtok($contrato->descricao, "\n"),
                'valor' => Dinheiro::formatar($contrato->valor_centavos),
                'periodicidade' => $contrato->periodicidade->rotulo(),
                'modalidade' => $contrato->modalidade->rotulo(),
                'dia_vencimento' => $contrato->dia_vencimento,
                'ativo' => $contrato->ativo,
            ]),
            'paginacao' => Paginacao::dados($pagina),
            'filtros' => [
                'cliente_id' => isset($filtros['cliente_id']) ? (int) $filtros['cliente_id'] : '',
                'situacao' => $filtros['situacao'] ?? '',
                'modalidade' => $filtros['modalidade'] ?? '',
                'forma_pagamento' => $filtros['forma_pagamento'] ?? '',
                'produto_servico_id' => isset($filtros['produto_servico_id']) ? (int) $filtros['produto_servico_id'] : '',
            ],
            'clienteFiltro' => empty($filtros['cliente_id']) ? null : $usuario->clientes()->find($filtros['cliente_id'], ['id', 'nome']),
            'modalidades' => Modalidade::opcoes(),
            'formasPagamento' => FormaPagamento::opcoes(),
            'produtos' => $usuario->produtosServicos()->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->formulario($request, null);
    }

    public function store(ContratoRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $contrato = $request->user()->contratos()->create($request->dadosContrato());
            $contrato->itens()->createMany($request->itensDoContrato());
        });

        return redirect('/contratos')->with('sucesso', 'Contrato salvo. As cobranças aparecem no painel quando chegar a hora.');
    }

    public function edit(Request $request, int $contrato): Response
    {
        return $this->formulario($request, $request->user()->contratos()->findOrFail($contrato));
    }

    public function update(ContratoRequest $request, int $contrato): RedirectResponse
    {
        $registro = $request->user()->contratos()->findOrFail($contrato);
        $dados = $request->dadosContrato();

        $mudaPeriodos = $registro->periodicidade->value !== $dados['periodicidade']
            || $registro->data_inicio->toDateString() !== CarbonImmutable::parse($dados['data_inicio'])->toDateString();

        // Mudar a âncora dos períodos geraria períodos novos sobrepostos aos já cobrados.
        if ($mudaPeriodos && $registro->cobrancas()->exists()) {
            return back()->withErrors([
                'data_inicio' => 'Este contrato já tem cobranças. Para mudar o início ou a periodicidade, encerre-o e crie outro.',
            ]);
        }

        if ($registro->modalidade->value !== $dados['modalidade'] && $registro->cobrancas()->exists()) {
            return back()->withErrors([
                'modalidade' => 'Este contrato já tem cobranças. Para mudar a modalidade, encerre-o e crie outro.',
            ]);
        }

        DB::transaction(function () use ($registro, $dados, $request) {
            $registro->update($dados);
            $registro->itens()->delete();
            $registro->itens()->createMany($request->itensDoContrato());
        });

        return redirect('/contratos')->with('sucesso', 'Contrato salvo. As mudanças valem para as próximas cobranças.');
    }

    private function formulario(Request $request, ?Contrato $contrato): Response
    {
        return Inertia::render('Contratos/Form', [
            'contrato' => $contrato ? [
                'id' => $contrato->id,
                'cliente_id' => $contrato->cliente_id,
                'descricao' => $contrato->descricao,
                'valor' => Dinheiro::formatar($contrato->valor_centavos),
                'itens' => $contrato->itens()->with('produtoServico:id,nome,unidade')->get()
                    ->map(fn (ContratoItem $item) => [
                        'produto_servico_id' => $item->produto_servico_id,
                        'nome' => $item->produtoServico->nome,
                        'unidade' => $item->produtoServico->unidade,
                        'quantidade' => ItensDeVenda::formatarQuantidade(ItensDeVenda::milesimos($item->quantidade)),
                        'preco' => Dinheiro::formatar($item->preco_unitario_centavos),
                        'principal' => $item->principal,
                    ])->all(),
                'periodicidade' => $contrato->periodicidade->value,
                'modalidade' => $contrato->modalidade->value,
                'dia_vencimento' => $contrato->dia_vencimento,
                'data_inicio' => $contrato->data_inicio->toDateString(),
                'data_fim' => $contrato->data_fim?->toDateString() ?? '',
                'ativo' => $contrato->ativo,
                'forma_pagamento' => $contrato->forma_pagamento->value,
                'chave_pix_id' => $contrato->chave_pix_id ?? '',
            ] : null,
            'clienteAtual' => $contrato?->cliente()->first(['id', 'nome']),
            'periodicidades' => Periodicidade::opcoes(),
            'modalidades' => Modalidade::opcoes(),
            'chavesPix' => $request->user()->opcoesDeChavePix(),
            'formasPagamento' => FormaPagamento::opcoes(),
            'cadastroRapido' => $request->user()->opcoesDeCadastroRapido(),
        ]);
    }
}
