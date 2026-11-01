<?php

namespace App\Services;

use App\Models\Cobranca;
use App\Rules\ValorEmReais;
use App\Support\CondicoesDePagamento;
use App\Support\Desconto;
use App\Support\Dinheiro;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Decide, para a baixa individual, o lote e a correção, o que vale em cada valor da baixa (desconto, multa, juros e
 * outros acréscimos): o digitado ou a regra do contrato. A tabela "Entrada na baixa" da spec manda:
 *
 * | Campo no pedido | individual        | lote              | correcao         |
 * | Ausente         | regra do contrato | regra do contrato | mantém o gravado |
 * | Vazio           | sem valor         | regra do contrato | sem valor        |
 * | 0               | sem valor         | zera em todas     | sem valor        |
 * | Preenchido      | esse valor        | vale para todas   | esse valor       |
 *
 * "Outros" não tem regra: ausente ou vazio é 0 (e, na correção, ausente mantém o gravado). Sem atraso na data do
 * pagamento, multa, juros e outros são sempre zero. **Na correção, os juros digitados são o total** (em R$, uma vez, como a
 * multa), não "ao mês": o que está gravado é o total, e reenviá-lo multiplicado pelos dias o mudaria.
 */
final class ValoresDaBaixa
{
    public const INDIVIDUAL = 'individual';

    public const LOTE = 'lote';

    public const CORRECAO = 'correcao';

    public const MENSAGENS = [
        'desconto_tipo.required_with' => 'Escolha se o desconto é em % ou em R$.',
        'desconto_tipo.in' => 'Tipo de desconto inválido.',
        'multa_tipo.required_with' => 'Escolha se a multa é em % ou em R$.',
        'multa_tipo.in' => 'Tipo de multa inválido.',
        'juros_tipo.required_with' => 'Escolha se os juros são em % ou em R$.',
        'juros_tipo.in' => 'Tipo de juros inválido.',
    ];

    /**
     * Campos do desconto, iguais na baixa individual, na correção e no lote.
     *
     * @return array<string, list<mixed>>
     */
    public static function regrasDoDesconto(): array
    {
        return self::regrasDoCampo('desconto');
    }

