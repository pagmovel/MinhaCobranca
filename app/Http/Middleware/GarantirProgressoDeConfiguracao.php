<?php

namespace App\Http\Middleware;

use App\Support\ProgressoDeConfiguracao;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Trava só a criação de registro novo (produto, cliente, contrato, cobrança) até o bloco pedido estar completo. */
class GarantirProgressoDeConfiguracao
{
    private const MENSAGENS = [
        'perfil' => 'Complete seu perfil (dados do prestador, uma chave PIX e um modelo de mensagem) antes de cadastrar produtos, serviços ou clientes.',
        'liberado' => 'Cadastre pelo menos um produto ou serviço e um cliente antes de criar contratos ou cobranças.',
    ];

    public function handle(Request $request, Closure $next, string $bloco): Response
    {
        $progresso = ProgressoDeConfiguracao::de($request->user());

        if ($progresso[$bloco]) {
            return $next($request);
        }

        // O bloco "liberado" cobre perfil + catálogo: quando o perfil é a causa real, a mensagem do perfil
        // orienta melhor do que a de catálogo (decisão do plano — a sequência pede perfil primeiro).
        $mensagem = ! $progresso['perfil'] ? self::MENSAGENS['perfil'] : self::MENSAGENS[$bloco];

        return redirect('/painel')->with('erro', $mensagem);
    }
}
