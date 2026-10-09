<?php

namespace App\Http\Controllers;

use App\Enums\FamiliaModelo;
use App\Http\Requests\ClienteRequest;
use App\Models\Cliente;
use App\Support\Documento;
use App\Support\Ufs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClienteController extends Controller
{
    public function index(Request $request): Response
    {
        $clientes = $request->user()->clientes()->orderBy('nome')->get()
            ->map(fn (Cliente $cliente) => [
                'id' => $cliente->id,
                'nome' => $cliente->nome,
                'documento' => Documento::formatar($cliente->documento),
                'cidade' => collect([$cliente->cidade, $cliente->uf])->filter()->implode(' / '),
                'modelo' => $cliente->modelo->rotulo(),
            ]);

        return Inertia::render('Clientes/Index', ['clientes' => $clientes]);
    }

    public function create(Request $request): Response
    {
        return $this->formulario($request, null);
    }

    public function store(ClienteRequest $request): RedirectResponse
    {
        $request->user()->clientes()->create($request->validated());

        return redirect('/clientes')->with('sucesso', 'Cliente salvo.');
    }

    public function edit(Request $request, int $cliente): Response
    {
        return $this->formulario($request, $request->user()->clientes()->findOrFail($cliente));
    }

    public function update(ClienteRequest $request, int $cliente): RedirectResponse
    {
        $request->user()->clientes()->findOrFail($cliente)->update($request->validated());

        return redirect('/clientes')->with('sucesso', 'Cliente salvo.');
    }

    public function destroy(Request $request, int $cliente): RedirectResponse
    {
        $request->user()->clientes()->findOrFail($cliente)->delete();

        return redirect('/clientes')->with('sucesso', 'Cliente excluído.');
    }

    private function formulario(Request $request, ?Cliente $cliente): Response
    {
        return Inertia::render('Clientes/Form', [
            'cliente' => $cliente
                ? ['id' => $cliente->id, 'modelo' => $cliente->modelo->value]
                    + array_map(fn ($valor) => $valor ?? '', $cliente->only(Cliente::CAMPOS))
                : null,
            'modeloPadrao' => $request->user()->modelo_padrao->value,
            'modelos' => FamiliaModelo::opcoes(),
            'ufs' => Ufs::TODAS,
        ]);
    }
}
