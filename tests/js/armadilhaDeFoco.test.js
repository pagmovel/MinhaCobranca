import { test } from 'node:test';
import assert from 'node:assert/strict';
import { proximoDaArmadilha } from '../../resources/js/armadilhaDeFoco.js';

test('volta_ao_primeiro_depois_do_ultimo', () => {
    assert.equal(proximoDaArmadilha(3, 2, false), 0);
    assert.equal(proximoDaArmadilha(3, 0, false), 1);
});

test('vai_ao_ultimo_com_shift_no_primeiro', () => {
    assert.equal(proximoDaArmadilha(3, 0, true), 2);
    assert.equal(proximoDaArmadilha(3, 2, true), 1);
});

test('lista_vazia_devolve_menos_um', () => {
    assert.equal(proximoDaArmadilha(0, -1, false), -1);
    assert.equal(proximoDaArmadilha(0, 0, true), -1);
});

test('indice_desconhecido_vai_ao_primeiro', () => {
    assert.equal(proximoDaArmadilha(3, -1, false), 0);
    assert.equal(proximoDaArmadilha(3, -1, true), 2);
});
