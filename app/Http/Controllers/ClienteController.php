<?php

namespace App\Http\Controllers;

use App\Enums\FamiliaModelo;
use App\Enums\StatusCobranca;
use App\Http\Requests\ClienteRequest;
use App\Models\Cliente;
use App\Services\EmissorDeRecibos;
use App\Support\BlocoDePaginas;
use App\Support\Busca;
use App\Support\Ufs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClienteController extends Controller
{
    public function index(Request $request): Response
    {
        // A lista vem por ajax de /clientes/dados; a página só abre a tela com os filtros.
        return Inertia::render('Clientes/Index', [
            'filtros' => $this->filtros($request),
            'ufs' => Ufs::TODAS,
            'modelos' => FamiliaModelo::opcoes(),
        ]);
    }

    /** Dados da lista (JSON, só leitura): um bloco de páginas. */
    public function dados(Request $request): JsonResponse
    {
        $filtros = $this->filtros($request);
        $consulta = $request->user()->clientes()->ordemDeExibicao()->orderBy('id')
            ->withCount(['contratos as contratos_ativos' => fn ($contratos) => $contratos->where('ativo', true)]);

        if ($filtros['q'] !== '') {
            $consulta->comTermo($filtros['q']);
        }
        if ($filtros['cidade'] !== '') {
            $consulta->whereRaw("lower(cidade) like ? escape '\\'", [Busca::contem($filtros['cidade'])]);
        }
        if ($filtros['uf'] !== '') {
            $consulta->where('uf', $filtros['uf']);
        }
        if ($filtros['modelo'] !== '') {
            $consulta->where('modelo', $filtros['modelo']);
        }
        if ($filtros['contrato'] !== '') {
            $ativo = fn ($contratos) => $contratos->where('ativo', true);

            if ($filtros['contrato'] === 'com') {
                $consulta->whereHas('contratos', $ativo);
            } else {
                $consulta->whereDoesntHave('contratos', $ativo);
            }
        }

        $bloco = BlocoDePaginas::de($consulta, $filtros['pagina'], $filtros['por_pagina']);

        return response()->json([
            'linhas' => $bloco['itens']->map(fn (Cliente $cliente) => [
                ...$cliente->paraBusca(),
                'cidade' => collect([$cliente->cidade, $cliente->uf])->filter()->implode(' / '),
                'modelo' => $cliente->modelo->rotulo(),
                'contratos_ativos' => $cliente->contratos_ativos,
            ])->all(),
            'pagina' => $bloco['pagina'],
            'filtros' => $filtros,
        ]);
    }

    /**
     * Filtros da lista, já no formato da tela ('' = sem filtro).
     *
     * @return array{q: string, cidade: string, uf: string, modelo: string, contrato: string, pagina: int, por_pagina: int}
     */
    private function filtros(Request $request): array
    {
        $validados = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:255'],
            'uf' => ['nullable', Rule::in(Ufs::TODAS)],
            'modelo' => ['nullable', Rule::enum(FamiliaModelo::class)],
            'contrato' => ['nullable', Rule::in(['com', 'sem'])],
            ...BlocoDePaginas::regras(),
        ]);

        return [
            'q' => trim($validados['q'] ?? ''),
            'cidade' => trim($validados['cidade'] ?? ''),
            'uf' => $validados['uf'] ?? '',
            'modelo' => $validados['modelo'] ?? '',
            'contrato' => $validados['contrato'] ?? '',
            'pagina' => (int) ($validados['pagina'] ?? 1),
            'por_pagina' => (int) ($validados['por_pagina'] ?? 25),
        ];
    }

    /** Autosugestão: nome ou apelido (sem diferença de maiúsculas) ou começo do documento; só clientes do usuário. */
    public function busca(Request $request): JsonResponse
    {
        $termo = trim((string) $request->query('q', ''));

        // Vazio = o campo acabou de receber o foco: mostra os primeiros. Uma letra só ainda não busca.
        if (mb_strlen($termo) === 1) {
            return response()->json([]);
        }

        $clientes = $request->user()->clientes()
            ->when($termo !== '', fn ($consulta) => $consulta->comTermo($termo))
            ->ordemDeExibicao()->limit(10)->get(['id', 'nome', 'apelido', 'documento']);

        return response()->json($clientes->map(fn (Cliente $cliente) => $cliente->paraBusca()));
    }

    public function create(Request $request): Response
    {
        return $this->formulario($request, null);
    }

    public function store(ClienteRequest $request): RedirectResponse|JsonResponse
    {
        $cliente = $request->user()->clientes()->create($request->safe()->except('regerar_enviadas'));

        // Cadastro rápido (modal no contrato/cobrança): devolve o cliente pronto para ser escolhido.
        if ($request->expectsJson()) {
            return response()->json($cliente->paraBusca(), 201);
        }

        return redirect('/clientes')->with('sucesso', 'Cliente salvo.');
    }

    public function edit(Request $request, int $cliente): Response
    {
        return $this->formulario($request, $request->user()->clientes()->findOrFail($cliente));
    }

    public function update(ClienteRequest $request, int $cliente, EmissorDeRecibos $emissor): RedirectResponse
    {
        $registro = $request->user()->clientes()->findOrFail($cliente);

        // Trocou o modelo do recibo: as cobranças em aberto passam a ele na mesma transação (Fase 22).
        $refeitas = DB::transaction(function () use ($registro, $request, $emissor) {
            $modeloAnterior = $registro->modelo;
            $registro->update($request->safe()->except('regerar_enviadas'));

            return $registro->modelo === $modeloAnterior ? 0 : $emissor->aplicarModeloDoCliente($registro, $request->boolean('regerar_enviadas'));
        });

        return redirect('/clientes')->with('sucesso', match ($refeitas) {
            0 => 'Cliente salvo.',
            1 => 'Cliente salvo. 1 recibo em aberto foi refeito no modelo novo.',
            default => "Cliente salvo. {$refeitas} recibos em aberto foram refeitos no modelo novo.",
        });
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
                ? ['id' => $cliente->id, 'modelo' => $cliente->modelo->value, 'apelido' => $cliente->apelido]
                    + array_map(fn ($valor) => $valor ?? '', $cliente->only(Cliente::CAMPOS))
                : null,
            // Prévia da troca de modelo: quantas cobranças em aberto seriam refeitas (Fase 22).
            'emAberto' => $cliente ? [
                'nao_enviadas' => $cliente->cobrancas()->where('status', StatusCobranca::Pendente)->whereNull('enviado_em')->count(),
                'enviadas' => $cliente->cobrancas()->where('status', StatusCobranca::Pendente)->whereNotNull('enviado_em')->count(),
            ] : null,
            'modeloPadrao' => $request->user()->modelo_padrao->value,
            'modelos' => FamiliaModelo::opcoes(),
            'ufs' => Ufs::TODAS,
        ]);
    }
}
