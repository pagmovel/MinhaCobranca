<?php

namespace App\Services;

use App\Enums\FormaPagamento;
use App\Enums\StatusCobranca;
use App\Models\Cobranca;
use App\Models\Recibo;
use App\Support\Dinheiro;
use App\Support\Documento;
use App\Support\LinhasDoRecibo;
use App\Support\PixCopiaECola;
use App\Support\QrCodePix;
use App\Support\TextoDoRecibo;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

final class RenderizadorDeRecibo
{
    /** Dados congelados (snapshot) + dados vivos (situação, pagamento) no formato dos templates. */
    public function dados(Recibo $recibo, CarbonImmutable $agora): array
    {
        $snapshot = $recibo->snapshot;
        $servico = $snapshot['servico'];
        $cobranca = $recibo->cobranca;
        $carimbo = $this->carimbo($cobranca, $agora);
        $inicio = CarbonImmutable::parse($servico['periodo_inicio']);
        $periodo = $inicio->format('d/m/Y').' a '.CarbonImmutable::parse($servico['periodo_fim'])->format('d/m/Y');
        $itens = $servico['itens'] ?? [];
        $texto = TextoDoRecibo::compor($itens, $servico['descricao']);
        // Dado vivo, como situação e pago_em. Paga: o que foi gravado na baixa. Pendente com regra no contrato: o valor de hoje.
        $valorDoDia = $this->valorDoDia($cobranca, $agora);
        [$desconto, $liquido] = match (true) {
            $cobranca->status === StatusCobranca::Paga => [$cobranca->desconto_centavos, $cobranca->valorPagoCentavos()],
            $valorDoDia !== null => [$valorDoDia['desconto_centavos'], $valorDoDia['total_centavos']],
            default => [0, $cobranca->valor_centavos],
        };
        // Sem desconto e sem acréscimo os campos ficam nulos e o recibo sai como sempre saiu (o template usa o valor cheio).
        $comValores = $desconto > 0 || $liquido !== $cobranca->valor_centavos;
        $extenso = ValorPorExtenso::converter($comValores ? $liquido : $servico['valor_centavos']);

        return [
            'numero' => $recibo->numeroFormatado(),
            'ano_sequencia' => sprintf('%d / %06d', $recibo->ano, $recibo->numero),
            'emitido_em' => $recibo->emitido_em->format('d/m/Y H:i:s'),
            'competencia' => $inicio->format('m/Y'),
            'vencimento' => CarbonImmutable::parse($servico['vencimento'])->format('d/m/Y'),
            'pago_em' => $cobranca->pago_em?->format('d/m/Y') ?? '-',
            'situacao' => $carimbo['situacao'],
            'carimbo' => ['texto' => $carimbo['texto'], 'detalhe' => $carimbo['detalhe'], 'classe' => $carimbo['classe']],
            'prestador' => $this->parte($snapshot['prestador']),
            'tomador' => $this->parte($snapshot['tomador']),
            'servico' => [
                'codigo_servico' => $this->traco($servico['codigo_servico'] ?? null),
                'codigo_nbs' => $this->traco($servico['codigo_nbs'] ?? null),
                'codigo_tributacao_nacional' => $this->traco($servico['codigo_tributacao_nacional'] ?? null),
                'linha' => $this->traco($this->juntar([
                    $servico['codigo_servico'] ?? null,
                    filled($servico['codigo_nbs'] ?? null) ? 'NBS: '.$servico['codigo_nbs'] : null,
                ], '. ')),
                'descricao' => $texto,
                'linhas' => LinhasDoRecibo::quebrar($texto),
                'descricao_em_linha' => $itens === []
                    ? (string) preg_replace('/\s*\R\s*/', ' ', trim($servico['descricao']))
                    : TextoDoRecibo::emLinha($itens, $servico['descricao']),
                'periodo' => $periodo,
                'obs_periodo' => 'Obs.: Período de '.$periodo,
            ],
            'valor' => Dinheiro::formatar($servico['valor_centavos']),
            'desconto' => $desconto > 0 ? Dinheiro::formatar($desconto) : null,
            'valor_pago' => $comValores ? Dinheiro::formatar($liquido) : null,
            'valor_extenso' => $extenso,
            'informacoes' => array_values(array_filter([
                'Valor por extenso: '.$extenso.'.',
                $cobranca->fraseDaCondicao($agora),
                $cobranca->detalheDosAcrescimos(),
                // A linha digitável do boleto não muda com a data: quem paga o valor do dia precisa saber disso.
                $comValores && $valorDoDia !== null && $cobranca->forma_pagamento === FormaPagamento::Boleto
                    ? 'O boleto foi emitido com o valor original.' : null,
            ])),
            'gerado_em' => $agora->format('d/m/Y H:i'),
            'logo' => $this->logo($snapshot['prestador']['logo'] ?? null),
            'aplicativo' => config('app.name'),
            'pagamento' => $this->pagamento($recibo, $snapshot, $valorDoDia),
        ];
    }

