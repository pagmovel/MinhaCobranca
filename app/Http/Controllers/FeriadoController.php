<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeriadoRequest;
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
}
