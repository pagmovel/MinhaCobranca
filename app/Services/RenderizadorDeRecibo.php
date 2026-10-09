<?php

namespace App\Services;

use App\Models\Cobranca;
use App\Models\Recibo;
use App\Support\Dinheiro;
use App\Support\Documento;
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
        $extenso = ValorPorExtenso::converter($servico['valor_centavos']);

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
                'descricao' => $servico['descricao'],
                'descricao_em_linha' => (string) preg_replace('/\s*\R\s*/', ' ', trim($servico['descricao'])),
                'periodo' => $periodo,
                'obs_periodo' => 'Obs.: Período de '.$periodo,
            ],
            'valor' => Dinheiro::formatar($servico['valor_centavos']),
            'valor_extenso' => $extenso,
            'informacoes' => ['Valor por extenso: '.$extenso.'.'],
            'gerado_em' => $agora->format('d/m/Y H:i'),
            'logo' => $this->logo($snapshot['prestador']['logo'] ?? null),
            'aplicativo' => config('app.name'),
        ];
    }

    public function pdf(Recibo $recibo, CarbonImmutable $agora)
    {
        return Pdf::loadView($recibo->modelo->view(), $this->dados($recibo, $agora))->setPaper('a4', 'portrait');
    }

    private function carimbo(Cobranca $cobranca, CarbonImmutable $agora): array
    {
        $vencimento = $cobranca->vencimento->format('d/m/Y');

        return match ($cobranca->situacao($agora)) {
            'paga' => ['texto' => 'PAGO', 'detalhe' => 'em '.$cobranca->pago_em->format('d/m/Y'), 'classe' => 'pago', 'situacao' => 'Pago em '.$cobranca->pago_em->format('d/m/Y')],
            'cancelada' => ['texto' => 'CANCELADO', 'detalhe' => null, 'classe' => 'cancelada', 'situacao' => 'Cancelado'],
            'vencida' => ['texto' => 'VENCIDO', 'detalhe' => $vencimento, 'classe' => 'vencida', 'situacao' => 'Vencido'],
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