    public function pdf(Recibo $recibo, CarbonImmutable $agora)
    {
        return Pdf::loadView($recibo->modelo->view(), $this->dados($recibo, $agora))->setPaper('a4', 'portrait')
            // O laravel-dompdf desliga por padrão; sem isso a DejaVu inteira vai no arquivo (~860 KB).
            ->setOption('isFontSubsettingEnabled', true);
    }

    /** Pendente com regra no contrato: as condições de hoje ({@see Cobranca::condicoesNaData()}). Senão, nulo (vale o valor do recibo). */
    private function valorDoDia(Cobranca $cobranca, CarbonImmutable $agora): ?array
    {
        return $cobranca->status === StatusCobranca::Pendente && $cobranca->contrato?->regrasDePagamento() !== null
            ? $cobranca->condicoesNaData($agora)
            : null;
    }

    /**
     * Forma de pagamento é viva (lida da cobrança); prestador e valor vêm do snapshot, como o resto do recibo. Com regra no
     * contrato, o PIX cobra o valor do dia (`$valorDoDia`).
     */
    private function pagamento(Recibo $recibo, array $snapshot, ?array $valorDoDia): array
    {
        $cobranca = $recibo->cobranca;

        // Paga ou cancelada: o recibo não pode mais dizer como pagar (o comprovante não pode ter um QR pagável).
        if ($cobranca->status !== StatusCobranca::Pendente) {
            return ['forma' => null];
        }

        if ($cobranca->forma_pagamento === FormaPagamento::Boleto) {
            return [
                'forma' => 'boleto',
                'linha_digitavel' => filled($cobranca->linha_digitavel) ? $this->linhaDigitavel($cobranca->linha_digitavel) : null,
            ];
        }

        $chave = $cobranca->forma_pagamento === FormaPagamento::Pix ? $cobranca->chavePix : null;

        $valor = $valorDoDia['total_centavos'] ?? $snapshot['servico']['valor_centavos'];

        // Desconto integral: não há o que cobrar, então nem QR nem copia e cola.
        if ($chave === null || ($valorDoDia !== null && $valor === 0)) {
            return ['forma' => null];
        }

        $payload = PixCopiaECola::gerar(
            $chave->chave,
            (string) $snapshot['prestador']['nome'],
            (string) ($snapshot['prestador']['cidade'] ?? ''),
            $valor,
            sprintf('REC%d%06d', $recibo->ano, $recibo->numero),
        );

        return [
            'forma' => 'pix',
            'chave' => $chave->tipo->formatar($chave->chave),
            'tipo' => $chave->tipo->rotulo(),
            'favorecido' => $this->traco($snapshot['prestador']['nome'] ?? null),
            'copia_e_cola' => $payload,
            'qr' => QrCodePix::dataUri($payload),
        ];
    }

