<?php

namespace App\Enums;

/** Aparência do sistema escolhida pelo usuário (`users.tema`). Cada tema novo entra como um caso aqui. */
enum TemaVisual: string
{
    case Talao = 'talao';
    case Regua = 'regua';
    case Almanaque = 'almanaque';

    public function rotulo(): string
    {
        return match ($this) {
            self::Talao => 'Talão',
            self::Regua => 'Régua',
            self::Almanaque => 'Almanaque',
        };
    }

    public function descricao(): string
    {
        return match ($this) {
            self::Talao => 'O visual de sempre: menu lateral claro e o painel em duas listas.',
            self::Regua => 'Menu no topo e o mês numa linha do tempo, com cada cobrança no dia do vencimento.',
            self::Almanaque => 'Menu lateral azul-marinho, painel em blocos de cantos bem arredondados e a linha do tempo do mês.',
        };
    }

    /** Onde fica o menu: `lateral` ou `superior`. */
    public function casca(): string
    {
        return match ($this) {
            self::Talao, self::Almanaque => 'lateral',
            self::Regua => 'superior',
        };
    }

    /** Composição do Painel: `empilhado`, `colunas`, `mosaico` ou `regua`. */
    public function painel(): string
    {
        return match ($this) {
            self::Talao => 'empilhado',
            self::Regua => 'regua',
            self::Almanaque => 'mosaico',
        };
    }

    public function comLinhaDoTempo(): bool
    {
        return match ($this) {
            self::Talao, self::Regua => false,
            self::Almanaque => true,
        };
    }

    /**
     * Cores da miniatura no seletor: fundo, texto, destaque e linha.
     *
     * @return list<string>
     */
    public function amostra(): array
    {
        return match ($this) {
            self::Talao => ['#F5F7F9', '#1F2933', '#2F3C9E', '#C9D1DA'],
            self::Regua => ['#F7F9FC', '#0F1B33', '#3B47C4', '#D5DCE9'],
            self::Almanaque => ['#F2F3F8', '#161B33', '#3B47C4', '#DCE0EC'],
        };
    }

    /**
     * Famílias de fonte do tema (Bunny Fonts) e os pesos usados.
     *
     * @return array<string, list<int>>
     */
    public function fontes(): array
    {
        return match ($this) {
            self::Talao => ['Archivo' => [400, 500, 600, 700], 'Archivo Narrow' => [500, 600]],
            self::Regua => ['Bricolage Grotesque' => [700, 800], 'Figtree' => [400, 500, 600, 700]],
            self::Almanaque => ['Bricolage Grotesque' => [700, 800], 'Onest' => [400, 500, 600, 700]],
        };
    }

    /**
     * Aliases das fontes do tema no manifesto do Vite: o plugin usa o slug da família ("Archivo Narrow" vira
     * "archivo-narrow"). É o argumento do `@fonts` no layout, para a página só carregar as fontes do tema ativo.
     *
     * @return list<string>
     */
    public function aliasesDasFontes(): array
    {
        return array_map(
            fn (string $familia) => trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($familia)), '-'),
            array_keys($this->fontes()),
        );
    }

    /** Tema de quem acabou de se cadastrar (`TEMA_PADRAO` no `.env`); valor desconhecido cai no Talão. */
    public static function padrao(): self
    {
        return self::tryFrom((string) config('temas.padrao')) ?? self::Talao;
    }

    /**
     * Lista que vai para a tela (seletor de tema).
     *
     * @return list<array{id: string, nome: string, descricao: string, casca: string, painel: string, linha_do_tempo: bool, amostra: list<string>}>
     */
    public static function paraOFront(): array
    {
        return array_map(fn (self $tema) => [
            'id' => $tema->value,
            'nome' => $tema->rotulo(),
            'descricao' => $tema->descricao(),
            'casca' => $tema->casca(),
            'painel' => $tema->painel(),
            'linha_do_tempo' => $tema->comLinhaDoTempo(),
            'amostra' => $tema->amostra(),
        ], self::cases());
    }
}
