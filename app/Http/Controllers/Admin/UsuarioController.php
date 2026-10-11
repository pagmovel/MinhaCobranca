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
    public function index(Request $request): Response
    {
        // A lista vem por ajax de /admin/usuarios/dados; a página só abre a tela com os filtros.
        return Inertia::render('Admin/Usuarios', ['filtros' => $this->filtros($request)]);
    }

    /** Dados da lista (JSON, só leitura): pendentes primeiro, depois nome. */
    public function dados(Request $request): JsonResponse
    {
        $filtros = $this->filtros($request);
        $consulta = User::query()
            ->orderByRaw("case when status = 'pendente' then 0 else 1 end")
            ->orderBy('name')
            ->orderBy('id');

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
     * @return array{q: string, status: string, pagina: int, por_pagina: int}
     */
    private function filtros(Request $request): array
    {
        $validados = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(StatusUsuario::class)],
            ...BlocoDePaginas::regras(),
        ]);

        return [
            'q' => trim($validados['q'] ?? ''),
            'status' => $validados['status'] ?? '',
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

    public function bloquear(Request $request, User $usuario): RedirectResponse
    {
        if ($usuario->is($request->user())) {
            return back()->with('erro', 'Você não pode bloquear a própria conta.');
        }

        $usuario->status = StatusUsuario::Bloqueado;
        $usuario->save();

        return back()->with('sucesso', "Acesso de {$usuario->name} bloqueado.");
    }
}
