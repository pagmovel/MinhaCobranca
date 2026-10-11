<?php

namespace App\Http\Controllers;

use App\Enums\FamiliaModelo;
use App\Enums\TipoAvisoModelo;
use App\Enums\TipoChavePix;
use App\Http\Requests\PerfilRequest;
use App\Models\ChavePix;
use App\Models\Feriado;
use App\Services\AjusteDeVencimentos;
use App\Support\DiasUteis;
use App\Support\Ufs;
use Carbon\CarbonImmutable;
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
        'dias_antecedencia', 'dias_aviso',
    ];

    public function edit(Request $request, AjusteDeVencimentos $ajuste): Response
    {
        $usuario = $request->user();
        $ano = CarbonImmutable::today()->year;

        return Inertia::render('Perfil/Editar', [
            'perfil' => array_map(fn ($valor) => $valor ?? '', $usuario->only(self::CAMPOS)) + [
                'modelo_padrao' => $usuario->modelo_padrao->value,
                'tem_logo' => filled($usuario->logo),
            ],
            'modelos' => FamiliaModelo::opcoes(),
            'chavesPix' => $usuario->chavesPix()->orderBy('id')->get()->map(fn (ChavePix $chave) => [
                'id' => $chave->id,
                'tipo' => $chave->tipo->value,
                'tipo_rotulo' => $chave->tipo->rotulo(),
                'chave' => $chave->tipo->formatar($chave->chave),
                'apelido' => $chave->apelido ?? '',
            ]),
            'tiposChavePix' => TipoChavePix::opcoes(),
            'feriados' => $usuario->feriados()->get()->map(fn (Feriado $feriado) => [
                'id' => $feriado->id,
                'descricao' => $feriado->descricao,
                'tipo' => $feriado->tipo,
                'dia' => $feriado->dia ?? '',
                'mes' => $feriado->mes ?? '',
                'data' => $feriado->data?->toDateString() ?? '',
                'quando' => $feriado->quando($ano),
            ]),
            'feriadosNacionais' => collect((new DiasUteis)->nacionais($ano))
                ->map(fn (string $nome, string $data) => ['data' => CarbonImmutable::parse($data)->format('d/m'), 'descricao' => $nome])
                ->values(),
            // Recalculada a cada abertura: cadastrar ou excluir feriado volta para cá e a prévia já vem atualizada.
            'vencimentosParaAjustar' => $ajuste->previa($usuario),
            'modelosMensagem' => $usuario->opcoesDeModelosMensagem(),
            'tiposAvisoModelo' => TipoAvisoModelo::opcoes(),
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
