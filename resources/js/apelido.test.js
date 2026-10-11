import { test } from 'node:test';
import assert from 'node:assert/strict';
import { apelidoDoCliente, comApelidoNaDescricao, descricaoComecaCom } from './apelido.js';

const regional = { id: 7, nome: 'Regional Araci', nome_oficial: 'Zeta Sindicato' };

test('apelido só do cliente escolhido no formulário e que tem apelido', () => {
    assert.equal(apelidoDoCliente(regional, 7), 'Regional Araci');
    assert.equal(apelidoDoCliente(regional, '7'), 'Regional Araci', 'o id vem número ou texto');
    assert.equal(apelidoDoCliente(regional, ''), '', 'campo de cliente apagado');
    assert.equal(apelidoDoCliente({ id: 8, nome: 'Bruno Lima', nome_oficial: null }, 8), '', 'sem apelido');
    assert.equal(apelidoDoCliente(null, 7), '');
});

test('o apelido entra como primeira linha, antes do que já foi digitado', () => {
    assert.equal(comApelidoNaDescricao('', 'Regional Araci'), 'Regional Araci');
    assert.equal(comApelidoNaDescricao('Hospedagem do domínio x.com', 'Regional Araci'), 'Regional Araci\nHospedagem do domínio x.com');
});

test('o botão some quando a primeira linha já é o apelido', () => {
    assert.equal(descricaoComecaCom('Regional Araci\nHospedagem', 'Regional Araci'), true);
    assert.equal(descricaoComecaCom('  Regional Araci  ', 'Regional Araci'), true);
    assert.equal(descricaoComecaCom('Hospedagem\nRegional Araci', 'Regional Araci'), false);
    assert.equal(descricaoComecaCom('', 'Regional Araci'), false);
});
