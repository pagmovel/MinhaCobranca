<?php

namespace App\Http\Middleware;

use App\Enums\StatusUsuario;
use App\Enums\TipoChavePix;
use App\Models\User;
use App\Support\ProgressoDeConfiguracao;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => fn () => $request->user()?->only('id', 'name', 'email', 'is_admin'),
            ],
            'pendentes' => fn () => $request->user()?->is_admin
                ? User::where('status', StatusUsuario::Pendente)->count()
                : 0,
            // Cadastro rápido de chave PIX em qualquer tela com forma de pagamento (painel, lista, formulários).
            'tiposChavePix' => fn () => $request->user() ? TipoChavePix::opcoes() : [],
            'flash' => [
                'sucesso' => fn () => $request->session()->get('sucesso'),
                'erro' => fn () => $request->session()->get('erro'),
            ],
            'progresso' => fn () => $request->user() ? ProgressoDeConfiguracao::de($request->user()) : null,
            'mostrarApresentacao' => function () use ($request) {
                $usuario = $request->user();
                if (! $usuario || $usuario->apresentacao_vista) {
                    return false;
                }

                return ! ProgressoDeConfiguracao::de($usuario)['liberado'];
            },
        ];
    }
}
