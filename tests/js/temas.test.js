import { test } from 'node:test';
import assert from 'node:assert/strict';
import { componenteDoPainel, temaValido } from '../../resources/js/temas.js';

const lista = [{ id: 'talao' }, { id: 'regua' }, { id: 'almanaque' }];

test('id_desconhecido_cai_no_primeiro', () => {
    assert.equal(temaValido('inexistente', lista), 'talao');
    assert.equal(temaValido('', [{ id: 'regua' }]), 'regua');
});

test('id_valido_e_mantido', () => {
    assert.equal(temaValido('almanaque', lista), 'almanaque');
});

test('lista_vazia_devolve_talao', () => {
    assert.equal(temaValido('regua', []), 'talao');
});

test('painel_desconhecido_usa_empilhado', () => {
    assert.equal(componenteDoPainel('qualquer-coisa'), 'empilhado');
    assert.equal(componenteDoPainel(undefined), 'empilhado');
});

test('painel_conhecido_e_mantido', () => {
    for (const painel of ['empilhado', 'mosaico', 'regua']) {
        assert.equal(componenteDoPainel(painel), painel);
    }
});
