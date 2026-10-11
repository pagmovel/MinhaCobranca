<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeriadoRequest;
use App\Rules\AnoValido;
use App\Services\AjusteDeVencimentos;
use App\Support\DiasUteis;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FeriadoController extends Controller
{
    public function store(FeriadoRequest $request): RedirectResponse
    {
        $request->user()->feriados()->create($request->validated());

        return back()->with('sucesso', 'Feriado cadastrado.');
    }

    public function update(FeriadoRequest $request, int $feriado): RedirectResponse
    {
        $request->user()->feriados()->findOrFail($feriado)->update($request->validated());

        return back()->with('sucesso', 'Feriado atualizado.');
    }

    public function destroy(Request $request, int $feriado): RedirectResponse
    {
        $request->user()->feriados()->findOrFail($feriado)->delete();

        return back()->with('sucesso', 'Feriado excluído.');
    }

    public function ajustar(Request $request, AjusteDeVencimentos $ajuste): RedirectResponse
    {
        $dados = $request->validate(['ids' => ['required', 'array', 'max:1000'], 'ids.*' => ['integer']]);
        $quantas = $ajuste->ajustar($request, array_map('intval', $dados['ids']));

        return back()->with('sucesso', match ($quantas) {
            0 => 'Nenhuma cobrança precisava de ajuste.',
            1 => '1 cobrança ajustada.',
            default => "{$quantas} cobranças ajustadas.",
        });
    }

    /** Aviso da cobrança avulsa (spec seção 5): só leitura; a data digitada continua valendo. */
    public function proximoDiaUtil(Request $request): JsonResponse
    {
        $dados = $request->validate(['data' => ['required', 'date_format:Y-m-d', new AnoValido]]);
        $data = CarbonImmutable::createFromFormat('Y-m-d', $dados['data'])->startOfDay();
        $diasUteis = $request->user()->diasUteis();
        $motivo = $diasUteis->motivo($data);
        $proximo = $diasUteis->proximoDiaUtil($data);

        return response()->json([
            'util' => $motivo === null,
            'aviso' => match (true) {
                $motivo === null => null,
                in_array($motivo, ['sábado', 'domingo'], true) => "Cai num {$motivo}.",
                default => "É feriado: {$motivo}.",
            },
            'proximo' => $proximo->toDateString(),
            'proximo_rotulo' => DiasUteis::DIAS_DA_SEMANA[$proximo->dayOfWeek].', '.$proximo->format('d/m'),
        ]);
    }
}
