<?php

namespace App\Http\Middleware;

use App\Enums\StatusUsuario;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Derruba, no próximo clique, quem foi bloqueado (ou voltou a pendente) com a sessão aberta. */
class GarantirContaAprovada
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario && ! $usuario->aprovado()) {
            $mensagem = $usuario->status === StatusUsuario::Bloqueado
                ? 'Seu acesso foi bloqueado. Fale com o administrador.'
                : 'Seu cadastro aguarda liberação do administrador.';

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/entrar')->with('erro', $mensagem);
        }

        return $next($request);
    }
}
