import { test } from 'node:test';
import assert from 'node:assert/strict';
import { quantosCabem } from '../../resources/js/menuResponsivo.js';

test('todos_cabem_nao_mostra_mais', () => {
    // 100 + 100 + 100 = 300 cabe em 300: não reserva espaço para o "Mais".
    assert.equal(quantosCabem([100, 100, 100], 300, 60), 3);
});

test('sobra_vai_para_o_mais', () => {
    // 340 > 300: reservando 60 para o "Mais" sobram 240, cabem 2 de 100.
    assert.equal(quantosCabem([100, 100, 100, 40], 300, 60), 2);
    // 400 > 380: reservando 60 sobram 320, cabem 3.
    assert.equal(quantosCabem([100, 100, 100, 100], 380, 60), 3);
});

test('nunca_deixa_zero_itens_na_barra', () => {
    assert.equal(quantosCabem([200, 200], 100, 60), 1);
    assert.equal(quantosCabem([200, 200], 0, 60), 1);
});

test('lista_vazia_devolve_zero', () => {
    assert.equal(quantosCabem([], 300, 60), 0);
});
