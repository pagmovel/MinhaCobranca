import { test } from 'node:test';
import assert from 'node:assert/strict';
import { camposFaltandoNoForm } from './progressoHolofote.js';

const campos = [
    { campo: 'telefone', rotulo: 'Telefone', seletor: '#telefone' },
    { campo: 'cep', rotulo: 'CEP', seletor: '#cep' },
];

test('filtra de novo contra o form: o que a pessoa já digitou sai da lista mesmo sem ter salvo', () => {
    const resultado = camposFaltandoNoForm(campos, { telefone: '(79) 99999-0000', cep: '' });
    assert.deepEqual(resultado, [{ campo: 'cep', rotulo: 'CEP', seletor: '#cep' }]);
});

test('lista vazia quando o form já preencheu tudo', () => {
    assert.deepEqual(camposFaltandoNoForm(campos, { telefone: 'x', cep: 'y' }), []);
});
