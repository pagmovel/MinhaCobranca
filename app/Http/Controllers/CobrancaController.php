<?php

namespace App\Http\Controllers;

use App\Enums\StatusCobranca;
use App\Http\Requests\CobrancaRequest;
use App\Services\EmissorDeRecibos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CobrancaController extends Controller
{
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
            $emissor->refazer($registro->recibo);
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
