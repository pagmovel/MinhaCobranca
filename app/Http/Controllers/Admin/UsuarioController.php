<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusUsuario;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\BlocoDePaginas;
use App\Support\Busca;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UsuarioController extends Controller
{
    /** Colunas ordenáveis pelo cabeçalho da tela (nome da coluna real por trás do nome exposto). */
    private const COLUNAS_ORDENACAO = ['nome' => 'name', 'email' => 'email', 'situacao' => 'status', 'cadastrado_em' => 'created_at'];

    public function index(Request $request): Response
    {
        // A lista vem por ajax de /admin/usuarios/dados; a página só abre a tela com os filtros.
        return Inertia::render('Admin/Usuarios', ['filtros' => $this->filtros($request)]);
    }

    /** Dados da lista (JSON, só leitura): ordem alfabética por nome por padrão; cabeçalho muda `ordenar`/`direcao`. */
    public function dados(Request $request): JsonResponse
    {
        $filtros = $this->filtros($request);
        $coluna = self::COLUNAS_ORDENACAO[$filtros['ordenar']];
        $consulta = $filtros['ordenar'] === 'cadastrado_em'
            ? User::query()->orderBy($coluna, $filtros['direcao'])
            : User::query()->orderByRaw("lower({$coluna}) {$filtros['direcao']}");
        $consulta->orderBy('id');

        if ($filtros['q'] !== '') {
            $termo = Busca::contem($filtros['q']);
            $consulta->where(fn ($busca) => $busca->whereRaw("lower(name) like ? escape '\\'", [$termo])->orWhereRaw("lower(email) like ? escape '\\'", [$termo]));
        }
        if ($filtros['status'] !== '') {
            $consulta->where('status', $filtros['status']);
        }

        $bloco = BlocoDePaginas::de($consulta, $filtros['pagina'], $filtros['por_pagina']);

        return response()->json([
            'linhas' => $bloco['itens']->map(fn (User $usuario) => [
                'id' => $usuario->id,
                'nome' => $usuario->name,
                'email' => $usuario->email,
                'status' => $usuario->status->value,
                'is_admin' => $usuario->is_admin,
                'cadastrado_em' => $usuario->created_at?->format('d/m/Y'),
                // A tela não oferece "Bloquear" na própria conta (o servidor também recusa).
                'eu' => $usuario->is($request->user()),
            ])->all(),
            'pagina' => $bloco['pagina'],
            'filtros' => $filtros,
        ]);
    }

    /**
     * Filtros da lista, já no formato da tela ('' = sem filtro).
     *
     * @return array{q: string, status: string, ordenar: string, direcao: string, pagina: int, por_pagina: int}
     */
    private function filtros(Request $request): array
    {
        $validados = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(StatusUsuario::class)],
            'ordenar' => ['nullable', Rule::in(array_keys(self::COLUNAS_ORDENACAO))],
            'direcao' => ['nullable', Rule::in(['asc', 'desc'])],
            ...BlocoDePaginas::regras(),
        ]);

        return [
            'q' => trim($validados['q'] ?? ''),
            'status' => $validados['status'] ?? '',
            'ordenar' => $validados['ordenar'] ?? 'nome',
            'direcao' => $validados['direcao'] ?? 'asc',
            'pagina' => (int) ($validados['pagina'] ?? 1),
            'por_pagina' => (int) ($validados['por_pagina'] ?? 25),
        ];
    }

    /** Também serve para reativar quem estava bloqueado. */
    public function aprovar(User $usuario): RedirectResponse
    {
        $usuario->status = StatusUsuario::Aprovado;
        $usuario->save();

        return back()->with('sucesso', "Acesso de {$usuario->name} liberado.");
    }

    /** Libera (ou reativa) vários de uma vez; quem já está liberado é ignorado. */
    public function aprovarEmLote(Request $request): RedirectResponse
    {
        $dados = $request->validate(['ids' => ['required', 'array', 'max:200'], 'ids.*' => ['integer']]);

        $pedidos = count($dados['ids']);
        $usuarios = User::whereIn('id', $dados['ids'])->where('status', '!=', StatusUsuario::Aprovado)->get();

        foreach ($usuarios as $usuario) {
            $usuario->status = StatusUsuario::Aprovado;
            $usuario->save();
        }

        $ignorados = $pedidos - $usuarios->count();
        $resumo = "{$usuarios->count()} usuário(s) liberado(s).";
        if ($ignorados > 0) {
            $resumo .= " {$ignorados} ignorado(s) (já ".($ignorados === 1 ? 'estava' : 'estavam').' liberado'.($ignorados === 1 ? '' : 's').').';
        }

        return back()->with('sucesso', $resumo);
    }

    public function bloquear(Request $request, User $usuario): RedirectResponse
    {
        if ($usuario->is($request->user())) {
            return back()->with('erro', 'Você não pode bloquear a própria conta.');
        }

        $usuario->status = StatusUsuario::Bloqueado;
        $usuario->save();

        return back()->with('sucesso', "Acesso de {$usuario->name} bloqueado.");
    }

    /** Bloqueia vários de uma vez; quem já está bloqueado ou é a própria conta é ignorado. */
    public function bloquearEmLote(Request $request): RedirectResponse
    {
        $dados = $request->validate(['ids' => ['required', 'array', 'max:200'], 'ids.*' => ['integer']]);

        $pedidos = count($dados['ids']);
        $usuarios = User::whereIn('id', $dados['ids'])
            ->where('status', '!=', StatusUsuario::Bloqueado)
            ->where('id', '!=', $request->user()->id)
            ->get();

        foreach ($usuarios as $usuario) {
            $usuario->status = StatusUsuario::Bloqueado;
            $usuario->save();
        }

        $ignorados = $pedidos - $usuarios->count();
        $resumo = "{$usuarios->count()} usuário(s) bloqueado(s).";
        if ($ignorados > 0) {
            $resumo .= ' '.$ignorados.' ignorado(s) (já '.($ignorados === 1 ? 'estava bloqueado' : 'estavam bloqueados').' ou é sua própria conta).';
        }

        return back()->with('sucesso', $resumo);
    }
}
