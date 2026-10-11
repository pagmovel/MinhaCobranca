<?php

namespace Tests\Feature\Recibos;

use App\Enums\FormaPagamento;
use App\Enums\TipoChavePix;
use App\Models\ChavePix;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\User;
use App\Services\EmissorDeRecibos;
use App\Services\RenderizadorDeRecibo;
use App\Support\LinhasDoRecibo;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('visual')]
class AmostrasVisuaisTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    private ChavePix $chave;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-13 14:30:53'));
        $this->usuario = User::factory()->create([
            'name' => 'Prestador Exemplo da Silva Santos',
            'documento' => '52998224725',
            'inscricao_municipal' => '1000736',
            'telefone' => '(79) 99999-0000',
            'logradouro' => 'Rua A-4 (Conjunto Exemplo I)',
            'numero' => 'M.06',
            'bairro' => 'Bairro Exemplo',
            'cidade' => 'Nossa Senhora do Socorro',
            'uf' => 'SE',
            'cep' => '49160000',
        ]);
        $this->chave = ChavePix::factory()->for($this->usuario)->create(['tipo' => TipoChavePix::Email, 'chave' => 'financeiro.exemplo@exemplo.com.br']);
    }

    private function pdf(string $modelo, string $descricao, array $pagamento = [])
    {
        $cliente = Cliente::factory()->for($this->usuario)->create([
            'nome' => 'Sindicato Exemplo dos Trabalhadores em Educação do Estado',
            // Pior caso da linha "Unidade atendida" (-v2): o apelido no limite de 60 caracteres.
            'apelido' => mb_substr('Delegacia Sindical do Sisal - Regional de Araci, Bahia e Região', 0, 60),
            'modelo' => $modelo,
            'logradouro' => 'Rua Exemplo Oliveira Lima',
            'complemento' => 'Delegacia Sindical do Sisal',
        ]);
        $cobranca = Cobranca::factory()->for($this->usuario)->for($cliente)->create([
            'descricao' => $descricao,
            'codigo_servico' => '0102 - Programação',
            'codigo_nbs' => '115061000',
            'codigo_tributacao_nacional' => '01.02.01',
            'periodo_inicio' => '2026-09-21',
            'periodo_fim' => '2026-10-20',
            'vencimento' => '2026-09-21',
        ] + $pagamento);

        return app(RenderizadorDeRecibo::class)->pdf(app(EmissorDeRecibos::class)->emitir($cobranca), CarbonImmutable::now());
    }

    public function test_gera_amostras_dos_dois_modelos(): void
    {
        foreach (['webiss', 'danfse'] as $modelo) {
            $pdf = $this->pdf($modelo, "- Hospedagem do domínio exemplo.com.br\n- Servidor de Arquivos\n- Pacote 5 subdomínios\n- Pacote 5 bancos de dados")->output();

            $this->assertStringStartsWith('%PDF', $pdf);
            // Vai por WhatsApp: fonte embutida só com os caracteres usados.
            $this->assertLessThan(200_000, strlen($pdf), "modelo {$modelo}");
            Storage::disk('local')->put("verificacao/amostra-{$modelo}.pdf", $pdf);

            // Amostra com PIX para a conferência visual (V4) e para escanear o QR no celular.
            $comPix = $this->pdf($modelo, "- Hospedagem do domínio exemplo.com.br\n- Servidor de Arquivos", ['forma_pagamento' => FormaPagamento::Pix, 'chave_pix_id' => $this->chave->id])->output();
            Storage::disk('local')->put("verificacao/amostra-{$modelo}-pix.pdf", $comPix);
        }
    }

    /** Limite da descrição (25 linhas, `LinhasMaximas`) não pode empurrar o recibo para a segunda página. */
    public function test_descricao_no_limite_cabe_em_uma_pagina(): void
    {
        $curtas = implode("\n", array_map(fn (int $linha) => "- Item {$linha} do serviço prestado no período", range(1, 25)));
        // Parágrafo sem Enter: o dompdf quebraria sozinho e sobreporia as linhas seguintes (revisão final).
        $paragrafo = mb_substr(str_repeat('Manutenção mensal do servidor e suporte técnico remoto ', 40), 0, 1900);
        $mista = $paragrafo."\n".implode("\n", array_fill(0, 25 - count(LinhasDoRecibo::quebrar($paragrafo)), '- Linha curta'));
        $this->assertCount(25, LinhasDoRecibo::quebrar($mista));

        $formas = [
            'nenhuma' => [],
            'pix' => ['forma_pagamento' => FormaPagamento::Pix, 'chave_pix_id' => $this->chave->id],
            'boleto' => ['forma_pagamento' => FormaPagamento::Boleto, 'linha_digitavel' => '23793381286000000000300000000400184340000026060'],
        ];

        foreach (['webiss' => $curtas, 'danfse' => $curtas, 'webiss-paragrafo' => $mista] as $amostra => $descricao) {
            foreach ($formas as $forma => $pagamento) {
                $modelo = strtok($amostra, '-');
                $pdf = $this->pdf($modelo, $descricao, $pagamento);
                Storage::disk('local')->put("verificacao/amostra-{$amostra}-{$forma}-25-linhas.pdf", $pdf->output());

                $this->assertSame(1, $pdf->getDomPDF()->getCanvas()->get_page_count(), "amostra {$amostra} com {$forma}");
            }
        }
    }
}
