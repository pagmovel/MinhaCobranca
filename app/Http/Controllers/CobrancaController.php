<?php

namespace App\Http\Controllers;

use App\Enums\FormaPagamento;
use App\Enums\StatusCobranca;
use App\Http\Requests\CobrancaRequest;
use App\Http\Requests\PagamentoRequest;
use App\Models\Auditoria;
use App\Models\Cobranca;
use App\Models\CobrancaItem;
use App\Rules\AnoValido;
use App\Services\EmissorDeRecibos;
use App\Services\GeradorDeCobrancas;
use App\Services\ListaDeCobrancas;
use App\Support\BlocoDePaginas;
use App\Support\Dinheiro;
use App\Support\ItensDeVenda;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CobrancaController extends Controller
{
    public function index(Request $request, GeradorDeCobrancas $gerador): Response
    {
        $usuario = $request->user();
        $hoje = CarbonImmutable::today();
        $gerador->gerarPara($usuario, $hoje);

        // A lista vem por ajax de /cobrancas/dados; a página só abre a tela com os filtros (o período é conferido lá).
        $filtros = $this->filtros($request, $hoje);

        return Inertia::render('Cobrancas/Index', [
            'filtros' => $this->filtrosParaTela($filtros),
            // Para o "Limpar filtros" aparecer só quando algum filtro difere do que a tela abre sem parâmetros.
            'padroes' => ['mes' => $hoje->format('Y-m'), 'ate' => $hoje->format('Y-m'), 'status' => '', 'cliente_id' => '', 'canceladas' => false],
            'chavesPix' => $usuario->opcoesDeChavePix(),
            'formasPagamento' => FormaPagamento::opcoes(),
            'clienteFiltro' => $filtros['cliente_id'] === null ? null : $usuario->clientes()->find($filtros['cliente_id'])?->paraBusca(),
            'modelosMensagem' => $usuario->opcoesDeModelosMensagem(),
            'prestadorNome' => $usuario->nome_fantasia ?: $usuario->name,
        ]);
    }

    /** Dados da lista (JSON, só leitura): um bloco de páginas e os totais do resultado inteiro. */
    public function dados(Request $request, ListaDeCobrancas $lista): JsonResponse
    {
        $hoje = CarbonImmutable::today();
        $filtros = $this->filtros($request, $hoje);

        // Mesmas mensagens dos relatórios; 422 em JSON, e a tela mostra embaixo do "Até".
        if ($filtros['mes']->gt($filtros['ate'])) {
            throw ValidationException::withMessages(['ate' => 'O fim do período deve ser igual ou depois do início.']);
        }
        if ($filtros['mes']->diffInMonths($filtros['ate']) >= 36) {
            throw ValidationException::withMessages(['ate' => 'Escolha um período de até 36 meses.']);
        }

        return response()->json($lista->montar($request->user(), $filtros, $hoje) + ['filtros' => $this->filtrosParaTela($filtros)]);
    }

    /**
     * Filtros da lista validados só no formato (o período é conferido no JSON).
     *
     * @return array{mes: CarbonImmutable, ate: CarbonImmutable, status: ?string, cliente_id: ?int, canceladas: bool, pagina: int, por_pagina: int}
     */
    private function filtros(Request $request, CarbonImmutable $hoje): array
    {
        $validados = $request->validate([
            'mes' => ['nullable', 'date_format:Y-m', new AnoValido],
            'ate' => ['nullable', 'date_format:Y-m', new AnoValido],
            'status' => ['nullable', Rule::in(['pendente', 'vence_hoje', 'vencida', 'a_vencer', 'paga', 'cancelada'])],
            'cliente_id' => ['nullable', 'integer'],
            'canceladas' => ['nullable', 'boolean'],
            ...BlocoDePaginas::regras(),
        ]);
        $mes = CarbonImmutable::createFromFormat('Y-m-d', ($validados['mes'] ?? $hoje->format('Y-m')).'-01')->startOfDay();

        return [
            'mes' => $mes,
            'ate' => isset($validados['ate']) ? CarbonImmutable::createFromFormat('Y-m-d', $validados['ate'].'-01')->startOfDay() : $mes,
            'status' => $validados['status'] ?? null,
            'cliente_id' => empty($validados['cliente_id']) ? null : (int) $validados['cliente_id'],
            'canceladas' => (bool) ($validados['canceladas'] ?? false),
            'pagina' => (int) ($validados['pagina'] ?? 1),
            'por_pagina' => (int) ($validados['por_pagina'] ?? 25),
        ];
    }

    /** @return array{mes: string, ate: string, status: string, cliente_id: int|string, canceladas: bool, pagina: int, por_pagina: int} */
    private function filtrosParaTela(array $filtros): array
    {
        return [
            'mes' => $filtros['mes']->format('Y-m'),
            'ate' => $filtros['ate']->format('Y-m'),
            'status' => $filtros['status'] ?? '',
            'cliente_id' => $filtros['cliente_id'] ?? '',
            'canceladas' => $filtros['canceladas'],
            'pagina' => $filtros['pagina'],
            'por_pagina' => $filtros['por_pagina'],
        ];
    }

    public function create(Request $request): Response
    {
        return $this->formulario($request, null);
    }

    public function edit(Request $request, int $cobranca): Response|RedirectResponse
    {
        $registro = $request->user()->cobrancas()->findOrFail($cobranca);

        if (! $registro->editavel()) {
            return redirect('/cobrancas')->with('erro', 'Cobrança já enviada não pode ser editada. Cancele e crie uma cobrança avulsa.');
        }

        return $this->formulario($request, $registro);
    }

    private function formulario(Request $request, ?Cobranca $cobranca): Response
    {
        return Inertia::render('Cobrancas/Form', [
            'cobranca' => $cobranca ? [
                'id' => $cobranca->id,
                'de_contrato' => $cobranca->contrato_id !== null,
                'cliente_id' => $cobranca->cliente_id,
                'descricao' => $cobranca->descricao,
                'itens' => $cobranca->itens->map(fn (CobrancaItem $item) => [
                    'produto_servico_id' => $item->produto_servico_id,
                    'nome' => $item->nome,
                    'unidade' => $item->unidade,
                    'quantidade' => ItensDeVenda::formatarQuantidade(ItensDeVenda::milesimos($item->quantidade)),
                    'preco' => Dinheiro::formatar($item->preco_unitario_centavos),
                    'principal' => $item->principal,
                ])->all(),
                'valor' => Dinheiro::formatar($cobranca->valor_centavos),
                'periodo_inicio' => $cobranca->periodo_inicio->toDateString(),
                'periodo_fim' => $cobranca->periodo_fim->toDateString(),
                'vencimento' => $cobranca->vencimento->toDateString(),
                'forma_pagamento' => $cobranca->forma_pagamento->value,
                'chave_pix_id' => $cobranca->chave_pix_id ?? '',
                'linha_digitavel' => $cobranca->linha_digitavel ?? '',
            ] : null,
            'clienteAtual' => $cobranca?->cliente?->paraBusca(),
            'chavesPix' => $request->user()->opcoesDeChavePix(),
            'formasPagamento' => FormaPagamento::opcoes(),
            'cadastroRapido' => $request->user()->opcoesDeCadastroRapido(),
        ]);
    }

    public function store(CobrancaRequest $request, EmissorDeRecibos $emissor): RedirectResponse
    {
        $usuario = $request->user();

        if (! $usuario->perfilCompleto()) {
            return back()->with('erro', 'Complete seu perfil para emitir recibos.');
        }

        DB::transaction(function () use ($usuario, $request, $emissor) {
            $cobranca = $usuario->cobrancas()->create($request->dadosCobranca());
            $cobranca->itens()->createMany($request->itensDaCobranca());
            $emissor->emitir($cobranca);
        });

        return redirect('/cobrancas')->with('sucesso', 'Cobrança criada com o recibo.');
    }

    public function update(CobrancaRequest $request, int $cobranca, EmissorDeRecibos $emissor): RedirectResponse
    {
        $registro = $request->user()->cobrancas()->with('recibo')->findOrFail($cobranca);

        if (! $registro->editavel()) {
            return back()->with('erro', 'Cobrança já enviada não pode ser editada. Cancele e crie uma cobrança avulsa.');
        }

        $dados = $request->dadosCobranca();
        if ($registro->contrato_id) {
            // Período e cliente de cobrança de contrato são definidos pelo contrato.
            $dados = Arr::except($dados, ['cliente_id', 'periodo_inicio', 'periodo_fim']);
        }

        $antes = ['valor_centavos' => $registro->valor_centavos, 'vencimento' => $registro->vencimento->toDateString()];
        $resumo = 'Cobrança editada (antes: R$ '.Dinheiro::formatar($registro->valor_centavos).', vence '.$registro->vencimento->format('d/m/Y').')';

        DB::transaction(function () use ($registro, $dados, $emissor, $request, $antes, $resumo) {
            $registro->update($dados);
            Auditoria::registrar($request, 'cobranca.editada', $registro, $resumo, $antes,
                ['valor_centavos' => $registro->valor_centavos, 'vencimento' => $registro->vencimento->toDateString()]);
            $registro->itens()->delete();
            $registro->itens()->createMany($request->itensDaCobranca());
            $emissor->refazer($registro->recibo, $registro->wasChanged('cliente_id'));
        });

        return redirect('/cobrancas')->with('sucesso', 'Cobrança atualizada. O recibo manteve o número.');
    }

    /** Forma de pagamento é dado vivo: muda até a baixa, mesmo com a cobrança enviada. */
    public function pagamento(PagamentoRequest $request, int $cobranca): RedirectResponse
    {
        $registro = $request->user()->cobrancas()->findOrFail($cobranca);

        if ($registro->status !== StatusCobranca::Pendente) {
            return back()->with('erro', 'Só cobranças pendentes mudam a forma de pagamento.');
        }

        $registro->update($request->dados());

        return back()->with('sucesso', 'Forma de pagamento atualizada.');
    }

    public function cancelar(Request $request, int $cobranca): RedirectResponse
    {
        $registro = $request->user()->cobrancas()->findOrFail($cobranca);

        if ($registro->status !== StatusCobranca::Pendente) {
            return back()->with('erro', 'Só cobranças pendentes podem ser canceladas.');
        }

        DB::transaction(function () use ($registro, $request) {
            $registro->status = StatusCobranca::Cancelada;
            $registro->save();
            Auditoria::registrar($request, 'cobranca.cancelada', $registro, 'Cobrança cancelada (R$ '
                .Dinheiro::formatar($registro->valor_centavos).', vence '.$registro->vencimento->format('d/m/Y').')');
        });

        return back()->with('sucesso', 'Cobrança cancelada. O recibo passa a sair como cancelado.');
    }

    public function prorrogar(Request $request, int $cobranca, EmissorDeRecibos $emissor): RedirectResponse
    {
        $registro = $request->user()->cobrancas()->with('recibo')->findOrFail($cobranca);

        if ($registro->status !== StatusCobranca::Pendente) {
            return back()->with('erro', 'Só cobranças pendentes podem ter o vencimento prorrogado.');
        }

        $dados = $request->validate([
            'vencimento' => ['required', 'date_format:Y-m-d', new AnoValido, 'after:'.$registro->vencimento->toDateString()],
        ], [
            'vencimento.after' => 'A nova data deve ser depois do vencimento atual ('.$registro->vencimento->format('d/m/Y').').',
        ]);

        $antes = $registro->vencimento;

        DB::transaction(function () use ($registro, $dados, $antes, $emissor, $request) {
            $registro->update(['vencimento' => $dados['vencimento']]);
            Auditoria::registrar($request, 'cobranca.vencimento_prorrogado', $registro,
                'Vencimento prorrogado de '.$antes->format('d/m/Y').' para '.$registro->vencimento->format('d/m/Y'),
                ['vencimento' => $antes->toDateString()], ['vencimento' => $registro->vencimento->toDateString()]);

            if ($registro->recibo !== null) {
                $emissor->refazer($registro->recibo);
            }
        });

        return back()->with('sucesso', 'Vencimento prorrogado.');
    }

    /** Baixa com a data errada: troca a data do pagamento (senha + motivo, registrado na auditoria). */
    public function corrigirPagamento(Request $request, int $cobranca): RedirectResponse
    {
        $registro = $request->user()->cobrancas()->findOrFail($cobranca);
        $dados = $request->validate(['pago_em' => ['required', 'date_format:Y-m-d', new AnoValido, 'before_or_equal:today']] + $this->confirmacao(), $this->mensagensDeConfirmacao());

        if ($registro->status !== StatusCobranca::Paga) {
            return back()->with('erro', 'Só cobranças pagas têm o pagamento corrigido.');
        }

        $antes = $registro->pago_em;

        DB::transaction(function () use ($registro, $request, $dados, $antes) {
            $registro->pago_em = $dados['pago_em'];
            $registro->save();
            Auditoria::registrar($request, 'pagamento.corrigido', $registro, 'Pagamento corrigido (antes: '.$antes->format('d/m/Y').')',
                ['pago_em' => $antes->toDateString()], ['pago_em' => $registro->pago_em->toDateString()], trim($dados['motivo']));
        });

        return back()->with('sucesso', 'Data do pagamento corrigida. Se o recibo pago já foi enviado, envie de novo: ele sai com a data nova.');
    }

    /** Baixa na cobrança errada: volta a pendente (senha + motivo, registrado na auditoria). */
    public function desfazerBaixa(Request $request, int $cobranca): RedirectResponse
    {
        $registro = $request->user()->cobrancas()->findOrFail($cobranca);
        $dados = $request->validate($this->confirmacao(), $this->mensagensDeConfirmacao());

        if ($registro->status !== StatusCobranca::Paga) {
            return back()->with('erro', 'Só cobranças pagas têm a baixa desfeita.');
        }

        $antes = $registro->pago_em;

        DB::transaction(function () use ($registro, $request, $dados, $antes) {
            $registro->status = StatusCobranca::Pendente;
            $registro->pago_em = null;
            $registro->save();
            Auditoria::registrar($request, 'baixa.desfeita', $registro, 'Baixa desfeita (estava paga em '.$antes->format('d/m/Y').')',
                ['status' => 'paga', 'pago_em' => $antes->toDateString()], ['status' => 'pendente', 'pago_em' => null], trim($dados['motivo']));
        });

        return back()->with('sucesso', 'Baixa desfeita: a cobrança voltou a ficar em aberto.');
    }

    /** Senha de quem está logado + motivo: a mudança fica registrada em nome da pessoa e explicada. */
    private function confirmacao(): array
    {
        return [
            'senha' => ['required', 'string', 'current_password'],
            'motivo' => ['required', 'string', 'max:500'],
        ];
    }

    private function mensagensDeConfirmacao(): array
    {
        return [
            'senha.required' => 'Digite sua senha para confirmar.',
            'senha.current_password' => 'Senha incorreta.',
            'motivo.required' => 'Escreva o motivo da correção.',
        ];
    }

    public function pagar(Request $request, int $cobranca): RedirectResponse
    {
        $registro = $request->user()->cobrancas()->findOrFail($cobranca);
        $dados = $request->validate(['pago_em' => ['required', 'date_format:Y-m-d', new AnoValido, 'before_or_equal:today']]);

        if ($registro->status !== StatusCobranca::Pendente) {
            return back()->with('erro', 'Só cobranças pendentes recebem baixa.');
        }

        $registro->status = StatusCobranca::Paga;
        $registro->pago_em = $dados['pago_em'];
        $registro->save();

        return back()->with('sucesso', 'Baixa registrada.');
    }

    /** Chamado pelo botão Enviar depois que a folha de compartilhamento (ou o download) concluiu. */
    public function marcarEnviada(Request $request, int $cobranca): RedirectResponse
    {
        $registro = $request->user()->cobrancas()->findOrFail($cobranca);
        $registro->enviado_em = now();
        $registro->save();

        return back();
    }
}
