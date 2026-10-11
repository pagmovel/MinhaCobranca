<?php

namespace App\Http\Controllers;

use App\Enums\FamiliaModelo;
use App\Http\Requests\ClienteRequest;
use App\Models\Cliente;
use App\Support\Busca;
use App\Support\Documento;
use App\Support\Paginacao;
use App\Support\Ufs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClienteController extends Controller
{
    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:255'],
            'uf' => ['nullable', Rule::in(Ufs::TODAS)],
            'modelo' => ['nullable', Rule::enum(FamiliaModelo::class)],
            'contrato' => ['nullable', Rule::in(['com', 'sem'])],
        ]);

        $consulta = $request->user()->clientes()->orderBy('nome')->orderBy('id');

        if (filled($filtros['q'] ?? null)) {
            $consulta->comTermo($filtros['q']);
        }
        if (filled($filtros['cidade'] ?? null)) {
            $consulta->whereRaw("lower(cidade) like ? escape '\\'", [Busca::contem($filtros['cidade'])]);
        }
        if (filled($filtros['uf'] ?? null)) {
            $consulta->where('uf', $filtros['uf']);
        }
        if (filled($filtros['modelo'] ?? null)) {
            $consulta->where('modelo', $filtros['modelo']);
        }
        if (filled($filtros['contrato'] ?? null)) {
            $ativo = fn ($contratos) => $contratos->where('ativo', true);

            if ($filtros['contrato'] === 'com') {
                $consulta->whereHas('contratos', $ativo);
            } else {
                $consulta->whereDoesntHave('contratos', $ativo);
            }
        }

        $pagina = $consulta->paginate(25)->withQueryString();

        return Inertia::render('Clientes/Index', [
            'clientes' => collect($pagina->items())->map(fn (Cliente $cliente) => [
                'id' => $cliente->id,
                'nome' => $cliente->nome,
                'documento' => Documento::formatar($cliente->documento),
                'cidade' => collect([$cliente->cidade, $cliente->uf])->filter()->implode(' / '),
                'modelo' => $cliente->modelo->rotulo(),
            ]),
            'paginacao' => Paginacao::dados($pagina),
            'filtros' => [
                'q' => $filtros['q'] ?? '',
                'cidade' => $filtros['cidade'] ?? '',
                'uf' => $filtros['uf'] ?? '',
                'modelo' => $filtros['modelo'] ?? '',
                'contrato' => $filtros['contrato'] ?? '',
            ],
            'ufs' => Ufs::TODAS,
            'modelos' => FamiliaModelo::opcoes(),
        ]);
    }

    /** Autosugestão: nome (sem diferença de maiúsculas) ou começo do documento; só clientes do usuário. */
    public function busca(Request $request): JsonResponse
    {
        $termo = trim((string) $request->query('q', ''));

        // Vazio = o campo acabou de receber o foco: mostra os primeiros. Uma letra só ainda não busca.
        if (mb_strlen($termo) === 1) {
            return response()->json([]);
        }

        $clientes = $request->user()->clientes()
            ->when($termo !== '', fn ($consulta) => $consulta->comTermo($termo))
            ->orderBy('nome')->limit(10)->get(['id', 'nome', 'documento']);

        return response()->json($clientes->map(fn (Cliente $cliente) => $this->paraBusca($cliente)));
    }

    /** @return array{id: int, nome: string, documento: string} */
    private function paraBusca(Cliente $cliente): array
    {
        return ['id' => $cliente->id, 'nome' => $cliente->nome, 'documento' => Documento::formatar($cliente->documento)];
    }

    public function create(Request $request): Response
    {
        return $this->formulario($request, null);
    }

    public function store(ClienteRequest $request): RedirectResponse|JsonResponse
    {
        $cliente = $request->user()->clientes()->create($request->validated());

        // Cadastro rápido (modal no contrato/cobrança): devolve o cliente pronto para ser escolhido.
        if ($request->expectsJson()) {
            return response()->json($this->paraBusca($cliente), 201);
        }

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
