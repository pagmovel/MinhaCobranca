<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Calendário de dias úteis do prestador (Fase 23): sábado, domingo, feriados nacionais, Carnaval, Sexta-feira da Paixão
 * e a lista local do usuário não são dias úteis. Puro: não lê banco nem relógio.
 */
final class DiasUteis
{
    public const DIAS_DA_SEMANA = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];

    /** Lei 662/1949 (redação da Lei 10.607/2002) e Lei 6.802/1980. O 20/11 (Lei 14.759/2023) entra em `nacionais()`. */
    private const NACIONAIS = [
        '01-01' => 'Confraternização Universal',
        '04-21' => 'Tiradentes',
        '05-01' => 'Dia do Trabalho',
        '09-07' => 'Independência do Brasil',
        '10-12' => 'Nossa Senhora Aparecida',
        '11-02' => 'Finados',
        '11-15' => 'Proclamação da República',
        '12-25' => 'Natal',
    ];

    /** @var array<int, array<string, string>> por ano: data (Y-m-d) => nome do feriado */
    private array $calendario = [];

    /** @param list<array{descricao: string, tipo: string, dia: ?int, mes: ?int, data: ?string}> $locais */
    public function __construct(private readonly array $locais = []) {}

    public function ehDiaUtil(CarbonImmutable $data): bool
    {
        return $this->motivo($data) === null;
    }

    /** A própria data, se for dia útil; senão o primeiro dia útil depois dela. */
    public function proximoDiaUtil(CarbonImmutable $data): CarbonImmutable
    {
        $dia = $data->startOfDay();

        while (! $this->ehDiaUtil($dia)) {
            $dia = $dia->addDay();
        }

        return $dia;
    }

    /** Por que a data não é dia útil ("sábado", "Tiradentes", nome do feriado local); `null` se for. */
    public function motivo(CarbonImmutable $data): ?string
    {
        // Explícito (não `isWeekend()`): não depende da configuração global de fim de semana do Carbon.
        if (in_array($data->dayOfWeek, [0, 6], true)) {
            return self::DIAS_DA_SEMANA[$data->dayOfWeek];
        }

        // União: no mesmo dia, o nome do feriado nacional vence o da lista local.
        $this->calendario[$data->year] ??= $this->nacionais($data->year) + $this->locaisDoAno($data->year);

        return $this->calendario[$data->year][$data->toDateString()] ?? null;
    }

    /**
     * Nacionais, Carnaval e Sexta-feira da Paixão do ano, em ordem de data (a tela mostra só para leitura).
     *
     * @return array<string, string> data (Y-m-d) => nome
     */
    public function nacionais(int $ano): array
    {
        $pascoa = self::pascoa($ano);
        $feriados = [];

        foreach (self::NACIONAIS as $mesDia => $nome) {
            $feriados["{$ano}-{$mesDia}"] = $nome;
        }

        // Lei 14.759/2023, publicada em 22/12/2023: vale a partir de 2024.
        if ($ano >= 2024) {
            $feriados["{$ano}-11-20"] = 'Dia Nacional de Zumbi e da Consciência Negra';
        }

        // Não são feriados nacionais por lei; entram por decisão do usuário (bancos fechados). Spec da Fase 23, D1.
        $feriados[$pascoa->subDays(48)->toDateString()] = 'Carnaval';
        $feriados[$pascoa->subDays(47)->toDateString()] = 'Carnaval';
        $feriados[$pascoa->subDays(2)->toDateString()] = 'Sexta-feira da Paixão';

        ksort($feriados);

        return $feriados;
    }

    /** Domingo de Páscoa (algoritmo de Meeus/Jones/Butcher, calendário gregoriano). */
    public static function pascoa(int $ano): CarbonImmutable
    {
        $a = $ano % 19;
        $b = intdiv($ano, 100);
        $c = $ano % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mes = intdiv($h + $l - 7 * $m + 114, 31);
        $dia = ($h + $l - 7 * $m + 114) % 31 + 1;

        return CarbonImmutable::create($ano, $mes, $dia)->startOfDay();
    }

    /** @return array<string, string> data (Y-m-d) => descrição */
    private function locaisDoAno(int $ano): array
    {
        $feriados = [];

        foreach ($this->locais as $local) {
            $data = match ($local['tipo']) {
                // 29/02 só existe em ano bissexto: nos outros, o feriado não acontece (não escorrega para 01/03).
                'anual' => checkdate((int) $local['mes'], (int) $local['dia'], $ano)
                    ? sprintf('%04d-%02d-%02d', $ano, $local['mes'], $local['dia'])
                    : null,
                'corpus_christi' => self::pascoa($ano)->addDays(60)->toDateString(),
                'unico' => str_starts_with((string) $local['data'], "{$ano}-") ? $local['data'] : null,
                default => null,
            };

            if ($data !== null) {
                $feriados[$data] ??= $local['descricao'];
            }
        }

        return $feriados;
    }
}
