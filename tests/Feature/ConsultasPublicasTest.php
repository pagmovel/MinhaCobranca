<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConsultasPublicasTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** Banco do Brasil: cadastro público. */
    private const CNPJ = '00000000000191';

    private const FALHA = 'Não foi possível consultar agora; preencha manualmente.';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create());
    }

    private function respostaCnpj(array $extra = []): array
    {
        return array_merge([
            'cnpj' => self::CNPJ,
            'razao_social' => 'BANCO DO BRASIL SA',
            'nome_fantasia' => 'DIRECAO GERAL',
            'descricao_tipo_de_logradouro' => 'QUADRA',
            'logradouro' => 'SAUN QUADRA 5 BLOCO B TORRE I, II, III',
            'numero' => 'SN',
            'complemento' => 'ANDAR T I',
            'bairro' => 'ASA NORTE',
            'municipio' => 'BRASILIA',
            'uf' => 'DF',
            'cep' => '70040912',
            'qsa' => [['nome_socio' => 'FULANO DE TAL', 'cnpj_cpf_do_socio' => '***550179**']],
        ], $extra);
    }

    public function test_cnpj_traz_so_os_dados_da_empresa(): void
    {
        Http::fake(['brasilapi.com.br/api/cnpj/v1/*' => Http::response($this->respostaCnpj())]);

        $this->getJson('/consultas/cnpj/'.self::CNPJ)->assertOk()->assertExactJson([
            'nome' => 'BANCO DO BRASIL SA',
            'nome_fantasia' => 'DIRECAO GERAL',
            'logradouro' => 'SAUN QUADRA 5 BLOCO B TORRE I, II, III',
            'numero' => 'SN',
            'complemento' => 'ANDAR T I',
            'bairro' => 'ASA NORTE',
            'cidade' => 'BRASILIA',
            'uf' => 'DF',
            'cep' => '70040912',
        ]);
    }

    public function test_tipo_de_logradouro_entra_quando_falta_no_texto(): void
    {
        Http::fake(['brasilapi.com.br/*' => Http::response($this->respostaCnpj([
            'descricao_tipo_de_logradouro' => 'RUA', 'logradouro' => 'VIRGILIO OLIVEIRA LIMA',
        ]))]);

        $this->getJson('/consultas/cnpj/'.self::CNPJ)->assertJsonPath('logradouro', 'RUA VIRGILIO OLIVEIRA LIMA');
    }

    public function test_cpf_e_cnpj_invalido_nao_sao_consultados(): void
    {
        Http::fake();

        $this->getJson('/consultas/cnpj/52998224725')->assertStatus(422)
            ->assertJsonPath('mensagem', 'Informe um CNPJ válido para buscar os dados.');
        $this->getJson('/consultas/cnpj/11222333000180')->assertStatus(422);
        $this->getJson('/consultas/cep/1234')->assertStatus(422)->assertJsonPath('mensagem', 'Informe um CEP com 8 números.');

        Http::assertNothingSent();
    }

    public function test_falha_nao_encontrado_ou_resposta_ruim_dao_mensagem_e_nao_ficam_no_cache(): void
    {
        Http::fakeSequence('brasilapi.com.br/*')
            ->push(['message' => 'CNPJ não encontrado.', 'type' => 'not_found'], 404)
            ->push('<html>erro</html>', 500)
            ->push([], 200)
            ->pushFailedConnection()
            ->push($this->respostaCnpj());

        foreach (range(1, 4) as $tentativa) {
            $this->getJson('/consultas/cnpj/'.self::CNPJ)->assertNotFound()->assertJsonPath('mensagem', self::FALHA);
        }
        $this->getJson('/consultas/cnpj/'.self::CNPJ)->assertOk()->assertJsonPath('nome', 'BANCO DO BRASIL SA');
    }

    public function test_resposta_boa_fica_no_cache_por_30_dias(): void
    {
        Http::fake(['brasilapi.com.br/*' => Http::response($this->respostaCnpj())]);

        $this->getJson('/consultas/cnpj/'.self::CNPJ)->assertOk();
        $this->getJson('/consultas/cnpj/'.self::CNPJ)->assertOk();
        Http::assertSentCount(1);

        $this->travel(31)->days();
        $this->getJson('/consultas/cnpj/'.self::CNPJ)->assertOk();
        Http::assertSentCount(2);
    }

    public function test_cep_usa_o_viacep_e_cai_na_brasilapi(): void
    {
        Http::fake([
            'viacep.com.br/ws/01001000/json/' => Http::response([
                'cep' => '01001-000', 'logradouro' => 'Praça da Sé', 'bairro' => 'Sé', 'localidade' => 'São Paulo', 'uf' => 'SP',
            ]),
            'viacep.com.br/ws/49160000/json/' => Http::response(['erro' => 'true']),
            'brasilapi.com.br/api/cep/v1/49160000' => Http::response([
                'cep' => '49160000', 'state' => 'SE', 'city' => 'Nossa Senhora do Socorro', 'neighborhood' => '', 'street' => '',
            ]),
        ]);

        $this->getJson('/consultas/cep/01001-000')->assertOk()->assertExactJson([
            'logradouro' => 'Praça da Sé', 'bairro' => 'Sé', 'cidade' => 'São Paulo', 'uf' => 'SP',
        ]);
        $this->getJson('/consultas/cep/49160000')->assertOk()
            ->assertJsonPath('cidade', 'Nossa Senhora do Socorro')
            ->assertJsonPath('uf', 'SE');
    }

    public function test_limite_de_30_consultas_por_minuto(): void
    {
        Http::fake(['*' => Http::response(['erro' => 'true'])]);

        foreach (range(1, 30) as $tentativa) {
            $this->getJson('/consultas/cep/01001000')->assertNotFound();
        }
        $this->getJson('/consultas/cep/01001000')->assertTooManyRequests();
    }

    public function test_visitante_nao_consulta(): void
    {
        auth()->logout();

        $this->getJson('/consultas/cep/01001000')->assertUnauthorized();
    }
}
