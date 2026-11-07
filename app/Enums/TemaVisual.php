<?php

namespace App\Enums;

/** Aparência do sistema escolhida pelo usuário (`users.tema`). Cada tema novo entra como um caso aqui. */
enum TemaVisual: string
{
    case Talao = 'talao';

    public function rotulo(): string
    {
        return match ($this) {
            self::Talao => 'Talão',
        };
    }

    public function descricao(): string
    {
        return match ($this) {
            self::Talao => 'O visual de sempre: menu lateral claro e o painel em duas listas.',
        };
    }

    /** Onde fica o menu: `lateral` ou `superior`. */
    public function casca(): string
    {
        return match ($this) {
            self::Talao => 'lateral',
        };
    }

    /** Composição do Painel: `empilhado`, `colunas`, `mosaico` ou `regua`. */
    public function painel(): string
    {
        return match ($this) {
            self::Talao => 'empilhado',
        };
    }

    public function comLinhaDoTempo(): bool
    {
        return match ($this) {
            self::Talao => false,
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
