<?php

namespace App\Services;

use App\Enums\StatusCobranca;
use App\Models\Cobranca;
use App\Models\User;
use App\Support\DiasUteis;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cobranças já geradas que vencem em dia não útil (Fase 23, spec seção 4): só as de contrato, pendentes e não enviadas
 * (avulsas ficam fora — D2; enviadas, pagas e canceladas nunca mudam). A prévia e a execução usam a mesma consulta.
 */
final class AjusteDeVencimentos
{
    public function __construct(private EmissorDeRecibos $emissor) {}

    /** @return list<array{id: int, cliente: string, vencimento: string, motivo: string, novo_vencimento: string, tem_boleto: bool}> */
    public function previa(User $usuario): array
    {
        return $this->aAjustar($usuario)->map(fn (array $linha) => [
            'id' => $linha['cobranca']->id,
            'cliente' => $linha['cobranca']->cliente->exibicao(),
            'vencimento' => $linha['cobranca']->vencimento->format('d/m/Y'),
            'motivo' => $linha['motivo'],
            'novo_vencimento' => DiasUteis::DIAS_DA_SEMANA[$linha['novo']->dayOfWeek].', '.$linha['novo']->format('d/m/Y'),
            // D3: o vencimento do boleto está dentro da linha digitável; o recibo muda, o boleto não.
            'tem_boleto' => filled($linha['cobranca']->linha_digitavel),
        ])->all();
    }

    /**
     * Ajusta, numa transação, as escolhidas na prévia que ainda precisam (a prévia pode ter ficado velha): vencimento novo
     * e recibo refeito com o mesmo número. Id alheio, enviado ou já ajustado é ignorado. Devolve quantas mudaram.
     *
     * @param  list<int>  $ids
     */
    public function ajustar(User $usuario, array $ids): int
    {
        return DB::transaction(function () use ($usuario, $ids) {
            $aAjustar = $this->aAjustar($usuario)->filter(fn (array $linha) => in_array($linha['cobranca']->id, $ids, true));

            foreach ($aAjustar as $linha) {
                $linha['cobranca']->update(['vencimento' => $linha['novo']]);

                if ($linha['cobranca']->recibo) {
                    $this->emissor->refazer($linha['cobranca']->recibo);
                }
            }

            return $aAjustar->count();
        });
    }

    /** @return Collection<int, array{cobranca: Cobranca, novo: CarbonImmutable, motivo: string}> */
    private function aAjustar(User $usuario): Collection
    {
        $diasUteis = $usuario->diasUteis();

        return $usuario->cobrancas()
            ->where('status', StatusCobranca::Pendente)
            ->whereNull('enviado_em')
            ->whereNotNull('contrato_id')
            ->with(['cliente:id,nome,apelido', 'recibo'])
            ->orderBy('vencimento')->orderBy('id')
            ->get()
            ->reject(fn (Cobranca $cobranca) => $diasUteis->ehDiaUtil($cobranca->vencimento))
            ->map(fn (Cobranca $cobranca) => [
                'cobranca' => $cobranca,
                'novo' => $diasUteis->proximoDiaUtil($cobranca->vencimento),
                'motivo' => $diasUteis->motivo($cobranca->vencimento),
            ])
            ->values();
    }
}
