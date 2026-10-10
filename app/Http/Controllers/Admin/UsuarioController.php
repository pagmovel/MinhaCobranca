<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusUsuario;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UsuarioController extends Controller
{
    public function index(): Response
    {
        $usuarios = User::query()
            ->orderByRaw("case when status = 'pendente' then 0 else 1 end")
            ->orderBy('name')
            ->get()
            ->map(fn (User $usuario) => [
                'id' => $usuario->id,
                'nome' => $usuario->name,
                'email' => $usuario->email,
                'status' => $usuario->status->value,
                'is_admin' => $usuario->is_admin,
                'cadastrado_em' => $usuario->created_at?->format('d/m/Y'),
            ]);

        return Inertia::render('Admin/Usuarios', ['usuarios' => $usuarios]);
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
