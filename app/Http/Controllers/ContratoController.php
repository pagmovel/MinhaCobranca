<?php

namespace App\Http\Controllers;

use App\Enums\FormaPagamento;
use App\Enums\Modalidade;
use App\Enums\Periodicidade;
use App\Enums\StatusCobranca;
use App\Http\Requests\ContratoRequest;
use App\Models\Auditoria;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\ContratoItem;
use App\Models\User;
use App\Services\CalculadoraDePeriodos;
use App\Services\EncerradorDeContratos;
use App\Support\BlocoDePaginas;
use App\Support\Dinheiro;
use App\Support\ItensDeVenda;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ContratoController extends Controller
{
    public function index(Request $request): Response
    {
        $usuario = $request->user();
        $filtros = $this->filtros($request);

        // A lista vem por ajax de /contratos/dados; a página só abre a tela com os filtros.
        return Inertia::render('Contratos/Index', [
            'filtros' => $filtros,
            'clienteFiltro' => $filtros['cliente_id'] === '' ? null : $usuario->clientes()->find($filtros['cliente_id'], ['id', 'nome']),
            // Em qualquer situação: o "Ver contratos" de um item inativo também mostra o nome no filtro.
            'produtoFiltro' => $filtros['produto_servico_id'] === '' ? null : $usuario->produtosServicos()->find($filtros['produto_servico_id'], ['id', 'nome']),
            'modalidades' => Modalidade::opcoes(),
            'formasPagamento' => FormaPagamento::opcoes(),
        ]);
    }

    /** Dados da lista (JSON, só leitura): um bloco de páginas e os totais dos ativos por periodicidade. */
    public function dados(Request $request): JsonResponse
    {
        $filtros = $this->filtros($request);
        $filtrada = fn () => $this->filtrada($request->user(), $filtros);

        $bloco = BlocoDePaginas::de(
            $filtrada()->with('cliente:id,nome')->orderByDesc('ativo')->orderBy('data_inicio')->orderBy('id'),
            $filtros['pagina'],
            $filtros['por_pagina'],
        );
        // Sem ORDER BY (o PostgreSQL recusaria) e sem somar periodicidades diferentes (o número não faria sentido).
        $porPeriodicidade = $filtrada()->where('ativo', true)->toBase()
            ->selectRaw('periodicidade, count(*) as quantidade, sum(valor_centavos) as soma')
            ->groupBy('periodicidade')->get()->keyBy('periodicidade');

        return response()->json([
            'linhas' => $bloco['itens']->map(fn (Contrato $contrato) => [
                'id' => $contrato->id,
                'cliente_id' => $contrato->cliente_id,
                'cliente' => $contrato->cliente->nome,
                'resumo' => (string) strtok($contrato->descricao, "\n"),
                'periodicidade' => $contrato->periodicidade->rotulo(),
                'modalidade' => $contrato->modalidade->rotulo(),
                'dia_vencimento' => $contrato->dia_vencimento,
                'inicio' => $contrato->data_inicio->format('d/m/Y'),
                'fim' => $contrato->data_fim?->format('d/m/Y'),
                'valor' => Dinheiro::formatar($contrato->valor_centavos),
                // Ativo com fim marcado = encerramento agendado (T17.9).
                'situacao' => match (true) {
                    ! $contrato->ativo => 'encerrado',
                    $contrato->data_fim !== null => 'encerra',
                    default => 'ativo',
                },
            ])->all(),
            'totais' => [
                'ativos' => collect(Periodicidade::cases())
                    ->filter(fn (Periodicidade $periodicidade) => $porPeriodicidade->has($periodicidade->value))
                    ->map(fn (Periodicidade $periodicidade) => [
                        'periodicidade' => $periodicidade->value,
                        'quantidade' => (int) $porPeriodicidade[$periodicidade->value]->quantidade,
                        'soma_centavos' => (int) $porPeriodicidade[$periodicidade->value]->soma,
                    ])
                    ->values()
                    ->all(),
                'encerrados' => $filtrada()->where('ativo', false)->count(),
            ],
            'pagina' => $bloco['pagina'],
            'filtros' => $filtros,
        ]);
    }

    /** Contratos do usuário com os filtros da lista, sem ordem (a lista ordena; as somas não podem ter ORDER BY). */
    private function filtrada(User $usuario, array $filtros): HasMany
    {
        $consulta = $usuario->contratos();

        foreach (['cliente_id', 'modalidade', 'forma_pagamento'] as $campo) {
            if ($filtros[$campo] !== '') {
                $consulta->where($campo, $filtros[$campo]);
            }
        }
        if ($filtros['situacao'] !== '') {
            $consulta->where('ativo', $filtros['situacao'] === 'ativos');
        }
        if ($filtros['produto_servico_id'] !== '') {
            $consulta->whereHas('itens', fn ($itens) => $itens->where('produto_servico_id', $filtros['produto_servico_id']));
        }

        return $consulta;
    }

    /**
     * Filtros da lista, já no formato da tela ('' = sem filtro).
     *
     * @return array{cliente_id: int|string, situacao: string, modalidade: string, forma_pagamento: string, produto_servico_id: int|string, pagina: int, por_pagina: int}
     */
    private function filtros(Request $request): array
    {
        $validados = $request->validate([
            'cliente_id' => ['nullable', 'integer'],
            'situacao' => ['nullable', Rule::in(['ativos', 'encerrados'])],
            'modalidade' => ['nullable', Rule::enum(Modalidade::class)],
            'forma_pagamento' => ['nullable', Rule::enum(FormaPagamento::class)],
            'produto_servico_id' => ['nullable', 'integer'],
            ...BlocoDePaginas::regras(),
        ]);

        return [
            'cliente_id' => empty($validados['cliente_id']) ? '' : (int) $validados['cliente_id'],
            'situacao' => $validados['situacao'] ?? '',
            'modalidade' => $validados['modalidade'] ?? '',
            'forma_pagamento' => $validados['forma_pagamento'] ?? '',
            'produto_servico_id' => empty($validados['produto_servico_id']) ? '' : (int) $validados['produto_servico_id'],
            'pagina' => (int) ($validados['pagina'] ?? 1),
            'por_pagina' => (int) ($validados['por_pagina'] ?? 25),
        ];
    }

    public function create(Request $request): Response
    {
        return $this->formulario($request, null);
    }

    public function store(ContratoRequest $request): RedirectResponse
    {
        $dados = $request->dadosContrato() + ['cobrar_a_partir_de' => $this->cobrarAPartirDe($request)];

        DB::transaction(function () use ($request, $dados) {
            $contrato = $request->user()->contratos()->create($dados);
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
            || $registro->data_inicio->toDateString() !== CarbonImmutable::parse($dados['data_inicio'])->toDateString()
            || $registro->modalidade->value !== $dados['modalidade'];
        $corrigir = false;

        // Mudar a âncora dos períodos geraria períodos novos sobrepostos aos já cobrados. Sem cobrança paga, a
        // pessoa pode corrigir (data errada): as pendentes são canceladas depois da prévia. Com paga, só recomeçar.
        if ($mudaPeriodos && $registro->cobrancas()->exists()) {
            if ($registro->cobrancas()->where('status', StatusCobranca::Paga)->exists()) {
                return back()->withErrors([
                    'data_inicio' => 'Este contrato tem cobrança paga: para mudar o início, a periodicidade ou a modalidade, '
                        .'recomece o contrato a partir de uma data posterior.',
                ]);
            }

            if (! $request->boolean('corrigir')) {
                return back()->withErrors([
                    'data_inicio' => 'Confirme a correção: as cobranças pendentes deste contrato serão canceladas.',
                ]);
            }

            $corrigir = true;
        }

        if ($mudaPeriodos || $request->has('gerar_retroativas')) {
            $dados['cobrar_a_partir_de'] = $this->cobrarAPartirDe($request);
        }

        // Encerrado só volta a valer com "Reativar" marcado: um formulário aberto antes do encerramento (outra aba)
        // manda ativo e fim vazio, e não pode desfazer o encerramento sem ninguém ver.
        if (! $registro->ativo && ! $request->boolean('reativar')) {
            $dados['ativo'] = false;
            $dados['data_fim'] = $registro->data_fim;
        } elseif (! $registro->ativo) {
            $dados['ativo'] = true;
            $dados['ultimo_periodo'] = 'inteiro';
        }

        DB::transaction(function () use ($registro, $dados, $request, $corrigir) {
            if ($corrigir) {
                Auditoria::registrar($request, 'contrato.corrigido', $registro, 'Contrato corrigido (antes: início '
                    .$registro->data_inicio->format('d/m/Y').', '.mb_strtolower($registro->periodicidade->rotulo())
                    .', '.mb_strtolower($registro->modalidade->rotulo()).')',
                    $registro->only(['data_inicio', 'periodicidade', 'modalidade']),
                    Arr::only($dados, ['data_inicio', 'periodicidade', 'modalidade']));
                $registro->cobrancas()->where('status', StatusCobranca::Pendente)->update(['status' => StatusCobranca::Cancelada]);
                // Canceladas saem do contrato: o índice único (contrato, início do período) bloquearia um período novo
                // que comece no mesmo dia, e o gerador o pularia achando que já existe.
                $registro->cobrancas()->update(['contrato_id' => null]);
            }

            $registro->update($dados);
            $registro->itens()->delete();
            $registro->itens()->createMany($request->itensDoContrato());
        });

        return redirect('/contratos')->with('sucesso', 'Contrato salvo. As mudanças valem para as próximas cobranças.');
    }

    /** Quantos períodos terminaram antes de hoje para um início no passado (a tela pergunta se gera esses). */
    public function retroativos(Request $request, CalculadoraDePeriodos $calculadora): JsonResponse
    {
        $dados = $request->validate([
            'data_inicio' => ['required', 'date_format:Y-m-d'],
            'periodicidade' => ['required', Rule::enum(Periodicidade::class)],
        ]);
        $anteriores = $calculadora->anteriores(
            CarbonImmutable::parse($dados['data_inicio']), Periodicidade::from($dados['periodicidade']), CarbonImmutable::today(),
        );

        return response()->json([
            'quantidade' => $anteriores['quantidade'],
            'de' => $anteriores['de']?->format('d/m/Y'),
            'ate' => $anteriores['ate']?->format('d/m/Y'),
            'a_partir_de' => $anteriores['a_partir_de']->format('d/m/Y'),
        ]);
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
        DB::transaction(function () use ($encerrador, $registro, $fim, $proporcional, $request) {
            $encerrador->encerrar($registro, $fim, $proporcional, CarbonImmutable::today());
            $this->registrarEncerramento($request, $registro, $fim);
        });

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
            $this->registrarEncerramento($request, $registro, $inicio->subDay());
            $novo = $request->user()->contratos()->create(array_merge($dados, ['ativo' => true]));
            $novo->itens()->createMany($request->itensDoContrato());
            Auditoria::registrar($request, 'contrato.recomecado', $registro,
                "Contrato recomeçado: novo contrato nº {$novo->id} a partir de ".$inicio->format('d/m/Y'));

            return $novo;
        });

        return redirect("/contratos/{$novo->id}/edit")->with('sucesso', 'Contrato anterior termina em '
            .$inicio->subDay()->format('d/m/Y').'. Este é o contrato novo, a partir de '.$inicio->format('d/m/Y').'.');
    }

    private function registrarEncerramento(Request $request, Contrato $contrato, CarbonImmutable $fim): void
    {
        Auditoria::registrar($request, 'contrato.encerrado', $contrato, $fim->gt(CarbonImmutable::today())
            ? 'Encerramento agendado para '.$fim->format('d/m/Y')
            : 'Contrato encerrado em '.$fim->format('d/m/Y'), null, ['data_fim' => $fim->toDateString(), 'ultimo_periodo' => $contrato->ultimo_periodo]);
    }

    /** @return array{0: CarbonImmutable, 1: bool} data de fim e se o período em curso fica proporcional */
    private function dadosDoEncerramento(Request $request, Contrato $contrato): array
    {
        $dados = $request->validate([
            'fim' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$contrato->data_inicio->toDateString()],
            'em_curso' => ['nullable', Rule::in(['proporcional', 'cancelar'])],
        ], [
            'fim.after_or_equal' => 'O fim não pode ser antes do início do contrato ('.$contrato->data_inicio->format('d/m/Y').').',
        ]);

        return [CarbonImmutable::parse($dados['fim'])->startOfDay(), ($dados['em_curso'] ?? 'proporcional') === 'proporcional'];
    }

    /** "Cobrar a partir de": nulo gera desde o início (padrão de sempre); sem os retroativos, o período atual. */
    private function cobrarAPartirDe(ContratoRequest $request): ?CarbonImmutable
    {
        if ($request->boolean('gerar_retroativas', true)) {
            return null;
        }

        $anteriores = app(CalculadoraDePeriodos::class)->anteriores(
            CarbonImmutable::parse($request->validated('data_inicio')),
            Periodicidade::from($request->validated('periodicidade')),
            CarbonImmutable::today(),
        );

        return $anteriores['quantidade'] > 0 ? $anteriores['a_partir_de'] : null;
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
                'tem_pagas' => $contrato->cobrancas()->where('status', StatusCobranca::Paga)->exists(),
                // O que uma correção de início cancelaria (prévia na tela).
                'cobrancas_pendentes' => $contrato->cobrancas()->where('status', StatusCobranca::Pendente)->orderBy('periodo_inicio')->get()
                    ->map(fn (Cobranca $cobranca) => [
                        'periodo' => $cobranca->periodo_inicio->format('d/m/Y').' a '.$cobranca->periodo_fim->format('d/m/Y'),
                        'vencimento' => $cobranca->vencimento->format('d/m/Y'),
                        'valor' => Dinheiro::formatar($cobranca->valor_centavos),
                        'enviada' => $cobranca->enviado_em !== null,
                    ])->all(),
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
