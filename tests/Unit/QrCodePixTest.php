<?php

namespace Tests\Unit;

use App\Support\QrCodePix;
use PHPUnit\Framework\TestCase;

class QrCodePixTest extends TestCase
{
    public function test_gera_png_quadrado_em_data_uri(): void
    {
        $uri = QrCodePix::dataUri('00020126360014br.gov.bcb.pix0114+55799999900005204000053039865406260.605802BR5906FULANO6007ARACAJU62070503***6304ABCD');

        $this->assertStringStartsWith('data:image/png;base64,', $uri);
        $png = base64_decode(substr($uri, strlen('data:image/png;base64,')), true);
        $this->assertStringStartsWith("\x89PNG", $png);
        [$largura, $altura] = getimagesizefromstring($png);
        $this->assertSame($largura, $altura);
        $this->assertGreaterThanOrEqual(150, $largura);
    }
}
