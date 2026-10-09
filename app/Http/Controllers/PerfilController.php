<?php

namespace App\Http\Controllers;

use App\Enums\FamiliaModelo;
use App\Http\Requests\PerfilRequest;
use App\Support\Ufs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PerfilController extends Controller
{
    private const CAMPOS = [
        'name', 'email', 'documento', 'nome_fantasia', 'inscricao_municipal', 'inscricao_estadual',
        'telefone', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'cep',
        'dias_antecedencia',
    ];

    public function edit(Request $request): Response
    {
        $usuario = $request->user();

        return Inertia::render('Perfil/Editar', [
            'perfil' => array_map(fn ($valor) => $valor ?? '', $usuario->only(self::CAMPOS)) + [
                'modelo_padrao' => $usuario->modelo_padrao->value,
                'tem_logo' => filled($usuario->logo),
            ],
            'modelos' => FamiliaModelo::opcoes(),
            'ufs' => Ufs::TODAS,
        ]);
    }

    public function update(PerfilRequest $request): RedirectResponse
    {
        $usuario = $request->user();
        $usuario->fill($request->safe()->except('logo'));

        if ($request->hasFile('logo')) {
            // Nome com hash: o arquivo anterior fica, porque recibos já emitidos apontam para ele.
            $usuario->logo = $request->file('logo')->store("logos/{$usuario->id}", 'local');
        }

        $usuario->save();

        return back()->with('sucesso', 'Perfil salvo.');
    }

    public function logo(Request $request): StreamedResponse
    {
        $caminho = $request->user()->logo;
        abort_if(blank($caminho) || ! Storage::disk('local')->exists($caminho), 404);

        return Storage::disk('local')->response($caminho);
    }
}
