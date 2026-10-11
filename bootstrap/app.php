<?php

use App\Http\Middleware\GarantirContaAprovada;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
        $middleware->alias([
            'conta.aprovada' => GarantirContaAprovada::class,
        ]);
        $middleware->redirectUsersTo('/painel');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // Token CSRF vencido (sessão expirou ou trocou): em vez da janela "419 Page Expired" do Inertia, volta para a
        // mesma página com o aviso; ela recarrega com o token novo e o segundo clique funciona (padrão da doc do Inertia v3).
        // Pedido JSON continua 419: enviarJson/buscarJson já mostram a mensagem certa.
        $exceptions->respond(function (Response $resposta, Throwable $erro, Request $request) {
            if ($resposta->getStatusCode() === 419 && ! $request->expectsJson()) {
                return back()->with('erro', 'A página expirou. Tente de novo.');
            }

            return $resposta;
        });
    })->create();
