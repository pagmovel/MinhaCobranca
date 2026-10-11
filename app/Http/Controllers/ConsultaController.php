<?php

namespace App\Http\Controllers;

use App\Services\Consultas\BrasilApiCep;
use App\Services\Consultas\BrasilApiCnpj;
use App\Services\Consultas\ViaCep;
use App\Support\Documento;
use App\Support\Ufs;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/** Preenchimento a partir de dados públicos (decisão LGPD na skill): só CNPJ e CEP, nunca CPF. */
class ConsultaController extends Controller
{
    private const DIAS_DE_CACHE = 30;

    /** Mesmos limites dos formulários de cliente e perfil. */
    private const LIMITES = ['numero' => 20];

    public function cnpj(string $cnpj, BrasilApiCnpj $brasilApi): JsonResponse
    {
        $cnpj = Documento::normalizar($cnpj);

        if (strlen($cnpj) !== 14 || ! Documento::valido($cnpj)) {
            return response()->json(['mensagem' => 'Informe um CNPJ válido para buscar os dados.'], 422);
        }

        return $this->responder("consulta:cnpj:{$cnpj}", fn () => $brasilApi->buscar($cnpj));
    }

    public function cep(string $cep, ViaCep $viaCep, BrasilApiCep $brasilApi): JsonResponse
    {
        $cep = (string) preg_replace('/\D/', '', $cep);

        if (strlen($cep) !== 8) {
            return response()->json(['mensagem' => 'Informe um CEP com 8 números.'], 422);
        }

        return $this->responder("consulta:cep:{$cep}", fn () => $viaCep->buscar($cep) ?? $brasilApi->buscar($cep));
    }

    /** Só resposta boa vai para o cache: falha ou "não encontrado" consulta de novo na próxima vez. */
    private function responder(string $chave, Closure $consultar): JsonResponse
    {
        $dados = Cache::get($chave);

        if ($dados === null) {
            $dados = $consultar();

            if ($dados === null) {
                return response()->json(['mensagem' => 'Não foi possível consultar agora; preencha manualmente.'], 404);
            }

            $dados = $this->limpar($dados);
            Cache::put($chave, $dados, now()->addDays(self::DIAS_DE_CACHE));
        }

        return response()->json($dados);
    }

    /** O que vem de fora cabe nos campos do formulário: texto aparado e cortado, UF conhecida, CEP com 8 dígitos. */
    private function limpar(array $dados): array
    {
        $limpos = [];
        foreach ($dados as $campo => $valor) {
            $limpos[$campo] = mb_substr(trim((string) $valor), 0, self::LIMITES[$campo] ?? 255);
        }

        if (array_key_exists('uf', $limpos)) {
            $uf = mb_strtoupper($limpos['uf']);
            $limpos['uf'] = in_array($uf, Ufs::TODAS, true) ? $uf : '';
        }

        if (array_key_exists('cep', $limpos)) {
            $cep = (string) preg_replace('/\D/', '', $limpos['cep']);
            $limpos['cep'] = strlen($cep) === 8 ? $cep : '';
        }

        return $limpos;
    }
}
