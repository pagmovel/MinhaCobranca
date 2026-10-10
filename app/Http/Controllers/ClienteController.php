<?php

namespace App\Http\Controllers;

use App\Enums\FamiliaModelo;
use App\Http\Requests\ClienteRequest;
use App\Models\Cliente;
use App\Support\Documento;
use App\Support\Ufs;
use Illuminate\Http\JsonResponse;
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

    /** Autosugestão: nome (sem diferença de maiúsculas) ou começo do documento; só clientes do usuário. */
    public function busca(Request $request): JsonResponse
    {
        $termo = trim((string) $request->query('q', ''));

        if (mb_strlen($termo) < 2) {
            return response()->json([]);
        }

        // % e _ digitados são texto, não curinga.
        $literal = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($termo));
        $documento = Documento::normalizar($termo);

        $clientes = $request->user()->clientes()
            ->where(function ($consulta) use ($literal, $documento) {
                $consulta->whereRaw("lower(nome) like ? escape '\\'", ["%{$literal}%"]);
                if ($documento !== '') {
                    $consulta->orWhere('documento', 'like', "{$documento}%");
                }
            })
            ->orderBy('nome')->limit(10)->get(['id', 'nome', 'documento']);

        return response()->json($clientes->map(fn (Cliente $cliente) => [
            'id' => $cliente->id,
            'nome' => $cliente->nome,
            'documento' => Documento::formatar($cliente->documento),
        ]));
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
        $registro = $request->user()->clientes()->findOrFail($cliente);

        if ($registro->contratos()->exists() || $registro->cobrancas()->exists()) {
            return back()->with('erro', 'Este cliente tem contratos ou cobranças e não pode ser excluído.');
        }

        $registro->delete();

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
