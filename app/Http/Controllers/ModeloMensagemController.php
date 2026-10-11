<?php

namespace App\Http\Controllers;

use App\Http\Requests\ModeloMensagemRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModeloMensagemController extends Controller
{
    public function store(ModeloMensagemRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            if ($request->boolean('padrao')) {
                $request->user()->modelosMensagem()->update(['padrao' => false]);
            }
            $request->user()->modelosMensagem()->create($request->validated());
        });

        return back()->with('sucesso', 'Modelo cadastrado.');
    }

    public function update(ModeloMensagemRequest $request, int $mensagem): RedirectResponse
    {
        DB::transaction(function () use ($request, $mensagem) {
            if ($request->boolean('padrao')) {
                $request->user()->modelosMensagem()->whereKeyNot($mensagem)->update(['padrao' => false]);
            }
            $request->user()->modelosMensagem()->findOrFail($mensagem)->update($request->validated());
        });

        return back()->with('sucesso', 'Modelo atualizado.');
    }

    public function destroy(Request $request, int $mensagem): RedirectResponse
    {
        $request->user()->modelosMensagem()->findOrFail($mensagem)->delete();

        return back()->with('sucesso', 'Modelo excluído.');
    }
}
