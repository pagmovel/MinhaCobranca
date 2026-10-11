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
            $dados = $request->validated();
            // Exatamente um padrão quando há modelos (spec §2): o primeiro cadastro de alguém já nasce padrão.
            $dados['padrao'] = $dados['padrao'] || ! $request->user()->modelosMensagem()->where('padrao', true)->exists();
            if ($dados['padrao']) {
                $request->user()->modelosMensagem()->update(['padrao' => false]);
            }
            $request->user()->modelosMensagem()->create($dados);
        });

        return back()->with('sucesso', 'Modelo cadastrado.');
    }

    public function update(ModeloMensagemRequest $request, int $mensagem): RedirectResponse
    {
        DB::transaction(function () use ($request, $mensagem) {
            $dados = $request->validated();
            $modelo = $request->user()->modelosMensagem()->findOrFail($mensagem);
            // Exatamente um padrão quando há modelos: desmarcar o único padrão existente é recusado, não zera.
            $haOutroPadrao = $request->user()->modelosMensagem()->whereKeyNot($mensagem)->where('padrao', true)->exists();
            $dados['padrao'] = $dados['padrao'] || (! $haOutroPadrao && $modelo->padrao);
            if ($dados['padrao']) {
                $request->user()->modelosMensagem()->whereKeyNot($mensagem)->update(['padrao' => false]);
            }
            $modelo->update($dados);
        });

        return back()->with('sucesso', 'Modelo atualizado.');
    }

    public function destroy(Request $request, int $mensagem): RedirectResponse
    {
        DB::transaction(function () use ($request, $mensagem) {
            $modelo = $request->user()->modelosMensagem()->findOrFail($mensagem);
            $modelo->delete();

            // Exatamente um padrão quando há modelos: some o padrão, promove o mais antigo que restou.
            if ($modelo->padrao) {
                $request->user()->modelosMensagem()->oldest('id')->first()?->update(['padrao' => true]);
            }
        });

        return back()->with('sucesso', 'Modelo excluído.');
    }
}
