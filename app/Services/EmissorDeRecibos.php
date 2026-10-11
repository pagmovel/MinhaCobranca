<?php

namespace App\Services;

use App\Enums\StatusCobranca;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\CobrancaItem;
use App\Models\Recibo;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

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

    public function snapshot(Cobranca $cobranca): array
    {
        $prestador = $cobranca->user()->firstOrFail();
        $tomador = $cobranca->cliente()->firstOrFail();

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
