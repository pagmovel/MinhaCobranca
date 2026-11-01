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
use App\Services\ValoresDaBaixa;
use App\Support\BlocoDePaginas;
use App\Support\Desconto;
use App\Support\Dinheiro;
use App\Support\ItensDeVenda;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
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

        $contrato = $filtros['contrato_id'] === null ? null : $usuario->contratos()->find($filtros['contrato_id']);

        return Inertia::render('Cobrancas/Index', [
            'filtros' => $this->filtrosParaTela($filtros),
            // Para o "Limpar filtros" aparecer só quando algum filtro difere do que a tela abre sem parâmetros.
            'padroes' => ['mes' => $hoje->format('Y-m'), 'ate' => $hoje->format('Y-m'), 'status' => '', 'cliente_id' => '', 'contrato_id' => '', 'canceladas' => false],
            'chavesPix' => $usuario->opcoesDeChavePix(),
            'formasPagamento' => FormaPagamento::opcoes(),
            'clienteFiltro' => $filtros['cliente_id'] === null ? null : $usuario->clientes()->find($filtros['cliente_id'])?->paraBusca(),
            'contratoFiltro' => $contrato === null ? null : [
                'id' => $contrato->id,
                'valor' => Dinheiro::formatar($contrato->valor_centavos),
                'data_inicio' => $contrato->data_inicio->format('d/m/Y'),
            ],
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
     * @return array{mes: CarbonImmutable, ate: CarbonImmutable, status: ?string, cliente_id: ?int, contrato_id: ?int, canceladas: bool, pagina: int, por_pagina: int}
     */
    private function filtros(Request $request, CarbonImmutable $hoje): array
    {
        $validados = $request->validate([
            'mes' => ['nullable', 'date_format:Y-m', new AnoValido],
            'ate' => ['nullable', 'date_format:Y-m', new AnoValido],
            'status' => ['nullable', Rule::in(['pendente', 'vence_hoje', 'vencida', 'a_vencer', 'paga', 'cancelada'])],
            'cliente_id' => ['nullable', 'integer'],
            'contrato_id' => ['nullable', 'integer'],
            'canceladas' => ['nullable', 'boolean'],
            ...BlocoDePaginas::regras(),
        ]);
        $mes = CarbonImmutable::createFromFormat('Y-m-d', ($validados['mes'] ?? $hoje->format('Y-m')).'-01')->startOfDay();

        return [
            'mes' => $mes,
            'ate' => isset($validados['ate']) ? CarbonImmutable::createFromFormat('Y-m-d', $validados['ate'].'-01')->startOfDay() : $mes,
            'status' => $validados['status'] ?? null,
            'cliente_id' => empty($validados['cliente_id']) ? null : (int) $validados['cliente_id'],
            'contrato_id' => empty($validados['contrato_id']) ? null : (int) $validados['contrato_id'],
            'canceladas' => (bool) ($validados['canceladas'] ?? false),
            'pagina' => (int) ($validados['pagina'] ?? 1),
            'por_pagina' => (int) ($validados['por_pagina'] ?? 25),
        ];
    }

    /** @return array{mes: string, ate: string, status: string, cliente_id: int|string, contrato_id: int|string, canceladas: bool, pagina: int, por_pagina: int} */
    private function filtrosParaTela(array $filtros): array
    {
        return [
            'mes' => $filtros['mes']->format('Y-m'),
            'ate' => $filtros['ate']->format('Y-m'),
            'status' => $filtros['status'] ?? '',
            'cliente_id' => $filtros['cliente_id'] ?? '',
            'contrato_id' => $filtros['contrato_id'] ?? '',
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

    /** T17.19: baixa de várias cobranças de uma vez (prévia fica na tela, aqui só executa). */
    public function pagarEmLote(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'ids' => ['required', 'array', 'max:200'],
            'ids.*' => ['integer'],
            'modo' => ['required', Rule::in(['vencimento', 'hoje'])],
            ...ValoresDaBaixa::regrasDoDesconto(),
        ], ValoresDaBaixa::MENSAGENS);

        $hoje = CarbonImmutable::today();
        $tipo = $dados['desconto_tipo'] ?? Desconto::PERCENTUAL;
        $entrada = $dados['desconto'] ?? null;
        $pedidas = count($dados['ids']);
        $cobrancas = $request->user()->cobrancas()->whereIn('id', $dados['ids'])->where('status', StatusCobranca::Pendente)->get();

        // Antes de gravar qualquer uma: lote recusado não baixa nada.
        $this->conferirDescontoDoLote($cobrancas, $tipo, $entrada);

        DB::transaction(function () use ($cobrancas, $dados, $hoje, $tipo, $entrada, $request) {
            foreach ($cobrancas as $registro) {
                $pagoEm = $dados['modo'] === 'hoje' ? $hoje : $registro->vencimento->min($hoje);
                $registro->status = StatusCobranca::Paga;
                $registro->pago_em = $pagoEm;
                $registro->desconto_centavos = Desconto::centavos($registro->valor_centavos, $tipo, $entrada);
                $registro->save();
                Auditoria::registrar($request, 'cobranca.paga', $registro, $this->resumoDaBaixa(
                    $registro,
                    Desconto::rotulo($registro->valor_centavos, $registro->desconto_centavos, $tipo, $entrada),
                    emLote: true,
                ));
            }
        });

        return back()->with('sucesso', $this->resumoDoLote($cobrancas->count(), $pedidas, 'baixada'));
    }

    /**
     * O desconto do lote vale para cada pendente selecionada: o mesmo percentual (arredondado por cobrança) ou o mesmo valor
     * em R$, que não pode passar da menor delas (nenhuma fica negativa). Paga ou cancelada selecionada não entra na conta.
     *
     * @param  Collection<int, Cobranca>  $pendentes
     */
    private function conferirDescontoDoLote(Collection $pendentes, string $tipo, ?string $entrada): void
    {
        $menor = $pendentes->min('valor_centavos');
        $centavos = Desconto::centavos($menor ?? 0, $tipo, $entrada);

        if ($centavos === null) {
            throw ValidationException::withMessages(['desconto' => 'Desconto inválido.']);
        }

        if ($tipo === Desconto::PERCENTUAL && ! Desconto::percentualValido($entrada)) {
            throw ValidationException::withMessages(['desconto' => 'Percentual inválido: use de 0 a 100, com até 2 casas decimais.']);
        }

        if ($tipo === Desconto::VALOR && $menor !== null && $centavos > $menor) {
            throw ValidationException::withMessages(['desconto' => 'O desconto não pode passar do valor da menor cobrança (R$ '.Dinheiro::formatar($menor).').']);
        }
    }

    /** T17.19: cancelamento de várias cobranças de uma vez. */
    public function cancelarEmLote(Request $request): RedirectResponse
    {
        $dados = $request->validate(['ids' => ['required', 'array', 'max:200'], 'ids.*' => ['integer']]);

        $pedidas = count($dados['ids']);
        $cobrancas = $request->user()->cobrancas()->whereIn('id', $dados['ids'])->where('status', StatusCobranca::Pendente)->get();

        DB::transaction(function () use ($cobrancas, $request) {
            foreach ($cobrancas as $registro) {
                $registro->status = StatusCobranca::Cancelada;
                $registro->save();
                Auditoria::registrar($request, 'cobranca.cancelada', $registro, 'Cancelada em lote (R$ '
                    .Dinheiro::formatar($registro->valor_centavos).', vence '.$registro->vencimento->format('d/m/Y').')');
            }
        });

        return back()->with('sucesso', $this->resumoDoLote($cobrancas->count(), $pedidas, 'cancelada'));
    }

    /** "2 cobrança(s) baixada(s)." + ", N ignorada(s) (não estavam pendentes)." quando alguma pedida não entrou. */
    private function resumoDoLote(int $feitas, int $pedidas, string $participio): string
    {
        $resumo = "{$feitas} cobrança(s) {$participio}(s).";
        $ignoradas = $pedidas - $feitas;

        return $ignoradas > 0 ? "{$resumo} {$ignoradas} ignorada(s) (não estavam pendentes)." : $resumo;
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
            // A linha digitável traz o vencimento antigo embutido (mesmo motivo do ajuste proporcional): sem ela,
            // o boleto cobraria na data errada.
            $registro->update(['vencimento' => $dados['vencimento'], 'linha_digitavel' => null]);
            Auditoria::registrar($request, 'cobranca.vencimento_prorrogado', $registro,
                'Vencimento prorrogado de '.$antes->format('d/m/Y').' para '.$registro->vencimento->format('d/m/Y'),
                ['vencimento' => $antes->toDateString()], ['vencimento' => $registro->vencimento->toDateString()]);

            if ($registro->recibo !== null) {
                $emissor->refazer($registro->recibo);
            }
        });

        return back()->with('sucesso', 'Vencimento prorrogado.');
    }

    /** Baixa com a data ou o desconto errados: troca os dois (senha + motivo, registrado na auditoria). */
    public function corrigirPagamento(Request $request, int $cobranca): RedirectResponse
    {
        $registro = $request->user()->cobrancas()->findOrFail($cobranca);
        $dados = $request->validate(['pago_em' => ['required', 'date_format:Y-m-d', new AnoValido, 'before_or_equal:today']] + $this->confirmacao(), $this->mensagensDeConfirmacao());

        if ($registro->status !== StatusCobranca::Paga) {
            return back()->with('erro', 'Só cobranças pagas têm o pagamento corrigido.');
        }

        // Pedido sem os campos do desconto corrige só a data: o desconto atual fica (a tela sempre os manda).
        $descontoDepois = $request->hasAny(['desconto', 'desconto_tipo'])
            ? $this->descontoValidado($request, $registro->valor_centavos)
            : $registro->desconto_centavos;
        $antes = $registro->pago_em;
        $descontoAntes = $registro->desconto_centavos;

        DB::transaction(function () use ($registro, $request, $dados, $antes, $descontoAntes, $descontoDepois) {
            $registro->pago_em = $dados['pago_em'];
            $registro->desconto_centavos = $descontoDepois;
            $registro->save();
            $resumo = 'Pagamento corrigido (antes: '.$antes->format('d/m/Y');
            if ($descontoAntes !== $descontoDepois) {
                $resumo .= ', desconto de R$ '.Dinheiro::formatar($descontoAntes).' para R$ '.Dinheiro::formatar($descontoDepois);
            }
            Auditoria::registrar($request, 'pagamento.corrigido', $registro, $resumo.')',
                ['pago_em' => $antes->toDateString(), 'desconto_centavos' => $descontoAntes],
                ['pago_em' => $registro->pago_em->toDateString(), 'desconto_centavos' => $descontoDepois], trim($dados['motivo']));
        });

        return back()->with('sucesso', 'Pagamento corrigido. Se o recibo pago já foi enviado, envie de novo: ele sai com os dados novos.');
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
        $descontoAntes = $registro->desconto_centavos;

        DB::transaction(function () use ($registro, $request, $dados, $antes, $descontoAntes) {
            $registro->status = StatusCobranca::Pendente;
            $registro->pago_em = null;
            $registro->desconto_centavos = 0;
            $registro->save();
            Auditoria::registrar($request, 'baixa.desfeita', $registro, 'Baixa desfeita (estava paga em '.$antes->format('d/m/Y').')',
                ['status' => 'paga', 'pago_em' => $antes->toDateString(), 'desconto_centavos' => $descontoAntes],
                ['status' => 'pendente', 'pago_em' => null, 'desconto_centavos' => 0], trim($dados['motivo']));
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
        $registro = $request->user()->cobrancas()->with('contrato')->findOrFail($cobranca);
        $dados = $request->validate(['pago_em' => ['required', 'date_format:Y-m-d', new AnoValido, 'before_or_equal:today']]);

        if ($registro->status !== StatusCobranca::Pendente) {
            return back()->with('erro', 'Só cobranças pendentes recebem baixa.');
        }

        $valores = ValoresDaBaixa::resolver(
            $registro,
            CarbonImmutable::parse($dados['pago_em']),
            $request->validate(ValoresDaBaixa::regras(), ValoresDaBaixa::MENSAGENS),
            ValoresDaBaixa::INDIVIDUAL,
        );

        DB::transaction(function () use ($registro, $dados, $valores, $request) {
            $registro->status = StatusCobranca::Paga;
            $registro->pago_em = $dados['pago_em'];
            $registro->desconto_centavos = $valores['desconto_centavos'];
            $registro->multa_centavos = $valores['multa_centavos'];
            $registro->juros_centavos = $valores['juros_centavos'];
            $registro->outros_centavos = $valores['outros_centavos'];
            $registro->save();
            Auditoria::registrar($request, 'cobranca.paga', $registro, $this->resumoDaBaixa($registro, $valores['rotulo_desconto']));
        });

        return back()->with('sucesso', 'Baixa registrada.');
    }

    /** Desconto da baixa em centavos, validado e conferido (0 ≤ desconto ≤ valor); sem desconto digitado, 0. */
    private function descontoValidado(Request $request, int $valorCentavos): int
    {
        $dados = $request->validate(ValoresDaBaixa::regrasDoDesconto(), ValoresDaBaixa::MENSAGENS);

        return ValoresDaBaixa::descontoDigitado($valorCentavos, $dados['desconto_tipo'] ?? Desconto::PERCENTUAL, $dados['desconto'] ?? null);
    }

    /**
     * Texto da auditoria da baixa (individual ou em lote). Sem acréscimos, o da Fase 26: sem desconto o de sempre, com
     * desconto mostra o desconto e o valor pago. Com multa, juros ou outros acréscimos, mostra só os que existem e sempre o
     * desconto, mesmo R$ 0,00. `$rotuloDoDesconto` é o texto do desconto ("10% = R$ 24,76").
     */
    private function resumoDaBaixa(Cobranca $registro, string $rotuloDoDesconto, bool $emLote = false): string
    {
        $valor = 'R$ '.Dinheiro::formatar($registro->valor_centavos);
        $pagoEm = $registro->pago_em->format('d/m/Y');
        $titulo = $emLote ? 'Baixa em lote' : 'Baixa registrada';
        $pago = 'R$ '.Dinheiro::formatar($registro->valorPagoCentavos());

        if ($registro->acrescimosCentavos() > 0) {
            $acrescimos = collect(['multa' => $registro->multa_centavos, 'juros' => $registro->juros_centavos, 'outros' => $registro->outros_centavos])
                ->filter(fn (int $centavos) => $centavos > 0)
                ->map(fn (int $centavos, string $nome) => "{$nome} R$ ".Dinheiro::formatar($centavos))
                ->implode(', ');

            return "{$titulo} ({$valor}, {$acrescimos}, desconto {$rotuloDoDesconto}, pago {$pago}, em {$pagoEm})";
        }

        if ($registro->desconto_centavos === 0) {
            return "{$titulo} ({$valor}, paga em {$pagoEm})";
        }

        if ($registro->desconto_centavos === $registro->valor_centavos) {
            return ($emLote ? 'Baixa em lote com' : 'Baixa com')." desconto integral (100% = {$valor}, pago R$ 0,00, em {$pagoEm})";
        }

        return "{$titulo} ({$valor}, desconto {$rotuloDoDesconto}, pago {$pago}, em {$pagoEm})";
    }

    /** Chamado pelo botão Enviar depois que a folha de compartilhamento (ou o download) concluiu. */
    public function marcarEnviada(Request $request, int $cobranca): RedirectResponse
    {
        $registro = $request->user()->cobrancas()->findOrFail($cobranca);
        $registro->enviado_em = now();
        $registro->save();
        Auditoria::registrar($request, 'cobranca.enviada', $registro, 'Recibo enviado (R$ '.Dinheiro::formatar($registro->valor_centavos).')');

        return back();
    }
}
