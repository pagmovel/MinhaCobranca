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
        // Em produção o app fica atrás do proxy do EasyPanel (Traefik), que termina o HTTPS: sem isso o Laravel gera
        // links http:// para os assets do Vite e o navegador bloqueia como conteúdo misto.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // Token CSRF vencido (sessão expirou ou trocou): em vez da janela "419 Page Expired" do Inertia, volta para a
        // mesma página com o aviso; ela recarrega com o token novo e o segundo clique funciona (padrão da doc do Inertia v3).
        // Pedido JSON continua 419: enviarJson/buscarJson já mostram a mensagem certa. 303 (não o 302 padrão do back()):
        // a especificação Fetch só reescreve POST para GET em 301/302; em DELETE/PUT/PATCH o navegador repetiria o
        // mesmo método no destino (ex.: DELETE em `/produtos-servicos`, que só aceita GET/POST, dando 405). 303
        // reescreve para GET em qualquer método.
        $exceptions->respond(function (Response $resposta, Throwable $erro, Request $request) {
            if ($resposta->getStatusCode() === 419 && ! $request->expectsJson()) {
                return back(303)->with('erro', 'A página expirou. Tente de novo.');
            }

            return $resposta;
        });
    })->create();
