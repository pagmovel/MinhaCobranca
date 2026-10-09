<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class CadastroController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Cadastro');
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        // Nasce pendente (default da coluna) e não entra: o admin libera depois.
        User::create($dados);

        return redirect('/entrar')
            ->with('sucesso', 'Cadastro recebido. Você poderá entrar assim que o administrador liberar seu acesso.');
    }
}
