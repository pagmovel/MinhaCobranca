<?php

namespace App\Support;

/**
 * O que sai no recibo como "o que foi cobrado": um item por linha, com a conta, e a descrição complementar depois.
 * Usado pelo PDF e pela validação do limite de linhas (a mesma montagem nos dois lugares).
 */
final class TextoDoRecibo
{
    /**
     * @param  list<array{nome: string, quantidade: string, unidade: string, preco_unitario_centavos: int, total_centavos: int}>  $itens
     */
    public static function compor(array $itens, ?string $descricao): string
    {
        $linhas = array_map(self::linhaDoItem(...), $itens);
        $descricao = trim((string) $descricao);

        if ($descricao !== '') {
            if ($linhas !== []) {
                $linhas[] = '';
            }
            $linhas[] = $descricao;
        }

        return implode("\n", $linhas);
    }

    /**
     * Uma linha só (modelo nacional): itens separados por "; ", a descrição no fim com as quebras viradas em espaço.
     *
     * @param  list<array{nome: string, quantidade: string, unidade: string, preco_unitario_centavos: int, total_centavos: int}>  $itens
     */
    public static function emLinha(array $itens, ?string $descricao): string
    {
        $partes = array_map(self::linhaDoItem(...), $itens);
        $descricao = trim((string) preg_replace('/\s*\R\s*/', ' ', (string) $descricao));

        if ($descricao !== '') {
            $partes[] = $descricao;
        }

        return implode('; ', $partes);
    }

    /** @param  array{nome: string, quantidade: string, unidade: string, preco_unitario_centavos: int, total_centavos: int}  $item */
    private static function linhaDoItem(array $item): string
    {
        return sprintf(
            '%s: %s %s × R$ %s = R$ %s',
            $item['nome'],
            ItensDeVenda::formatarQuantidade(ItensDeVenda::milesimos($item['quantidade'])),
            $item['unidade'],
            Dinheiro::formatar($item['preco_unitario_centavos']),
            Dinheiro::formatar($item['total_centavos']),
        );
    }
}
