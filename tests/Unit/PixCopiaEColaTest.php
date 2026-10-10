<?php

namespace Tests\Unit;

use App\Support\PixCopiaECola;
use PHPUnit\Framework\TestCase;

class PixCopiaEColaTest extends TestCase
{
    public function test_crc_do_exemplo_oficial_do_banco_central(): void
    {
        // Exemplo do Manual de Padrões para Iniciação do Pix (conferido no passo 1).
        $exemplo = '00020126580014br.gov.bcb.pix0136123e4567-e12b-12d1-a456-4266554400005204000053039865802BR5913Fulano de Tal6008BRASILIA62070503***63041D3D';

        $this->assertSame(substr($exemplo, -4), PixCopiaECola::crc16(substr($exemplo, 0, -4)));
    }

    public function test_monta_payload_com_valor_identificacao_e_textos_no_limite(): void
    {
        $payload = PixCopiaECola::gerar('+5579999990000', 'José da Silva Prestação de Serviços Ltda', 'Nossa Senhora do Socorro', 26060, 'REC2026000016');

        $this->assertStringStartsWith('000201', $payload);
        $this->assertStringContainsString('0014br.gov.bcb.pix0114+5579999990000', $payload);
        $this->assertStringContainsString('52040000530398654062', $payload);
        $this->assertStringContainsString('5406260.60', $payload);
        $this->assertStringContainsString('5802BR5925JOSE DA SILVA PRESTACAO D6015NOSSA SENHORA D', $payload);
        $this->assertStringContainsString('62170513REC2026000016', $payload);
        $this->assertSame(PixCopiaECola::crc16(substr($payload, 0, -4)), substr($payload, -4));
        $this->assertSame('6304', substr($payload, -8, 4));
    }

    public function test_txid_vazio_vira_tres_asteriscos(): void
    {
        $this->assertStringContainsString('62070503***', PixCopiaECola::gerar('fulano@exemplo.com', 'Fulano', 'Aracaju', 100, ''));
    }
}
