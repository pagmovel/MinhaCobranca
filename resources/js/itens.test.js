import { test } from 'node:test';
import assert from 'node:assert/strict';
import { formatarCentavos, milesimos, totalDoItem, totalDosItens } from './itens.js';

test('quantidade com vírgula ou ponto, até 3 casas', () => {
    assert.equal(milesimos('1,5'), 1500);
    assert.equal(milesimos('2'), 2000);
    assert.equal(milesimos('0.333'), 333);
    assert.equal(milesimos('1,2345'), null);
    assert.equal(milesimos(''), null);
});

test('total igual ao do servidor (meio centavo para cima)', () => {
    assert.equal(totalDoItem('1,5', '260,60'), 39090);
    assert.equal(totalDoItem('1,5', '0,01'), 2);
    assert.equal(totalDoItem('0,333', '3,00'), 100);
    assert.equal(totalDoItem('abc', '3,00'), 0);
    assert.equal(totalDoItem('99999', '99.999.999,99'), 999989999900001);
});

test('formata centavos como a tela', () => {
    assert.equal(formatarCentavos(39090), '390,90');
    assert.equal(formatarCentavos(5), '0,05');
    assert.equal(formatarCentavos(123456789), '1.234.567,89');
});

test('formata centavos negativos com o sinal na frente (linha de descontos dos relatórios)', () => {
    assert.equal(formatarCentavos(-2476), '-24,76');
    assert.equal(formatarCentavos(-50), '-0,50');
    assert.equal(formatarCentavos(-5), '-0,05');
    assert.equal(formatarCentavos(-123456789), '-1.234.567,89');
    assert.equal(formatarCentavos(-100000), '-1.000,00');
});

test('total dos itens soma o total de cada um e ignora item sem preço', () => {
    assert.equal(totalDosItens([{ quantidade: '1,5', preco: '260,60' }, { quantidade: '2', preco: '10,00' }]), 39090 + 2000);
    assert.equal(totalDosItens([{ quantidade: '1', preco: '' }]), 0);
    assert.equal(totalDosItens([]), 0);
});
