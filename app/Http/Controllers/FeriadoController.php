<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeriadoRequest;
use App\Services\AjusteDeVencimentos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FeriadoController extends Controller
{
    public function store(FeriadoRequest $request): RedirectResponse
    {
        $request->user()->feriados()->create($request->validated());

        return back()->with('sucesso', 'Feriado cadastrado.');
    }

    public function update(FeriadoRequest $request, int $feriado): RedirectResponse
    {
        $request->user()->feriados()->findOrFail($feriado)->update($request->validated());

        return back()->with('sucesso', 'Feriado atualizado.');
    }

    public function destroy(Request $request, int $feriado): RedirectResponse
    {
        $request->user()->feriados()->findOrFail($feriado)->delete();

        return back()->with('sucesso', 'Feriado excluído.');
    }

    public function ajustar(Request $request, AjusteDeVencimentos $ajuste): RedirectResponse
    {
        $dados = $request->validate(['ids' => ['required', 'array', 'max:1000'], 'ids.*' => ['integer']]);
        $quantas = $ajuste->ajustar($request->user(), array_map('intval', $dados['ids']));

        return back()->with('sucesso', match ($quantas) {
            0 => 'Nenhuma cobrança precisava de ajuste.',
            1 => '1 cobrança ajustada.',
            default => "{$quantas} cobranças ajustadas.",
        });
    }
}
