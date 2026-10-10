<?php

namespace App\Http\Controllers;

use App\Enums\StatusCobranca;
use App\Http\Requests\CobrancaRequest;
use App\Models\Cobranca;
use App\Services\EmissorDeRecibos;
use App\Services\GeradorDeCobrancas;
use App\Support\Dinheiro;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CobrancaController extends Controller
{
    public function index(Request $request, GeradorDeCobrancas $gerador): Response
    {
        $usuario = $request->user();
        $hoje = CarbonImmutable::today();
        $gerador->gerarPara($usuario, $hoje);

        $filtros = $request->validate([
            'mes' => ['nullable', 'date_format:Y-m'],
            'status' => ['nullable', Rule::in(['pendente', 'paga', 'cancelada'])],
            'cliente_id' => ['nullable', 'integer'],
        ]);

        $mes = CarbonImmutable::createFromFormat('Y-m-d', ($filtros['mes'] ?? $hoje->format('Y-m')).'-01')->startOfDay();

        $consulta = $usuario->cobrancas()->with(['cliente:id,nome', 'recibo'])
            ->whereBetween('vencimento', [$mes, $mes->endOfMonth()])
            ->orderBy('vencimento');

        if (! empty($filtros['status'])) {
            $consulta->where('status', $filtros['status']);
        }
        if (! empty($filtros['cliente_id'])) {
            $consulta->where('cliente_id', $filtros['cliente_id']);
        }

        return Inertia::render('Cobrancas/Index', [
            'cobrancas' => $consulta->get()->map(fn (Cobranca $cobranca) => $cobranca->paraLista($hoje)),
            'filtros' => [
                'mes' => $mes->format('Y-m'),
                'status' => $filtros['status'] ?? '',
                'cliente_id' => isset($filtros['cliente_id']) ? (int) $filtros['cliente_id'] : '',
            ],
            'clientes' => $usuario->clientes()->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->formulario($request, null);
    }

    public function edit(Request $request, int $cobranca): Response|RedirectResponse
    {
        $registro = $request->user()->cobrancas()->findOrFail($cobranca);

        if (! $registro->editavel()) {
            return redirect('/cobrancas')->with('erro', 'Cobrança já enviada não pode ser editada. Cancele e crie uma cobrança avulsa.');
        }

        return $this->formulario($request, $registro);
    }

    private function formulario(Request $request, ?Cobranca $cobranca): Response
    {
        return Inertia::render('Cobrancas/Form', [
            'cobranca' => $cobranca ? [
                'id' => $cobranca->id,
                'de_contrato' => $cobranca->contrato_id !== null,
                'cliente_id' => $cobranca->cliente_id,
                'descricao' => $cobranca->descricao,
                'codigo_servico' => $cobranca->codigo_servico ?? '',
                'codigo_nbs' => $cobranca->codigo_nbs ?? '',
                'codigo_tributacao_nacional' => $cobranca->codigo_tributacao_nacional ?? '',
                'valor' => Dinheiro::formatar($cobranca->valor_centavos),
                'periodo_inicio' => $cobranca->periodo_inicio->toDateString(),
                'periodo_fim' => $cobranca->periodo_fim->toDateString(),
                'vencimento' => $cobranca->vencimento->toDateString(),
            ] : null,
            'clientes' => $request->user()->clientes()->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function store(CobrancaRequest $request, EmissorDeRecibos $emissor): RedirectResponse
    {
        $usuario = $request->user();

        if (! $usuario->perfilCompleto()) {
            return back()->with('erro', 'Complete seu perfil para emitir recibos.');
        }

        DB::transaction(function () use ($usuario, $request, $emissor) {
            $emissor->emitir($usuario->cobrancas()->create($request->dadosCobranca()));
        });

        return redirect('/cobrancas')->with('sucesso', 'Cobrança criada com o recibo.');
    }

    public function update(CobrancaRequest $request, int $cobranca, EmissorDeRecibos $emissor): RedirectResponse
    {
        $registro = $request->user()->cobrancas()->with('recibo')->findOrFail($cobranca);

        if (! $registro->editavel()) {
            return back()->with('erro', 'Cobrança já enviada não pode ser editada. Cancele e crie uma cobrança avulsa.');
        }

        $dados = $request->dadosCobranca();
        if ($registro->contrato_id) {
            // Período e cliente de cobrança de contrato são definidos pelo contrato.
            $dados = Arr::except($dados, ['cliente_id', 'periodo_inicio', 'periodo_fim']);
        }

        DB::transaction(function () use ($registro, $dados, $emissor) {
            $registro->update($dados);
            $emissor->refazer($registro->recibo, $registro->wasChanged('cliente_id'));
        });

        return redirect('/cobrancas')->with('sucesso', 'Cobrança atualizada. O recibo manteve o número.');
    }

    public function cancelar(Request $request, int $cobranca): RedirectResponse
    {
        $registro = $request->user()->cobrancas()->findOrFail($cobranca);

        if ($registro->status !== StatusCobranca::Pendente) {
            return back()->with('erro', 'Só cobranças pendentes podem ser canceladas.');
        }

        $registro->status = StatusCobranca::Cancelada;
        $registro->save();

        return back()->with('sucesso', 'Cobrança cancelada. O recibo passa a sair como cancelado.');
    }

    public function pagar(Request $request, int $cobranca): RedirectResponse
    {
        $registro = $request->user()->cobrancas()->findOrFail($cobranca);
        $dados = $request->validate(['pago_em' => ['required', 'date', 'before_or_equal:today']]);

        if ($registro->status !== StatusCobranca::Pendente) {
            return back()->with('erro', 'Só cobranças pendentes recebem baixa.');
        }

        $registro->status = StatusCobranca::Paga;
        $registro->pago_em = $dados['pago_em'];
        $registro->save();

        return back()->with('sucesso', 'Baixa registrada.');
    }

    /** Chamado pelo botão Enviar depois que a folha de compartilhamento (ou o download) concluiu. */
    public function marcarEnviada(Request $request, int $cobranca): RedirectResponse
    {
        $registro = $request->user()->cobrancas()->findOrFail($cobranca);
        $registro->enviado_em = now();
        $registro->save();

        return back();
    }
}
