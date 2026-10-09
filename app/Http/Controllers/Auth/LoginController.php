<?php

namespace App\Http\Controllers\Auth;

use App\Enums\StatusUsuario;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Entrar');
    }

    public function store(Request $request): RedirectResponse
    {
        $credenciais = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credenciais, $request->boolean('lembrar'))) {
            throw ValidationException::withMessages(['email' => 'E-mail ou senha incorretos.']);
        }

        $usuario = $request->user();

        if (! $usuario->aprovado()) {
            $this->encerrarSessao($request);

            throw ValidationException::withMessages([
                'email' => $usuario->status === StatusUsuario::Bloqueado
                    ? 'Seu acesso foi bloqueado. Fale com o administrador.'
                    : 'Seu cadastro aguarda liberação do administrador.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended('/painel');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->encerrarSessao($request);

        return redirect('/entrar');
    }

    private function encerrarSessao(Request $request): void
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
