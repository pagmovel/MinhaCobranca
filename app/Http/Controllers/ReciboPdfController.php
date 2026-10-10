<?php

namespace App\Http\Controllers;

use App\Services\RenderizadorDeRecibo;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ReciboPdfController extends Controller
{
    public function __invoke(Request $request, int $recibo, RenderizadorDeRecibo $renderizador): Response
    {
        $registro = $request->user()->recibos()->with('cobranca')->findOrFail($recibo);

        try {
            return $renderizador->pdf($registro, CarbonImmutable::now())->stream($registro->nomeArquivo());
        } catch (Throwable $erro) {
            report($erro);

            return back()->with('erro', 'Não foi possível gerar o recibo. Tente de novo.');
        }
    }
}
