<?php

namespace App\Services\Consultas;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** ViaCEP ("Webservice gratuito"; bloqueia uso massivo): consulta principal de CEP. */
final class ViaCep
{
    /** @return array{logradouro: string, bairro: string, cidade: string, uf: string}|null */
    public function buscar(string $cep): ?array
    {
        try {
            $resposta = Http::timeout(5)->acceptJson()->get("https://viacep.com.br/ws/{$cep}/json/");
        } catch (ConnectionException) {
            return null;
        }

        $dados = $resposta->successful() ? $resposta->json() : null;
        // Campo que não é texto nem número (resposta fora do formato) conta como ausente.
        $dados = is_array($dados) ? array_map(fn ($valor) => is_scalar($valor) ? $valor : null, $dados) : null;

        // CEP inexistente responde 200 com {"erro": "true"}.
        if (! is_array($dados) || isset($dados['erro']) || blank($dados['localidade'] ?? null)) {
            return null;
        }

        return [
            'logradouro' => (string) ($dados['logradouro'] ?? ''),
            'bairro' => (string) ($dados['bairro'] ?? ''),
            'cidade' => (string) $dados['localidade'],
            'uf' => (string) ($dados['uf'] ?? ''),
        ];
    }
}
