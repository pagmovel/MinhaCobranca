<?php

namespace App\Http\Controllers;

use App\Enums\StatusCobranca;
use App\Models\Cobranca;
use App\Models\CobrancaItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AvisoWhatsappController extends Controller
{
    /** Dados para montar a mensagem (servidor nunca manda itens de dois clientes juntos). */
    public function dados(Request $request): JsonResponse
    {
        $dados = $request->validate(['ids' => ['required', 'array', 'max:50'], 'ids.*' => ['integer']]);

        $cobrancas = $request->user()->cobrancas()->where('status', StatusCobranca::Pendente)
            ->whereIn('id', $dados['ids'])->with(['cliente', 'itens'])->get();

        abort_if($cobrancas->isEmpty(), 404);
        abort_if($cobrancas->pluck('cliente_id')->unique()->count() > 1, 422, 'As cobranças escolhidas são de clientes diferentes.');

        $itens = $cobrancas->flatMap(function (Cobranca $cobranca) {
            if ($cobranca->itens->isNotEmpty()) {
                return $cobranca->itens->map(fn (CobrancaItem $item) => ['nome' => $item->nome, 'valor_centavos' => $item->total_centavos]);
            }

            // `Str::of(...)->before(...)->trim()` devolve um Stringable: objeto é sempre truthy, então `?:` nunca cairia no "Serviço".
            $primeiraLinha = (string) Str::of($cobranca->descricao)->before("\n")->trim();

            return collect([['nome' => $primeiraLinha !== '' ? $primeiraLinha : 'Serviço', 'valor_centavos' => $cobranca->valor_centavos]]);
        });

        return response()->json([
            'cliente' => $cobrancas->first()->cliente->exibicao(),
            'telefone' => $cobrancas->first()->cliente->telefone ?? '',
            'itens' => $itens->values(),
            'vencimentos' => $cobrancas->pluck('vencimento')->unique()->sort()->map(fn ($data) => $data->toDateString())->values(),
            'total_centavos' => $cobrancas->sum('valor_centavos'),
        ]);
    }

    /** Marca como avisadas: só as próprias e pendentes (ids alheios, pagas ou canceladas são ignorados). */
    public function avisadas(Request $request): RedirectResponse
    {
        $dados = $request->validate(['ids' => ['required', 'array', 'max:50'], 'ids.*' => ['integer']]);

        $quantas = $request->user()->cobrancas()->where('status', StatusCobranca::Pendente)
            ->whereIn('id', $dados['ids'])->update(['avisado_em' => now()]);

        return back()->with('sucesso', $quantas === 1 ? '1 cobrança marcada como avisada.' : "{$quantas} cobranças marcadas como avisadas.");
    }
}
