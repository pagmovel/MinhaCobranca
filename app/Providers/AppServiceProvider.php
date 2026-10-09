<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('admin', fn (User $user) => $user->is_admin);

        // IDs não numéricos viram 404 antes de chegar aos controllers (que recebem int).
        foreach (['cliente', 'contrato', 'cobranca', 'recibo', 'usuario', 'nota'] as $parametro) {
            Route::pattern($parametro, '[0-9]+');
        }

        // E-mail + IP: não pune quem divide a rede nem permite travar a conta de outro só pelo IP.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(
            Str::transliterate(Str::lower($request->string('email')).'|'.$request->ip())
        ));
    }
}
