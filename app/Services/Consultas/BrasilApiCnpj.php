<?php

namespace App\Services\Consultas;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * CNPJ na BrasilAPI (gratuita, sem chave; README: "em beta", termos em elaboração; sem crawling).
 * Só dados da empresa: o quadro de sócios (QSA) traz nomes de pessoas e é descartado.
 */
final class BrasilApiCnpj
{
    /** @return array{nome: string, nome_fantasia: string, logradouro: string, numero: string, complemento: string, bairro: string, cidade: string, uf: string, cep: string}|null */
    public function buscar(string $cnpj): ?array
    {
        try {
            $resposta = Http::timeout(5)->acceptJson()->get("https://brasilapi.com.br/api/cnpj/v1/{$cnpj}");
        } catch (ConnectionException) {
            return null;
        }

        $dados = $resposta->successful() ? $resposta->json() : null;
        // Campo que não é texto nem número (resposta fora do formato) conta como ausente.
        $dados = is_array($dados) ? array_map(fn ($valor) => is_scalar($valor) ? $valor : null, $dados) : null;

        if (! is_array($dados) || blank($dados['razao_social'] ?? null)) {
            return null;
        }

        $tipo = trim((string) ($dados['descricao_tipo_de_logradouro'] ?? ''));
        $logradouro = trim((string) ($dados['logradouro'] ?? ''));

        return [
            'nome' => (string) $dados['razao_social'],
            'nome_fantasia' => (string) ($dados['nome_fantasia'] ?? ''),
            // "RUA" + "VIRGILIO OLIVEIRA LIMA"; não repete quando o tipo já está no texto ("SAUN QUADRA 5").
            'logradouro' => $tipo !== '' && $logradouro !== '' && ! str_contains(mb_strtoupper($logradouro), mb_strtoupper($tipo))
                ? "{$tipo} {$logradouro}"
                : $logradouro,
            'numero' => (string) ($dados['numero'] ?? ''),
            'complemento' => (string) ($dados['complemento'] ?? ''),
            'bairro' => (string) ($dados['bairro'] ?? ''),
            'cidade' => (string) ($dados['municipio'] ?? ''),
            'uf' => (string) ($dados['uf'] ?? ''),
            'cep' => (string) ($dados['cep'] ?? ''),
        ];
    }
}
