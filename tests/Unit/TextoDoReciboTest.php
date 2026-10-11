<?php

namespace Tests\Unit;

use App\Support\TextoDoRecibo;
use PHPUnit\Framework\TestCase;

class TextoDoReciboTest extends TestCase
{
    private function item(string $nome, string $quantidade, string $unidade, int $preco, int $total): array
    {
        return ['nome' => $nome, 'quantidade' => $quantidade, 'unidade' => $unidade, 'preco_unitario_centavos' => $preco, 'total_centavos' => $total];
    }

    public function test_um_item_por_linha_com_a_conta_e_a_descricao_depois(): void
    {
        $texto = TextoDoRecibo::compor([
            $this->item('Suporte técnico', '1.500', 'hora', 12000, 18000),
            $this->item('Hospedagem de site', '1.000', 'mês', 24757, 24757),
        ], "Domínio aplbaraci.com.br\nReferente a outubro");

        $this->assertSame(
            "Suporte técnico: 1,5 hora × R$ 120,00 = R$ 180,00\n"
            ."Hospedagem de site: 1 mês × R$ 247,57 = R$ 247,57\n"
            ."\n"
            ."Domínio aplbaraci.com.br\nReferente a outubro",
            $texto,
        );
    }

    public function test_sem_descricao_so_os_itens_e_sem_itens_so_a_descricao(): void
    {
        $this->assertSame('Suporte técnico: 2 hora × R$ 120,00 = R$ 240,00', TextoDoRecibo::compor([
            $this->item('Suporte técnico', '2.000', 'hora', 12000, 24000),
        ], "  \n "));
        $this->assertSame('Hospedagem do domínio exemplo.com.br', TextoDoRecibo::compor([], 'Hospedagem do domínio exemplo.com.br'));
        $this->assertSame('', TextoDoRecibo::compor([], null));
    }

    public function test_em_linha_para_o_modelo_nacional(): void
    {
        $this->assertSame(
            'Suporte técnico: 1,5 hora × R$ 120,00 = R$ 180,00; Hospedagem de site: 1 mês × R$ 247,57 = R$ 247,57; Domínio aplbaraci.com.br Referente a outubro',
            TextoDoRecibo::emLinha([
                $this->item('Suporte técnico', '1.500', 'hora', 12000, 18000),
                $this->item('Hospedagem de site', '1.000', 'mês', 24757, 24757),
            ], "Domínio aplbaraci.com.br\nReferente a outubro"),
        );
    }
}
