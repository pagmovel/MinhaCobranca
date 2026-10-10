import { test } from 'node:test';
import assert from 'node:assert/strict';
import { formatadores as f } from './mascaras.js';

test('valor entra pelos centavos', () => {
    assert.equal(f.valor(''), '');
    assert.equal(f.valor('2'), '0,02');
    assert.equal(f.valor('26060'), '260,60');
    assert.equal(f.valor('260,60'), '260,60');
    assert.equal(f.valor('1.234,56'), '1.234,56');
    assert.equal(f.valor('R$ 1234567,89'), '1.234.567,89');
    assert.equal(f.valor('00012'), '0,12');
});

test('documento vira CPF até 11 dígitos e CNPJ (inclusive com letras) depois', () => {
    assert.equal(f.documento('529'), '529');
    assert.equal(f.documento('52998224725'), '529.982.247-25');
    assert.equal(f.documento('11222333000181'), '11.222.333/0001-81');
    assert.equal(f.documento('12abc34501de35'), '12.ABC.345/01DE-35');
    assert.equal(f.documento('11.222.333/0001-81999'), '11.222.333/0001-81');
});

test('cpf, cnpj, cep e telefone', () => {
    assert.equal(f.cpf('52998224725999'), '529.982.247-25');
    assert.equal(f.cnpj('11222333000181'), '11.222.333/0001-81');
    assert.equal(f.cep('49160000'), '49160-000');
    assert.equal(f.telefone('7932220000'), '(79) 3222-0000');
    assert.equal(f.telefone('79999990000'), '(79) 99999-0000');
    assert.equal(f.telefone('(79'), '(79');
});

test('linha digitável de boleto (47) e de arrecadação (48, começa com 8)', () => {
    assert.equal(
        f.linhaDigitavel('23793381286000000000300000000400184340000026060'),
        '23793.38128 60000.000003 00000.000400 1 84340000026060',
    );
    assert.equal(
        f.linhaDigitavel('846700000017435900240209024050002435842210108119'),
        '84670000001-7 43590024020-9 02405000243-5 84221010811-9',
    );
});

test('minúsculas sem espaços nas pontas', () => {
    assert.equal(f.minusculas(' Fulano@Exemplo.COM '), 'fulano@exemplo.com');
});

test('telefone descarta o +55 inicial (chave PIX e telefones já gravados com DDI)', () => {
    assert.equal(f.telefone('+55 (91) 98765-4321'), '(91) 98765-4321');
    assert.equal(f.telefone('+5591987654321'), '(91) 98765-4321');
    assert.equal(f.telefone('+55 79 99999-0000'), '(79) 99999-0000');
    assert.equal(f.telefone('55 79 3222-0000'), '(79) 3222-0000');
    assert.equal(f.telefone('(55) 99999-0000'), '(55) 99999-0000', 'DDD 55 com 11 dígitos continua');
});

test('valor colado é lido como o servidor lê (não pelos centavos)', () => {
    assert.equal(f.valorColado('1.500'), '1.500,00');
    assert.equal(f.valorColado('260,6'), '260,60');
    assert.equal(f.valorColado('1234.5'), '1.234,50');
    assert.equal(f.valorColado('R$ 1.234,56'), '1.234,56');
    assert.equal(f.valorColado('260'), '260,00');
    assert.equal(f.valorColado('12.34.5'), '123,45', 'formato estranho cai na regra de digitação');
});
