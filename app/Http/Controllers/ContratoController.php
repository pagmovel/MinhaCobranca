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
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ContratoController extends Controller
{
    public function index(Request $request): Response
    {
        $contratos = $request->user()->contratos()->with('cliente:id,nome')
            ->orderByDesc('ativo')->orderBy('data_inicio')->get()
            ->map(fn (Contrato $contrato) => [
                'id' => $contrato->id,
                'cliente' => $contrato->cliente->nome,
                'resumo' => strtok($contrato->descricao, "\n"),
                'valor' => Dinheiro::formatar($contrato->valor_centavos),
                'periodicidade' => $contrato->periodicidade->rotulo(),
                'dia_vencimento' => $contrato->dia_vencimento,
                'ativo' => $contrato->ativo,
            ]);

        return Inertia::render('Contratos/Index', ['contratos' => $contratos]);
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
            'temClientes' => $request->user()->clientes()->exists(),
            'clienteAtual' => $contrato?->cliente()->first(['id', 'nome']),
            'periodicidades' => Periodicidade::opcoes(),
            'modalidades' => Modalidade::opcoes(),
            'chavesPix' => $request->user()->opcoesDeChavePix(),
            'formasPagamento' => FormaPagamento::opcoes(),
        ]);
    }
}
