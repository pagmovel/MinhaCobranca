<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProxyConfiavelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_teste-proxy', fn (Request $request) => ['ip' => $request->ip(), 'https' => $request->secure()]);
    }

    public function test_atras_do_proxy_da_rede_docker_usa_ip_e_https_repassados(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.7'])
            ->withHeaders(['X-Forwarded-For' => '200.10.20.30', 'X-Forwarded-Proto' => 'https'])
            ->get('/_teste-proxy')
            ->assertExactJson(['ip' => '200.10.20.30', 'https' => true]);
    }

    public function test_cliente_direto_nao_consegue_forjar_o_ip(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '200.10.20.30'])
            ->withHeaders(['X-Forwarded-For' => '1.2.3.4', 'X-Forwarded-Proto' => 'https'])
            ->get('/_teste-proxy')
            ->assertExactJson(['ip' => '200.10.20.30', 'https' => false]);
    }

    public function test_ip_forjado_antes_do_proxy_e_ignorado(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.7'])
            ->withHeaders(['X-Forwarded-For' => '1.2.3.4, 200.10.20.30'])
            ->get('/_teste-proxy')
            ->assertJsonPath('ip', '200.10.20.30');
    }
}
