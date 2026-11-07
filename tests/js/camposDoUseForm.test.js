import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { useForm } from '@inertiajs/vue3';

/**
 * O Inertia v3 grava os métodos do formulário (`data`, `reset`, `errors`...) no mesmo objeto dos campos. Um campo com o
 * nome de uma dessas propriedades é sobrescrito pelo método, ou sobrescreve o método: o aviso aparece no console e,
 * com `form.data = '...'`, o envio quebra com "form.data is not a function". Foi o que travou "Cadastrar feriado".
 */
const RAIZ = new URL('../../resources/js/', import.meta.url).pathname;
const RESERVADAS = new Set(Object.keys(useForm({})));

const arquivosVue = (pasta) => readdirSync(pasta, { withFileTypes: true }).flatMap((entrada) => {
    const caminho = join(pasta, entrada.name);

    return entrada.isDirectory() ? arquivosVue(caminho) : (caminho.endsWith('.vue') ? [caminho] : []);
});

/** Conteúdo entre as chaves de cada `useForm({ ... })` literal do texto. */
function objetosDoUseForm(texto) {
    const corpos = [];
    for (const achado of texto.matchAll(/useForm\(\s*\{/g)) {
        let profundidade = 1;
        let fim = achado.index + achado[0].length;
        while (profundidade > 0 && fim < texto.length) {
            const letra = texto[fim];
            profundidade += letra === '{' ? 1 : (letra === '}' ? -1 : 0);
            fim += 1;
        }
        corpos.push(texto.slice(achado.index + achado[0].length, fim - 1));
    }

    return corpos;
}

/** Chaves do primeiro nível de um objeto literal (ignora `...spread`). */
function chavesDoPrimeiroNivel(corpo) {
    const trechos = [];
    let profundidade = 0;
    let inicio = 0;
    for (let i = 0; i < corpo.length; i += 1) {
        const letra = corpo[i];
        if ('{[('.includes(letra)) {
            profundidade += 1;
        } else if ('}])'.includes(letra)) {
            profundidade -= 1;
        } else if (letra === ',' && profundidade === 0) {
            trechos.push(corpo.slice(inicio, i));
            inicio = i + 1;
        }
    }
    trechos.push(corpo.slice(inicio));

    return trechos
        .map((trecho) => trecho.replace(/\/\/.*$/gm, '').trim())
        .filter((trecho) => trecho && !trecho.startsWith('...'))
        .map((trecho) => trecho.match(/^['"]?(\w+)['"]?\s*(:|$)/)?.[1])
        .filter(Boolean);
}

test('o_leitor_de_chaves_acha_nome_reservado_e_ignora_spread_e_aninhado', () => {
    const corpo = " nome: '', data: '', ...resto,\n // comentario: 1,\n itens: [{ data: 1 }], reset";

    assert.deepEqual(chavesDoPrimeiroNivel(corpo), ['nome', 'data', 'itens', 'reset']);
    assert.ok(RESERVADAS.has('data') && RESERVADAS.has('reset') && RESERVADAS.has('errors'));
});

test('nenhum_useform_usa_nome_reservado_do_inertia_como_campo', () => {
    const conflitos = [];
    for (const arquivo of arquivosVue(RAIZ)) {
        for (const corpo of objetosDoUseForm(readFileSync(arquivo, 'utf8'))) {
            for (const chave of chavesDoPrimeiroNivel(corpo)) {
                if (RESERVADAS.has(chave)) {
                    conflitos.push(`${arquivo.replace(RAIZ, '')}: ${chave}`);
                }
            }
        }
    }

    assert.deepEqual(conflitos, []);
});
