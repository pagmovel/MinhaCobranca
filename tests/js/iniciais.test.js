import { test } from 'node:test';
import assert from 'node:assert/strict';
import { iniciaisDoNome } from '../../resources/js/iniciais.js';

test('usa_a_primeira_e_a_ultima_palavra', () => {
    assert.equal(iniciaisDoNome('Marcos Ronaldo'), 'MR');
    assert.equal(iniciaisDoNome('Maria da Silva Santos'), 'MS');
});

test('nome_de_uma_palavra_so_tem_uma_inicial', () => {
    assert.equal(iniciaisDoNome('Marcos'), 'M');
});

test('ignora_espacos_sobrando_e_poe_em_maiuscula', () => {
    assert.equal(iniciaisDoNome('  ana   luísa  '), 'AL');
    assert.equal(iniciaisDoNome('émerson lima'), 'ÉL');
});

test('nome_vazio_ou_ausente_devolve_interrogacao', () => {
    assert.equal(iniciaisDoNome(''), '?');
    assert.equal(iniciaisDoNome('   '), '?');
    assert.equal(iniciaisDoNome(null), '?');
    assert.equal(iniciaisDoNome(undefined), '?');
});
