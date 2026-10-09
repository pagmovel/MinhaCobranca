<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PainelController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $hoje = CarbonImmutable::today();

        return Inertia::render('Painel', [
            'mes' => Str::ucfirst($hoje->locale('pt_BR')->translatedFormat('F \d\e Y')),
            'perfilCompleto' => $request->user()->perfilCompleto(),
        ]);
    }
}
