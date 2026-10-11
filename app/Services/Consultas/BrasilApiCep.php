<?php

namespace App\Services\Consultas;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** BrasilAPI CEP v1: reserva quando o ViaCEP falha ou não conhece o CEP. */
final class BrasilApiCep
{
    /** @return array{logradouro: string, bairro: string, cidade: string, uf: string}|null */
    public function buscar(string $cep): ?array
    {
        try {
            $resposta = Http::timeout(5)->acceptJson()->get("https://brasilapi.com.br/api/cep/v1/{$cep}");
        } catch (ConnectionException) {
            return null;
        }

        $dados = $resposta->successful() ? $resposta->json() : null;

        if (! is_array($dados) || blank($dados['city'] ?? null)) {
            return null;
        }

        return [
            'logradouro' => (string) ($dados['street'] ?? ''),
            'bairro' => (string) ($dados['neighborhood'] ?? ''),
            'cidade' => (string) $dados['city'],
            'uf' => (string) ($dados['state'] ?? ''),
        ];
    }
}
