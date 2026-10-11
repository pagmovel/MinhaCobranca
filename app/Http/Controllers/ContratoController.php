<?php

namespace App\Http\Controllers;

use App\Enums\FormaPagamento;
use App\Enums\Modalidade;
use App\Enums\Periodicidade;
use App\Http\Requests\ContratoRequest;
use App\Models\Contrato;
use App\Models\ContratoItem;
use App\Services\EncerradorDeContratos;
use App\Support\Dinheiro;
use App\Support\ItensDeVenda;
use App\Support\Paginacao;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ContratoController extends Controller
{
    public function index(Request $request): Response
    {
        $usuario = $request->user();
        $filtros = $request->validate([
            'cliente_id' => ['nullable', 'integer'],
            'situacao' => ['nullable', Rule::in(['ativos', 'encerrados'])],
            'modalidade' => ['nullable', Rule::enum(Modalidade::class)],
            'forma_pagamento' => ['nullable', Rule::enum(FormaPagamento::class)],
            'produto_servico_id' => ['nullable', 'integer'],
        ]);

        $consulta = $usuario->contratos()->with('cliente:id,nome')
            ->orderByDesc('ativo')->orderBy('data_inicio')->orderBy('id');

        foreach (['cliente_id', 'modalidade', 'forma_pagamento'] as $campo) {
            if (filled($filtros[$campo] ?? null)) {
                $consulta->where($campo, $filtros[$campo]);
            }
        }
        if (filled($filtros['situacao'] ?? null)) {
            $consulta->where('ativo', $filtros['situacao'] === 'ativos');
        }
        if (filled($filtros['produto_servico_id'] ?? null)) {
            $consulta->whereHas('itens', fn ($itens) => $itens->where('produto_servico_id', $filtros['produto_servico_id']));
        }

        $pagina = $consulta->paginate(25)->withQueryString();

        return Inertia::render('Contratos/Index', [
            'contratos' => collect($pagina->items())->map(fn (Contrato $contrato) => [
                'id' => $contrato->id,
                'cliente' => $contrato->cliente->nome,
                'resumo' => strtok($contrato->descricao, "\n"),
                'valor' => Dinheiro::formatar($contrato->valor_centavos),
                'periodicidade' => $contrato->periodicidade->rotulo(),
                'modalidade' => $contrato->modalidade->rotulo(),
                'dia_vencimento' => $contrato->dia_vencimento,
                'ativo' => $contrato->ativo,
            ]),
            'paginacao' => Paginacao::dados($pagina),
            'filtros' => [
                'cliente_id' => isset($filtros['cliente_id']) ? (int) $filtros['cliente_id'] : '',
                'situacao' => $filtros['situacao'] ?? '',
                'modalidade' => $filtros['modalidade'] ?? '',
                'forma_pagamento' => $filtros['forma_pagamento'] ?? '',
                'produto_servico_id' => isset($filtros['produto_servico_id']) ? (int) $filtros['produto_servico_id'] : '',
            ],
            'clienteFiltro' => empty($filtros['cliente_id']) ? null : $usuario->clientes()->find($filtros['cliente_id'], ['id', 'nome']),
            'modalidades' => Modalidade::opcoes(),
            'formasPagamento' => FormaPagamento::opcoes(),
            'produtos' => $usuario->produtosServicos()->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->formulario($request, null);
    }

    public function store(ContratoRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $contrato = $request->user()->contratos()->create($request->dadosContrato());
            $contrato->itens()->createMany($request->itensDoContrato());
        });

        return redirect('/contratos')->with('sucesso', 'Contrato salvo. As cobranças aparecem no painel quando chegar a hora.');
    }

    public function edit(Request $request, int $contrato): Response
    {
        return $this->formulario($request, $request->user()->contratos()->findOrFail($contrato));
    }

    public function update(ContratoRequest $request, int $contrato): RedirectResponse
    {
        $registro = $request->user()->contratos()->findOrFail($contrato);
        $dados = $request->dadosContrato();

        $mudaPeriodos = $registro->periodicidade->value !== $dados['periodicidade']
            || $registro->data_inicio->toDateString() !== CarbonImmutable::parse($dados['data_inicio'])->toDateString();

        // Mudar a âncora dos períodos geraria períodos novos sobrepostos aos já cobrados.
        if ($mudaPeriodos && $registro->cobrancas()->exists()) {
            return back()->withErrors([
                'data_inicio' => 'Este contrato já tem cobranças. Para mudar o início ou a periodicidade, encerre-o e crie outro.',
            ]);
        }

        if ($registro->modalidade->value !== $dados['modalidade'] && $registro->cobrancas()->exists()) {
            return back()->withErrors([
                'modalidade' => 'Este contrato já tem cobranças. Para mudar a modalidade, encerre-o e crie outro.',
            ]);
        }

        // Encerrado só volta a valer com "Reativar" marcado: um formulário aberto antes do encerramento (outra aba)
        // manda ativo e fim vazio, e não pode desfazer o encerramento sem ninguém ver.
        if (! $registro->ativo && ! $request->boolean('reativar')) {
            $dados['ativo'] = false;
            $dados['data_fim'] = $registro->data_fim;
        } elseif (! $registro->ativo) {
            $dados['ativo'] = true;
        }

        DB::transaction(function () use ($registro, $dados, $request) {
            $registro->update($dados);
            $registro->itens()->delete();
            $registro->itens()->createMany($request->itensDoContrato());
        });

        return redirect('/contratos')->with('sucesso', 'Contrato salvo. As mudanças valem para as próximas cobranças.');
    }

    /** Prévia do encerramento (JSON): o que acontece com cada cobrança, sem gravar nada. */
    public function encerramento(Request $request, int $contrato, EncerradorDeContratos $encerrador): JsonResponse
    {
        $registro = $request->user()->contratos()->findOrFail($contrato);
        [$fim, $proporcional] = $this->dadosDoEncerramento($request, $registro);
        $acoes = $encerrador->plano($registro, $fim, $proporcional, CarbonImmutable::today());

        return response()->json([
            'tem_em_curso' => collect($acoes)->contains('em_curso', true),
            'acoes' => array_map(fn (array $acao) => [
                'acao' => $acao['acao'],
                'periodo' => $acao['periodo']->inicio->format('d/m/Y').' a '.$acao['periodo']->fim->format('d/m/Y'),
                'vencimento' => $acao['periodo']->vencimento->format('d/m/Y'),
                'valor' => Dinheiro::formatar($acao['valor_centavos']),
                'dias' => $acao['usados'] === null ? null : "{$acao['usados']} de {$acao['dias']} dias",
                'nova' => $acao['cobranca'] === null,
                'agendada' => $acao['agendada'],
                'boleto_novo' => $acao['acao'] === 'proporcional' && filled($acao['cobranca']?->linha_digitavel),
                'enviada' => $acao['cobranca']?->enviado_em !== null,
            ], $acoes),
        ]);
    }

    public function encerrar(Request $request, int $contrato, EncerradorDeContratos $encerrador): RedirectResponse
    {
        $registro = $request->user()->contratos()->findOrFail($contrato);

        if (! $registro->ativo) {
            return back()->with('erro', 'Este contrato já está encerrado.');
        }

        // ponytail: reagendar exigiria guardar as quantidades originais da cobrança já proporcional; mudar a data
        // entra na prorrogação (T17.11).
        if ($registro->ultimo_periodo !== 'inteiro') {
            return back()->with('erro', 'O encerramento deste contrato já está agendado para '.$registro->data_fim?->format('d/m/Y').'.');
        }

        [$fim, $proporcional] = $this->dadosDoEncerramento($request, $registro);
        $encerrador->encerrar($registro, $fim, $proporcional, CarbonImmutable::today());

        return redirect("/contratos/{$registro->id}/edit")
            ->with('sucesso', $fim->gt(CarbonImmutable::today())
                ? 'Encerramento agendado para '.$fim->format('d/m/Y').'. As cobranças já emitidas foram acertadas; as próximas saem no ritmo de sempre até lá.'
                : 'Contrato encerrado em '.$fim->format('d/m/Y').'. As cobranças foram acertadas.');
    }

    /** Muda início/periodicidade/modalidade de contrato com cobranças: encerra na véspera e cria outro com o formulário. */
    public function recomecar(ContratoRequest $request, int $contrato, EncerradorDeContratos $encerrador): RedirectResponse
    {
        $registro = $request->user()->contratos()->findOrFail($contrato);
        $dados = $request->dadosContrato();
        $inicio = CarbonImmutable::parse($dados['data_inicio'])->startOfDay();

        if ($inicio->lte($registro->data_inicio)) {
            return back()->withErrors([
                'data_inicio' => 'O contrato novo precisa começar depois de '.$registro->data_inicio->format('d/m/Y')
                    .' (antes disso, os períodos se sobreporiam aos já cobrados).',
            ]);
        }

        if (! $registro->ativo) {
            return back()->with('erro', 'Este contrato já está encerrado.');
        }

        if ($registro->ultimo_periodo !== 'inteiro') {
            return back()->with('erro', 'O encerramento deste contrato já está agendado para '.$registro->data_fim?->format('d/m/Y').'.');
        }

        $novo = DB::transaction(function () use ($request, $registro, $dados, $inicio, $encerrador) {
            $encerrador->encerrar($registro, $inicio->subDay(), $request->input('em_curso') !== 'cancelar', CarbonImmutable::today());
            $novo = $request->user()->contratos()->create(array_merge($dados, ['ativo' => true]));
            $novo->itens()->createMany($request->itensDoContrato());

            return $novo;
        });

        return redirect("/contratos/{$novo->id}/edit")->with('sucesso', 'Contrato anterior termina em '
            .$inicio->subDay()->format('d/m/Y').'. Este é o contrato novo, a partir de '.$inicio->format('d/m/Y').'.');
    }

    /** @return array{0: CarbonImmutable, 1: bool} data de fim e se o período em curso fica proporcional */
    private function dadosDoEncerramento(Request $request, Contrato $contrato): array
    {
        $dados = $request->validate([
            'fim' => ['required', 'date', 'after_or_equal:'.$contrato->data_inicio->toDateString()],
            'em_curso' => ['nullable', Rule::in(['proporcional', 'cancelar'])],
        ], [
            'fim.after_or_equal' => 'O fim não pode ser antes do início do contrato ('.$contrato->data_inicio->format('d/m/Y').').',
        ]);

        return [CarbonImmutable::parse($dados['fim'])->startOfDay(), ($dados['em_curso'] ?? 'proporcional') === 'proporcional'];
    }

    private function formulario(Request $request, ?Contrato $contrato): Response
    {
        return Inertia::render('Contratos/Form', [
            'contrato' => $contrato ? [
                'id' => $contrato->id,
                'cliente_id' => $contrato->cliente_id,
                'descricao' => $contrato->descricao,
                'valor' => Dinheiro::formatar($contrato->valor_centavos),
                'itens' => $contrato->itens()->with('produtoServico:id,nome,unidade')->get()
                    ->map(fn (ContratoItem $item) => [
                        'produto_servico_id' => $item->produto_servico_id,
                        'nome' => $item->produtoServico->nome,
                        'unidade' => $item->produtoServico->unidade,
                        'quantidade' => ItensDeVenda::formatarQuantidade(ItensDeVenda::milesimos($item->quantidade)),
                        'preco' => Dinheiro::formatar($item->preco_unitario_centavos),
                        'principal' => $item->principal,
                    ])->all(),
                'periodicidade' => $contrato->periodicidade->value,
                'modalidade' => $contrato->modalidade->value,
                'dia_vencimento' => $contrato->dia_vencimento,
                'data_inicio' => $contrato->data_inicio->toDateString(),
                'data_fim' => $contrato->data_fim?->toDateString() ?? '',
                'ativo' => $contrato->ativo,
                'tem_cobrancas' => $contrato->cobrancas()->exists(),
                'encerramento_agendado' => $contrato->ativo && $contrato->ultimo_periodo !== 'inteiro',
                'forma_pagamento' => $contrato->forma_pagamento->value,
                'chave_pix_id' => $contrato->chave_pix_id ?? '',
            ] : null,
            'clienteAtual' => $contrato?->cliente()->first(['id', 'nome']),
            'periodicidades' => Periodicidade::opcoes(),
            'modalidades' => Modalidade::opcoes(),
            'chavesPix' => $request->user()->opcoesDeChavePix(),
            'formasPagamento' => FormaPagamento::opcoes(),
            'cadastroRapido' => $request->user()->opcoesDeCadastroRapido(),
        ]);
    }
}
