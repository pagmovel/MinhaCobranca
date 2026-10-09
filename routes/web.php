<?php

use App\Http\Controllers\Auth\CadastroController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\PainelController;
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

    // Fases seguintes acrescentam rotas aqui, acima do grupo admin.
});
