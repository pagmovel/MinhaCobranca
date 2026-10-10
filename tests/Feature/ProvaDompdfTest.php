<?php

namespace Tests\Feature;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProvaDompdfTest extends TestCase
{
    public function test_gera_pdf_de_prova_para_conferencia_visual(): void
    {
        $conteudo = Pdf::loadView('verificacao.prova-dompdf')->setPaper('a4', 'portrait')->output();

        $this->assertStringStartsWith('%PDF', $conteudo);

        // Arquivo real (não fake) para o usuário abrir e conferir; fica fora do git (storage/app/private).
        Storage::disk('local')->put('verificacao/prova-dompdf.pdf', $conteudo);
    }
}