    /**
     * Os campos da baixa: desconto, multa e juros (cada um com tipo `percentual` ou `valor`) e outros (só R$).
     *
     * @return array<string, list<mixed>>
     */
    public static function regras(): array
    {
        return [
            ...self::regrasDoDesconto(),
            ...self::regrasDoCampo('multa'),
            ...self::regrasDoCampo('juros'),
            'outros' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * Desconto em centavos para o que se digitou, conferido de novo no servidor (a tela já trava em 100%):
     * 0 ≤ desconto ≤ valor. Vazio é 0.
     */
    public static function descontoDigitado(int $valorCentavos, string $tipo, ?string $entrada): int
    {
        $centavos = Desconto::centavos($valorCentavos, $tipo, $entrada);

        if ($centavos === null) {
            throw ValidationException::withMessages(['desconto' => 'Desconto inválido.']);
        }

        if ($centavos > $valorCentavos) {
            throw ValidationException::withMessages(['desconto' => 'O desconto não pode passar do valor da cobrança.']);
        }

        return $centavos;
    }

    /**
     * Os valores da baixa em centavos e o rótulo do desconto para a auditoria ("10% = R$ 24,76" ou "R$ 24,76").
     * `$dados` é o que o pedido validou ({@see regras()}): chave ausente e chave vazia valem coisas diferentes.
     * Quem baixa várias de uma vez carrega o contrato antes (`load('contrato')`), para não consultar por cobrança.
     *
     * @param  array<string, mixed>  $dados
     * @param  self::INDIVIDUAL|self::LOTE|self::CORRECAO  $modo
     * @return array{desconto_centavos: int, multa_centavos: int, juros_centavos: int, outros_centavos: int, rotulo_desconto: string}
     *
     * @throws ValidationException no campo que errou
     */
    public static function resolver(Cobranca $cobranca, CarbonImmutable $pagoEm, array $dados, string $modo): array
    {
        $dias = CondicoesDePagamento::diasDeAtraso($cobranca->vencimento, $pagoEm);
        $regras = $cobranca->contrato?->regrasDePagamento();

        [$desconto, $rotulo] = self::desconto($cobranca, $dias, $regras['desconto'] ?? null, $dados, $modo);
        $multa = self::acrescimo('multa', $cobranca, $dias, $regras['multa'] ?? null, $dados, $modo);
        $juros = self::acrescimo('juros', $cobranca, $dias, $regras['juros'] ?? null, $dados, $modo);
        $outros = self::acrescimo('outros', $cobranca, $dias, null, $dados, $modo);

        // Só depois de conferir tudo: sem atraso não há acréscimo, nem o que veio preenchido.
        if ($dias === 0) {
            $multa = $juros = $outros = 0;
        }

        return [
            'desconto_centavos' => $desconto,
            'multa_centavos' => $multa,
            'juros_centavos' => $juros,
            'outros_centavos' => $outros,
            'rotulo_desconto' => $rotulo,
        ];
    }

    /**
     * @param  ?array{tipo: string, valor: int}  $regra
     * @param  array<string, mixed>  $dados
     * @return array{0: int, 1: string} desconto em centavos e o rótulo da auditoria
     */
    private static function desconto(Cobranca $cobranca, int $dias, ?array $regra, array $dados, string $modo): array
    {
        $valor = $cobranca->valor_centavos;

        if (($digitado = self::digitado('desconto', $dados, $modo)) !== null) {
            $centavos = self::descontoDigitado($valor, $digitado['tipo'], $digitado['texto']);

            return [$centavos, self::rotulo($valor, $centavos, $digitado['tipo'], $digitado['texto'])];
        }

        if ($modo === self::CORRECAO) {
            return [$cobranca->desconto_centavos, self::rotulo($valor, $cobranca->desconto_centavos, null, null)];
        }

        // O desconto do contrato é por pontualidade: só vale até o vencimento.
        $centavos = $dias === 0 ? CondicoesDePagamento::descontoCentavos($valor, $regra['tipo'] ?? null, $regra['valor'] ?? null) : 0;

        return [$centavos, self::rotulo($valor, $centavos, $regra['tipo'] ?? null, CondicoesDePagamento::entradaDaRegra($regra['tipo'] ?? null, $regra['valor'] ?? null))];
    }

    /**
     * Multa, juros ou outros: o digitado, o gravado (na correção) ou a regra do contrato (`outros` não tem).
     *
     * @param  ?array{tipo: string, valor: int}  $regra
     * @param  array<string, mixed>  $dados
     */
    private static function acrescimo(string $campo, Cobranca $cobranca, int $dias, ?array $regra, array $dados, string $modo): int
    {
        $valor = $cobranca->valor_centavos;
        $digitado = self::digitado($campo, $dados, $modo);

        // Os juros da correção são o total gravado: a mesma conta da multa, sem o × dias/30.
        $conta = $campo === 'juros' && $modo === self::CORRECAO ? 'multa' : $campo;

        return match (true) {
            $digitado !== null => self::calcular($conta, $valor, $dias, $digitado['tipo'], self::regraDigitada($campo, $digitado)),
            $modo === self::CORRECAO => $cobranca->{"{$campo}_centavos"},
            default => self::calcular($campo, $valor, $dias, $regra['tipo'] ?? null, $regra['valor'] ?? null),
        };
    }

    private static function calcular(string $campo, int $valor, int $dias, ?string $tipo, ?int $regra): int
    {
        return match ($campo) {
            'multa' => CondicoesDePagamento::multaCentavos($valor, $tipo, $regra),
            'juros' => CondicoesDePagamento::jurosCentavos($valor, $tipo, $regra, $dias),
            default => $regra ?? 0, // outros: a regra já são os centavos
        };
    }

    /**
     * @return array<string, list<mixed>>
     */
    private static function regrasDoCampo(string $campo): array
    {
        return [
            "{$campo}_tipo" => ['nullable', 'required_with:'.$campo, Rule::in([Desconto::PERCENTUAL, Desconto::VALOR])],
            $campo => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * O que o pedido digitou no campo, segundo a tabela da spec; null quando vale o padrão do modo (a regra do contrato,
     * ou o gravado na correção). Chave ausente: padrão. Vazio no lote: padrão. Vazio nos outros modos: sem valor.
     *
     * @param  array<string, mixed>  $dados
     * @return ?array{tipo: string, texto: string}
     */
    private static function digitado(string $campo, array $dados, string $modo): ?array
    {
        if (! array_key_exists($campo, $dados) && ! array_key_exists("{$campo}_tipo", $dados)) {
            return null;
        }

        $texto = trim((string) ($dados[$campo] ?? ''));

        if ($texto === '' && $modo === self::LOTE) {
            return null;
        }

        return ['tipo' => $campo === 'outros' ? Desconto::VALOR : (string) ($dados["{$campo}_tipo"] ?? Desconto::PERCENTUAL), 'texto' => $texto];
    }

    /**
     * A regra única de multa, juros e outros: percentual em pontos-base ou R$ em centavos, até o teto de
     * {@see ValorEmReais::MAXIMO_CENTAVOS}. Formato inválido, percentual acima de 100 e R$ acima do teto dão erro no campo.
     *
     * @param  array{tipo: string, texto: string}  $digitado
     */
    private static function regraDigitada(string $campo, array $digitado): int
    {
        $regra = CondicoesDePagamento::regraDaEntrada($digitado['tipo'], $digitado['texto']);

        if ($regra === null) {
            throw ValidationException::withMessages([$campo => $digitado['tipo'] === Desconto::PERCENTUAL
                ? 'Percentual inválido: use de 0 a 100, com até 2 casas decimais.'
                : 'Valor inválido: use reais, como 15,00, de no máximo R$ '.Dinheiro::formatar(ValorEmReais::MAXIMO_CENTAVOS).'.']);
        }

        return $regra;
    }

    /** "10% = R$ 24,76" quando há desconto com regra em %; senão só "R$ 24,76" (também "R$ 0,00" sem desconto). */
    private static function rotulo(int $valor, int $desconto, ?string $tipo, ?string $entrada): string
    {
        return Desconto::rotulo($valor, $desconto, $desconto > 0 && $tipo !== null ? $tipo : Desconto::VALOR, $entrada);
    }
}
