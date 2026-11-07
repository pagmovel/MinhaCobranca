<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\AlterarSenhaRequest;
use App\Models\Auditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class SenhaController extends Controller
{
    /** Troca a senha de quem está logado. As outras sessões continuam abertas (decisão do plano dos temas). */
    public function atualizar(AlterarSenhaRequest $request): RedirectResponse
    {
        $usuario = $request->user();

        DB::transaction(function () use ($request, $usuario) {
            $usuario->password = $request->validated('nova_senha'); // o cast `hashed` grava o hash
            $usuario->save();

            // Sem antes/depois e sem motivo: nada da senha, nem o hash, entra na auditoria.
            Auditoria::registrar($request, 'usuario.senha_alterada', $usuario, 'Senha alterada');
        });

        return back()->with('sucesso', 'Senha alterada.');
    }
}
