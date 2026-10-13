<?php

use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\Auth\CadastroController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AvisoWhatsappController;
use App\Http\Controllers\ChavePixController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CobrancaController;
use App\Http\Controllers\ConsultaController;
use App\Http\Controllers\ContratoController;
use App\Http\Controllers\FeriadoController;
use App\Http\Controllers\ModeloMensagemController;
use App\Http\Controllers\PainelController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\ProdutoServicoController;
use App\Http\Controllers\ReciboPdfController;
use App\Http\Controllers\RelatorioController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/painel');

Route::middleware('guest')->group(function () {
    Route::get('/entrar', [LoginController::class, 'create'])->name('login');
    Route::post('/entrar', [LoginController::class, 'store'])->middleware('throttle:login');
    Route::get('/cadastro', [CadastroController::class, 'create']);
    Route::post('/cadastro', [CadastroController::class, 'store'])->middleware('throttle:6,1');
});

Route::middleware(['auth', 'conta.aprovada'])->group(function () {
    Route::post('/sair', [LoginController::class, 'destroy']);
    Route::get('/painel', PainelController::class)->name('painel');
    Route::get('/painel/dados', [PainelController::class, 'dados']);

    Route::get('/perfil', [PerfilController::class, 'edit']);
    Route::post('/perfil', [PerfilController::class, 'update']);
    Route::get('/perfil/logo', [PerfilController::class, 'logo']);
    Route::post('/perfil/apresentacao', [PerfilController::class, 'apresentacao']);
    Route::post('/perfil/chaves-pix', [ChavePixController::class, 'store']);
    Route::put('/perfil/chaves-pix/{chave}', [ChavePixController::class, 'update']);
    Route::delete('/perfil/chaves-pix/{chave}', [ChavePixController::class, 'destroy']);
    Route::post('/perfil/feriados/ajustar', [FeriadoController::class, 'ajustar']);
    Route::post('/perfil/feriados', [FeriadoController::class, 'store']);
    Route::put('/perfil/feriados/{feriado}', [FeriadoController::class, 'update']);
    Route::delete('/perfil/feriados/{feriado}', [FeriadoController::class, 'destroy']);
    Route::get('/dias-uteis/proximo', [FeriadoController::class, 'proximoDiaUtil']);
    Route::post('/perfil/mensagens', [ModeloMensagemController::class, 'store']);
    Route::put('/perfil/mensagens/{mensagem}', [ModeloMensagemController::class, 'update']);
    Route::delete('/perfil/mensagens/{mensagem}', [ModeloMensagemController::class, 'destroy']);

    Route::get('/clientes/busca', [ClienteController::class, 'busca']);
    Route::get('/clientes/dados', [ClienteController::class, 'dados']);
    Route::resource('clientes', ClienteController::class)->except(['show', 'create', 'store']);
    Route::resource('clientes', ClienteController::class)->only(['create', 'store'])->middleware('progresso:perfil');
    Route::get('/contratos/retroativos', [ContratoController::class, 'retroativos']);
    Route::get('/contratos/dados', [ContratoController::class, 'dados']);
    Route::resource('contratos', ContratoController::class)->except(['show', 'destroy']);
    Route::get('/contratos/{contrato}/encerramento', [ContratoController::class, 'encerramento']);
    Route::post('/contratos/{contrato}/encerrar', [ContratoController::class, 'encerrar']);
    Route::post('/contratos/{contrato}/recomecar', [ContratoController::class, 'recomecar']);
    Route::get('/contratos/{contrato}/renovacao', [ContratoController::class, 'renovacao']);
    Route::post('/contratos/{contrato}/renovar', [ContratoController::class, 'renovar']);
    Route::post('/contratos/{contrato}/renovar-vencido', [ContratoController::class, 'renovarVencido']);
    Route::get('/contratos/{contrato}/reativacao', [ContratoController::class, 'reativacao']);
    Route::get('/produtos-servicos/busca', [ProdutoServicoController::class, 'busca']);
    Route::get('/produtos-servicos/dados', [ProdutoServicoController::class, 'dados']);
    Route::resource('produtos-servicos', ProdutoServicoController::class)
        ->except(['show', 'create', 'store'])->parameters(['produtos-servicos' => 'produto']);
    Route::resource('produtos-servicos', ProdutoServicoController::class)
        ->only(['create', 'store'])->parameters(['produtos-servicos' => 'produto'])->middleware('progresso:perfil');
    Route::get('/produtos-servicos/{produto}/reajuste', [ProdutoServicoController::class, 'reajuste']);
    Route::post('/produtos-servicos/{produto}/reajuste', [ProdutoServicoController::class, 'reajustar']);
    Route::middleware('throttle:30,1')->group(function () {
        Route::get('/consultas/cnpj/{cnpj}', [ConsultaController::class, 'cnpj'])->where('cnpj', '[0-9A-Za-z]{1,20}');
        Route::get('/consultas/cep/{cep}', [ConsultaController::class, 'cep'])->where('cep', '[0-9\-]{1,10}');
    });
    Route::get('/relatorios', [RelatorioController::class, 'index']);
    Route::get('/relatorios/{tipo}', [RelatorioController::class, 'show'])->whereIn('tipo', RelatorioController::TIPOS);
    Route::get('/relatorios/{tipo}/dados', [RelatorioController::class, 'dados'])->whereIn('tipo', RelatorioController::TIPOS);

    Route::get('/cobrancas', [CobrancaController::class, 'index']);
    Route::get('/cobrancas/dados', [CobrancaController::class, 'dados']);
    Route::get('/cobrancas/create', [CobrancaController::class, 'create']);
    Route::get('/cobrancas/aviso', [AvisoWhatsappController::class, 'dados']);
    Route::post('/cobrancas/avisadas', [AvisoWhatsappController::class, 'avisadas']);
    Route::get('/cobrancas/{cobranca}/edit', [CobrancaController::class, 'edit']);
    Route::post('/cobrancas', [CobrancaController::class, 'store']);
    Route::put('/cobrancas/{cobranca}', [CobrancaController::class, 'update']);
    Route::post('/cobrancas/lote/pagar', [CobrancaController::class, 'pagarEmLote']);
    Route::post('/cobrancas/lote/cancelar', [CobrancaController::class, 'cancelarEmLote']);
    Route::post('/cobrancas/{cobranca}/cancelar', [CobrancaController::class, 'cancelar']);
    Route::post('/cobrancas/{cobranca}/prorrogar', [CobrancaController::class, 'prorrogar']);
    Route::post('/cobrancas/{cobranca}/pagar', [CobrancaController::class, 'pagar']);
    Route::post('/cobrancas/{cobranca}/enviada', [CobrancaController::class, 'marcarEnviada']);
    Route::put('/cobrancas/{cobranca}/pagamento', [CobrancaController::class, 'pagamento']);
    // Pedem a senha: limite de tentativas por usuário.
    Route::middleware('throttle:6,1')->group(function () {
        Route::post('/cobrancas/{cobranca}/corrigir-pagamento', [CobrancaController::class, 'corrigirPagamento']);
        Route::post('/cobrancas/{cobranca}/desfazer-baixa', [CobrancaController::class, 'desfazerBaixa']);
    });
    Route::get('/auditoria', [AuditoriaController::class, 'index']);
    Route::get('/auditoria/dados', [AuditoriaController::class, 'dados']);

    Route::get('/recibos/{recibo}/pdf', ReciboPdfController::class);

    // Fases seguintes acrescentam rotas aqui, acima do grupo admin.

    Route::middleware('can:admin')->prefix('admin')->group(function () {
        Route::get('/usuarios', [UsuarioController::class, 'index']);
        Route::get('/usuarios/dados', [UsuarioController::class, 'dados']);
        Route::post('/usuarios/lote/aprovar', [UsuarioController::class, 'aprovarEmLote']);
        Route::post('/usuarios/lote/bloquear', [UsuarioController::class, 'bloquearEmLote']);
        Route::post('/usuarios/{usuario}/aprovar', [UsuarioController::class, 'aprovar']);
        Route::post('/usuarios/{usuario}/bloquear', [UsuarioController::class, 'bloquear']);
    });
});
