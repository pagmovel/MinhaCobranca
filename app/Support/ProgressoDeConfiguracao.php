<?php

namespace App\Support;

use App\Models\User;

class ProgressoDeConfiguracao
{
    private const ROTULOS_DO_PRESTADOR = [
        'name' => 'Nome ou razão social',
        'documento' => 'CPF ou CNPJ',
        'telefone' => 'Telefone',
        'logradouro' => 'Logradouro',
        'numero' => 'Número',
        'bairro' => 'Bairro',
        'cidade' => 'Cidade',
        'uf' => 'UF',
        'cep' => 'CEP',
    ];

    /**
     * @return array{
     *     perfil: bool, catalogo: bool, liberado: bool,
     *     pendencias: list<array{
     *         bloco: string, item: string, rotulo: string, feito: bool, link: string,
     *         seletor?: string,
     *         campos_faltando?: list<array{campo: string, rotulo: string, seletor: string}>,
     *     }>,
     * }
     */
    public static function de(User $usuario): array
    {
        $dadosPrestadorFeito = $usuario->perfilCompleto();
        $temChavePix = $usuario->chavesPix()->count() >= 1;
        $temModeloMensagem = $usuario->modelosMensagem()->count() >= 1;
        $temProdutoServico = $usuario->produtosServicos()->count() >= 1;
        $temCliente = $usuario->clientes()->count() >= 1;

        $perfil = $dadosPrestadorFeito && $temChavePix && $temModeloMensagem;
        $catalogo = $temProdutoServico && $temCliente;

        return [
            'perfil' => $perfil,
            'catalogo' => $catalogo,
            'liberado' => $perfil && $catalogo,
            'pendencias' => [
                [
                    'bloco' => 'perfil', 'item' => 'dados_prestador', 'rotulo' => 'Completar os dados do prestador',
                    'feito' => $dadosPrestadorFeito, 'link' => '/perfil',
                    'campos_faltando' => self::camposFaltando($usuario),
                ],
                [
                    'bloco' => 'perfil', 'item' => 'chave_pix', 'rotulo' => 'Cadastrar uma chave PIX',
                    'feito' => $temChavePix, 'link' => '/perfil', 'seletor' => '#nova-chave-pix',
                ],
                [
                    'bloco' => 'perfil', 'item' => 'modelo_mensagem', 'rotulo' => 'Cadastrar um modelo de mensagem',
                    'feito' => $temModeloMensagem, 'link' => '/perfil', 'seletor' => '#novo-modelo-mensagem',
                ],
                [
                    'bloco' => 'catalogo', 'item' => 'produto_servico', 'rotulo' => 'Cadastrar um produto ou serviço',
                    'feito' => $temProdutoServico, 'link' => '/produtos-servicos', 'seletor' => '#novo-produto-servico',
                ],
                [
                    'bloco' => 'catalogo', 'item' => 'cliente', 'rotulo' => 'Cadastrar um cliente',
                    'feito' => $temCliente, 'link' => '/clientes', 'seletor' => '#novo-cliente',
                ],
            ],
        ];
    }

    /** @return list<array{campo: string, rotulo: string, seletor: string}> */
    private static function camposFaltando(User $usuario): array
    {
        $faltando = [];
        foreach (User::CAMPOS_DO_PRESTADOR as $campo) {
            if (blank($usuario->{$campo})) {
                $faltando[] = ['campo' => $campo, 'rotulo' => self::ROTULOS_DO_PRESTADOR[$campo], 'seletor' => "#{$campo}"];
            }
        }

        return $faltando;
    }
}
