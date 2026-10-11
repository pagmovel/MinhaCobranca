<?php

namespace App\Services;

use App\Enums\StatusCobranca;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\CobrancaItem;
use App\Models\Recibo;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

final class EmissorDeRecibos
{
    private const CAMPOS_DO_PRESTADOR = [
        'documento', 'nome_fantasia', 'inscricao_municipal', 'inscricao_estadual', 'telefone',
        'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'cep', 'logo',
    ];

    private const TENTATIVAS = 3;

    public function __construct(private NumeradorDeRecibos $numerador) {}

    public function emitir(Cobranca $cobranca): Recibo
    {
        $agora = CarbonImmutable::now();

        for ($tentativa = 1; ; $tentativa++) {
            try {
                return DB::transaction(fn () => $cobranca->recibo()->create([
                    'user_id' => $cobranca->user_id,
                    'ano' => $agora->year,
                    'numero' => $this->numerador->proximo($cobranca->user_id, $agora->year),
                    'modelo' => $cobranca->cliente->modelo->versaoAtual(),
                    'emitido_em' => $agora,
                    'snapshot' => $this->snapshot($cobranca),
                ]));
            } catch (UniqueConstraintViolationException $erro) {
                // Duas emissões simultâneas pegaram o mesmo número (possível no PostgreSQL): tenta o próximo.
                if ($tentativa >= self::TENTATIVAS) {
                    throw $erro;
                }
            }
        }
    }

    /**
     * Cliente trocou o modelo do recibo (Fase 22): refaz, no modelo novo e com o mesmo número, os recibos das cobranças
     * em aberto e não enviadas; com `$incluirEnviadas`, também as enviadas e não pagas, que voltam para "Para enviar"
     * (o cliente precisa receber o PDF novo). Pagas e canceladas nunca mudam: o comprovante já entregue vale.
     */
    public function aplicarModeloDoCliente(Cliente $cliente, bool $incluirEnviadas): int
    {
        $cobrancas = $cliente->cobrancas()->where('status', StatusCobranca::Pendente)
            ->when(! $incluirEnviadas, fn ($consulta) => $consulta->whereNull('enviado_em'))
            ->with('recibo')->get();

        foreach ($cobrancas as $cobranca) {
            if ($cobranca->recibo) {
                $this->refazer($cobranca->recibo, trocouCliente: true);
            }
            if ($cobranca->enviado_em !== null) {
                $cobranca->forceFill(['enviado_em' => null])->save();
            }
        }

        return $cobrancas->count();
    }

    /**
     * Cobrança ainda não enviada foi editada: mesmo número, dados novos. O layout só muda aqui se o cliente mudou (o
     * modelo é do cliente); a troca de modelo no próprio cliente passa por `aplicarModeloDoCliente`.
     */
    public function refazer(Recibo $recibo, bool $trocouCliente = false): void
    {
        $cobranca = $recibo->cobranca()->firstOrFail();
        $dados = ['snapshot' => $this->snapshot($cobranca)];

        if ($trocouCliente) {
            $dados['modelo'] = $cobranca->cliente()->firstOrFail()->modelo->versaoAtual();
        }

        $recibo->update($dados);
    }

    /**
     * Ajuste em lote (Fase 23): refaz, com o mesmo número, os recibos de cobranças do mesmo prestador. Prestador e clientes
     * são lidos do banco uma vez para o lote (dentro da transação de quem chama), não uma vez por recibo; os itens
     * continuam lidos por cobrança. As cobranças vêm com `recibo` carregado e os dados novos já gravados.
     *
     * @param  Collection<int, Cobranca>  $cobrancas
     */
    public function refazerEmLote(Collection $cobrancas): void
    {
        if ($cobrancas->isEmpty()) {
            return;
        }

        if ($cobrancas->pluck('user_id')->unique()->count() > 1) {
            throw new LogicException('refazerEmLote só aceita cobranças do mesmo prestador.');
        }

        $prestador = User::query()->findOrFail($cobrancas->first()->user_id);
        $tomadores = Cliente::query()->whereKey($cobrancas->pluck('cliente_id')->unique()->all())->get()->keyBy('id');

        foreach ($cobrancas as $cobranca) {
            $cobranca->recibo?->update(['snapshot' => $this->snapshot($cobranca, $prestador, $tomadores[$cobranca->cliente_id])]);
        }
    }

    /** Sem `$prestador`/`$tomador`, lê os dois do banco agora (nunca da relação em cache: `refazer` usaria dado velho). */
    public function snapshot(Cobranca $cobranca, ?User $prestador = null, ?Cliente $tomador = null): array
    {
        $prestador ??= $cobranca->user()->firstOrFail();
        $tomador ??= $cobranca->cliente()->firstOrFail();

        return [
            'prestador' => ['nome' => $prestador->name, 'email' => $prestador->email]
                + $prestador->only(self::CAMPOS_DO_PRESTADOR),
            'tomador' => $tomador->only(Cliente::CAMPOS),
            'servico' => [
                'descricao' => $cobranca->descricao,
                'codigo_servico' => $cobranca->codigo_servico,
                'codigo_nbs' => $cobranca->codigo_nbs,
                'codigo_tributacao_nacional' => $cobranca->codigo_tributacao_nacional,
                'valor_centavos' => $cobranca->valor_centavos,
                'periodo_inicio' => $cobranca->periodo_inicio->toDateString(),
                'periodo_fim' => $cobranca->periodo_fim->toDateString(),
                'vencimento' => $cobranca->vencimento->toDateString(),
                // Cópia dos itens: o recibo lista um por linha (snapshot antigo sem itens sai como antes, só a descrição).
                'itens' => $cobranca->itens()->get()->map(fn (CobrancaItem $item) => [
                    'nome' => $item->nome,
                    'quantidade' => $item->quantidade,
                    'unidade' => $item->unidade,
                    'preco_unitario_centavos' => $item->preco_unitario_centavos,
                    'total_centavos' => $item->total_centavos,
                ])->all(),
            ],
        ];
    }
}
