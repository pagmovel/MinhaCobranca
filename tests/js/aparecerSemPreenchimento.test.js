import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

/**
 * A página inteira vive num `div.aparecer`. Uma animação com preenchimento final (`both` ou `forwards`) deixa um
 * `transform` computado nesse elemento depois de terminar, e o Chrome o trata como bloco de contenção de todo
 * `position: fixed` lá dentro: o Coach e o Holofote (que são `fixed`) saíam do canto da janela e iam para o fim do
 * conteúdo, fora da tela nos Painéis longos. Só o preenchimento inicial (`backwards`) é seguro.
 */
const css = readFileSync(new URL('../../resources/css/app.css', import.meta.url), 'utf8');

test('a_animacao_da_pagina_nao_preenche_o_estado_final', () => {
    const regra = css.match(/^\.aparecer\s*\{([^}]*)\}/m)?.[1];

    assert.ok(regra, 'a regra .aparecer existe em app.css');
    assert.match(regra, /animation:\s*aparecer\b/);
    assert.doesNotMatch(regra, /\b(both|forwards)\b/);
});
