<?php

use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\Auth\CadastroController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\PainelController;
use App\Http\Controllers\PerfilController;
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

    Route::get('/perfil', [PerfilController::class, 'edit']);
    Route::post('/perfil', [PerfilController::class, 'update']);
    Route::get('/perfil/logo', [PerfilController::class, 'logo']);

    Route::resource('clientes', ClienteController::class)->except('show');

    // Fases seguintes acrescentam rotas aqui, acima do grupo admin.

    Route::middleware('can:admin')->prefix('admin')->group(function () {
        Route::get('/usuarios', [UsuarioController::class, 'index']);
        Route::post('/usuarios/{usuario}/aprovar', [UsuarioController::class, 'aprovar']);
        Route::post('/usuarios/{usuario}/bloquear', [UsuarioController::class, 'bloquear']);
    });
});
