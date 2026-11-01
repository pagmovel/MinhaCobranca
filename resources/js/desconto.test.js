import { test } from 'node:test';
import assert from 'node:assert/strict';
import { descontoEmCentavos, travarDesconto } from './desconto.js';

// Mesmos casos de tests/Unit/DescontoTest.php: a tela e o servidor precisam concordar.
const casosValidos = [
    [24757, 'percentual', '', 0],
    [24757, 'percentual', null, 0],
    [24757, 'percentual', '   ', 0],
    [24757, 'percentual', '0', 0],
    [24757, 'percentual', '10', 2476],
    [24757, 'percentual', '12,5', 3095],
    [24757, 'percentual', '12.5', 3095],
    [24757, 'percentual', '12,50', 3095],
    [24757, 'percentual', '0,01', 2],
    [24757, 'percentual', '100', 24757],
    [24757, 'percentual', '150', 37136],
    [5, 'percentual', '50', 3],
    [1, 'percentual', '50', 1],
    [24757, 'valor', '', 0],
    [24757, 'valor', null, 0],
    [24757, 'valor', '0', 0],
    [24757, 'valor', '24,76', 2476],
    [24757, 'valor', '5', 500],
    [24757, 'valor', '260,6', 26060],
    [24757, 'valor', '1.234,56', 123456],
    [24757, 'valor', 'R$ 260', 26000],
    [24757, 'valor', '300,00', 30000],
];

for (const [valor, tipo, entrada, esperado] of casosValidos) {
    test(`${tipo} ${JSON.stringify(entrada)} sobre ${valor} dá ${esperado}`, () => {
        assert.equal(descontoEmCentavos(valor, tipo, entrada), esperado);
    });
}

const invalidos = [
    ...['percentual', 'valor'].flatMap((tipo) => ['-5', '1e3', '+5', 'abc'].map((entrada) => [tipo, entrada])),
    ['percentual', '1000'],
    ['percentual', '10,123'],
    ['percentual', '10%'],
    ['percentual', ' '], // o trim() do PHP não tira o espaço inseparável; o do JS tiraria
    ['valor', ' '],
    ['valor', '1,234,56'],
    ['abatimento', '10'],
];

for (const [tipo, entrada] of invalidos) {
    test(`${tipo} ${JSON.stringify(entrada)} é inválido`, () => {
        assert.equal(descontoEmCentavos(24757, tipo, entrada), null);
    });
}

test('percentual no maior valor de cobrança e no maior percentual aceito', () => {
    assert.equal(descontoEmCentavos(9_999_999_999, 'percentual', '999,99'), 99_998_999_990);
});

test('percentual acima de 100 vira 100', () => {
    assert.deepEqual(travarDesconto(24757, 'percentual', '150'), { entrada: '100', travou: true });
    assert.deepEqual(travarDesconto(24757, 'percentual', '100,01'), { entrada: '100', travou: true });
});

test('R$ acima do valor vira o valor, formatado como a máscara', () => {
    assert.deepEqual(travarDesconto(24757, 'valor', '300,00'), { entrada: '247,57', travou: true });
    assert.deepEqual(travarDesconto(24757, 'valor', '247,58'), { entrada: '247,57', travou: true });
    assert.deepEqual(travarDesconto(123456789, 'valor', '99.999.999,99'), { entrada: '1.234.567,89', travou: true });
});

test('dentro do limite fica como digitado', () => {
    assert.deepEqual(travarDesconto(24757, 'percentual', '100'), { entrada: '100', travou: false });
    assert.deepEqual(travarDesconto(24757, 'percentual', '12,5'), { entrada: '12,5', travou: false });
    assert.deepEqual(travarDesconto(24757, 'valor', '247,57'), { entrada: '247,57', travou: false });
    assert.deepEqual(travarDesconto(24757, 'valor', '24,76'), { entrada: '24,76', travou: false });
});

test('campo vazio ou ainda inválido não trava', () => {
    assert.deepEqual(travarDesconto(24757, 'percentual', ''), { entrada: '', travou: false });
    assert.deepEqual(travarDesconto(24757, 'valor', ''), { entrada: '', travou: false });
    assert.deepEqual(travarDesconto(24757, 'percentual', '1e3'), { entrada: '1e3', travou: false });
});