    private function linhaDigitavel(string $linha): string
    {
        return strlen($linha) === 48
            ? (string) preg_replace('/^(\d{11})(\d)(\d{11})(\d)(\d{11})(\d)(\d{11})(\d)$/', '$1-$2 $3-$4 $5-$6 $7-$8', $linha)
            : (string) preg_replace('/^(\d{5})(\d{5})(\d{5})(\d{6})(\d{5})(\d{6})(\d)(\d{14})$/', '$1.$2 $3.$4 $5.$6 $7 $8', $linha);
    }

    private function carimbo(Cobranca $cobranca, CarbonImmutable $agora): array
    {
        $vencimento = $cobranca->vencimento->format('d/m/Y');

        return match ($cobranca->situacao($agora)) {
            'paga' => ['texto' => 'PAGO', 'detalhe' => 'em '.$cobranca->pago_em->format('d/m/Y'), 'classe' => 'pago', 'situacao' => 'Pago em '.$cobranca->pago_em->format('d/m/Y')],
            'cancelada' => ['texto' => 'CANCELADO', 'detalhe' => null, 'classe' => 'cancelada', 'situacao' => 'Cancelado'],
            'vencida' => ['texto' => 'VENCIDO', 'detalhe' => $vencimento, 'classe' => 'vencida', 'situacao' => 'Vencido'],
            'vence_hoje' => ['texto' => 'VENCENDO HOJE', 'detalhe' => $vencimento, 'classe' => 'vence_hoje', 'situacao' => 'Vence hoje'],
            'a_vencer' => ['texto' => 'A VENCER', 'detalhe' => $vencimento, 'classe' => 'a_vencer', 'situacao' => 'A vencer'],
        };
    }

    private function parte(array $dados): array
    {
        $campo = fn (string $chave) => $dados[$chave] ?? null;
        $rua = $this->juntar([$campo('logradouro'), $campo('numero'), $campo('complemento'), $campo('bairro')], ', ');
        $cep = filled($campo('cep')) ? (string) preg_replace('/^(\d{5})(\d{3})$/', '$1-$2', $campo('cep')) : null;
        $cidadeUf = [$campo('cidade'), $campo('uf')];
        $nome = (string) $campo('nome');

        return [
            'nome' => $this->traco($nome),
            'nome_fantasia' => $this->traco($campo('nome_fantasia')),
            'documento' => Documento::formatar($campo('documento')),
            'inscricao_municipal' => $this->traco($campo('inscricao_municipal')),
            'inscricao_estadual' => $this->traco($campo('inscricao_estadual')),
            'telefone' => $this->traco($campo('telefone')),
            'email' => $this->traco($campo('email')),
            'endereco' => $this->traco($rua),
            'endereco_completo' => $this->traco($this->juntar([$rua, $cep ? "CEP: {$cep}" : null, $this->juntar($cidadeUf, ' - ')], ' - ')),
            'cidade_uf' => $this->traco($this->juntar($cidadeUf, ' - ')),
            'municipio' => $this->traco($this->juntar($cidadeUf, ' / ')),
            'cep' => $this->traco($cep),
            'iniciais' => collect(preg_split('/\s+/', trim($nome)))->filter()->take(2)
                ->map(fn (string $palavra) => mb_strtoupper(mb_substr($palavra, 0, 1)))->implode(''),
        ];
    }

    private function juntar(array $partes, string $separador): string
    {
        return collect($partes)->filter(fn ($parte) => filled($parte))->implode($separador);
    }

    private function traco(?string $valor): string
    {
        return filled($valor) ? $valor : '-';
    }

    /** Data URI: o dompdf não precisa acessar o disco nem a rede para desenhar o logo. */
    private function logo(?string $caminho): ?string
    {
        if (blank($caminho) || ! Storage::disk('local')->exists($caminho)) {
            return null;
        }

        return 'data:'.Storage::disk('local')->mimeType($caminho).';base64,'
            .base64_encode(Storage::disk('local')->get($caminho));
    }
}
