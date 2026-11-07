import { test } from 'node:test';
import assert from 'node:assert/strict';
import { proximoDaArmadilha, saindoDaArmadilha } from '../../resources/js/armadilhaDeFoco.js';

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

test('so_prende_nas_bordas_da_lista', () => {
    // No meio da lista o Tab natural do navegador segue a ordem real (grupos de rádio, tabindex): não se mexe.
    assert.equal(saindoDaArmadilha(3, 1, false), false);
    assert.equal(saindoDaArmadilha(3, 1, true), false);
    // No último (Tab) e no primeiro (Shift+Tab) o foco sairia do diálogo.
    assert.equal(saindoDaArmadilha(3, 2, false), true);
    assert.equal(saindoDaArmadilha(3, 0, true), true);
    // Primeiro com Tab e último com Shift+Tab seguem dentro.
    assert.equal(saindoDaArmadilha(3, 0, false), false);
    assert.equal(saindoDaArmadilha(3, 2, true), false);
});

test('foco_fora_da_lista_sempre_precisa_voltar', () => {
    assert.equal(saindoDaArmadilha(3, -1, false), true);
    assert.equal(saindoDaArmadilha(3, -1, true), true);
});

test('lista_de_um_item_prende_nos_dois_sentidos', () => {
    assert.equal(saindoDaArmadilha(1, 0, false), true);
    assert.equal(saindoDaArmadilha(1, 0, true), true);
});

test('lista_vazia_nao_prende', () => {
    assert.equal(saindoDaArmadilha(0, -1, false), false);
});
