<?php

namespace App\Http\Controllers;

use App\Enums\Periodicidade;
use App\Http\Requests\ContratoRequest;
use App\Models\Contrato;
use App\Support\Dinheiro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $request->user()->contratos()->create($request->dadosContrato());

        return redirect('/contratos')->with('sucesso', 'Contrato salvo. As cobranças aparecem no painel quando chegar a hora.');
    }

    public function edit(Request $request, int $contrato): Response
    {
        return $this->formulario($request, $request->user()->contratos()->findOrFail($contrato));
    }

    public function update(ContratoRequest $request, int $contrato): RedirectResponse
    {
        $request->user()->contratos()->findOrFail($contrato)->update($request->dadosContrato());

        return redirect('/contratos')->with('sucesso', 'Contrato salvo. As mudanças valem para as próximas cobranças.');
    }

    private function formulario(Request $request, ?Contrato $contrato): Response
    {
        return Inertia::render('Contratos/Form', [
            'contrato' => $contrato ? [
                'id' => $contrato->id,
                'cliente_id' => $contrato->cliente_id,
                'descricao' => $contrato->descricao,
                'codigo_servico' => $contrato->codigo_servico ?? '',
                'codigo_nbs' => $contrato->codigo_nbs ?? '',
                'codigo_tributacao_nacional' => $contrato->codigo_tributacao_nacional ?? '',
                'valor' => Dinheiro::formatar($contrato->valor_centavos),
                'periodicidade' => $contrato->periodicidade->value,
                'dia_vencimento' => $contrato->dia_vencimento,
                'data_inicio' => $contrato->data_inicio->toDateString(),
                'data_fim' => $contrato->data_fim?->toDateString() ?? '',
                'ativo' => $contrato->ativo,
            ] : null,
            'clientes' => $request->user()->clientes()->orderBy('nome')->get(['id', 'nome']),
            'periodicidades' => Periodicidade::opcoes(),
        ]);
    }
}
