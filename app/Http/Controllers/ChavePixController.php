<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChavePixRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ChavePixController extends Controller
{
    public function store(ChavePixRequest $request): RedirectResponse|JsonResponse
    {
        $chave = $request->user()->chavesPix()->create($request->validated());

        // Cadastro rápido (modal na forma de pagamento): devolve a chave pronta para ser escolhida.
        if ($request->expectsJson()) {
            return response()->json(['id' => $chave->id, 'rotulo' => $chave->rotulo()], 201);
        }

        return back()->with('sucesso', 'Chave PIX cadastrada.');
    }

    public function update(ChavePixRequest $request, int $chave): RedirectResponse
    {
        $request->user()->chavesPix()->findOrFail($chave)->update($request->validated());

        return back()->with('sucesso', 'Chave PIX atualizada.');
    }

    public function destroy(Request $request, int $chave): RedirectResponse
    {
        $registro = $request->user()->chavesPix()->findOrFail($chave);

        if ($registro->contratos()->exists() || $registro->cobrancas()->exists()) {
            return back()->with('erro', 'Esta chave está em uso em contratos ou cobranças. Troque a forma de pagamento deles antes de excluir.');
        }

        $registro->delete();

        return back()->with('sucesso', 'Chave PIX excluída.');
    }
}
