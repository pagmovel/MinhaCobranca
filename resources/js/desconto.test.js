import { test } from 'node:test';
import assert from 'node:assert/strict';
import { descontoEmCentavos, menorValor, previaDoLote, semSeparadorFinal, travarDesconto } from './desconto.js';

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
    ['percentual', '\u00A0'], // o trim() do PHP não tira o espaço inseparável; o do JS tiraria
    ['valor', '\u00A0'],
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

// Mesmo caso de tests/Feature/Cobrancas/AcoesEmLoteTest.php: a soma da prévia é a soma gravada pelo servidor.
test('prévia do lote em % arredonda por cobrança e os totais somam as linhas', () => {
    assert.deepEqual(previaDoLote([10001, 24757], 'percentual', '10'), {
        linhas: [
            { valor: 10001, desconto: 1000, pago: 9001 },
            { valor: 24757, desconto: 2476, pago: 22281 },
        ],
        totais: { valor: 34758, desconto: 3476, pago: 31282 },
    });
});

test('prévia do lote em R$ tira o mesmo valor de cada cobrança', () => {
    assert.deepEqual(previaDoLote([10001, 24757], 'valor', '20,00'), {
        linhas: [
            { valor: 10001, desconto: 2000, pago: 8001 },
            { valor: 24757, desconto: 2000, pago: 22757 },
        ],
        totais: { valor: 34758, desconto: 4000, pago: 30758 },
    });
    // R$ igual à menor: a menor fica com pago 0 (o servidor audita como integral).
    assert.deepEqual(previaDoLote([10001, 24757], 'valor', '100,01').totais, { valor: 34758, desconto: 20002, pago: 14756 });
});

test('prévia do lote sem desconto não desconta nada, em % e em R$', () => {
    for (const tipo of ['percentual', 'valor']) {
        for (const entrada of ['', '0', '  ']) {
            assert.deepEqual(previaDoLote([10001, 24757], tipo, entrada).totais, { valor: 34758, desconto: 0, pago: 34758 });
        }
    }
});

test('prévia do lote: vírgula ou ponto dão o mesmo desconto e a vírgula final é digitação em curso', () => {
    const esperado = previaDoLote([10001, 24757], 'percentual', '12.5');

    assert.deepEqual(previaDoLote([10001, 24757], 'percentual', '12,5'), esperado);
    assert.deepEqual(previaDoLote([10001, 24757], 'percentual', '12,').totais, previaDoLote([10001, 24757], 'percentual', '12').totais);
});

test('prévia do lote com 100% deixa o valor pago em zero', () => {
    assert.deepEqual(previaDoLote([10001, 24757], 'percentual', '100').totais, { valor: 34758, desconto: 34758, pago: 0 });
});

test('prévia do lote com entrada ainda inválida não desconta (a tela não pisca)', () => {
    assert.deepEqual(previaDoLote([10001], 'percentual', 'abc').totais, { valor: 10001, desconto: 0, pago: 10001 });
    assert.deepEqual(previaDoLote([10001], 'valor', 'abc').totais, { valor: 10001, desconto: 0, pago: 10001 });
});

test('prévia do lote sem cobranças tem totais zerados', () => {
    assert.deepEqual(previaDoLote([], 'percentual', '10'), { linhas: [], totais: { valor: 0, desconto: 0, pago: 0 } });
});

// O limite do R$ no lote é a menor cobrança; sem cobrança elegível o valor de referência é 0 (nunca Infinity, que o BigInt recusa).
test('menor valor do lote', () => {
    assert.equal(menorValor([24757, 10001, 30000]), 10001);
    assert.equal(menorValor([24757]), 24757);
    assert.equal(menorValor([]), 0);
});

test('a trava do lote em R$ vale pela menor cobrança', () => {
    const menor = menorValor([24757, 10001]);

    assert.deepEqual(travarDesconto(menor, 'valor', '150,00'), { entrada: '100,01', travou: true });
    assert.deepEqual(travarDesconto(menor, 'valor', '100,01'), { entrada: '100,01', travou: false });
    assert.deepEqual(travarDesconto(menor, 'percentual', '150'), { entrada: '100', travou: true });
});

// "10," passa por 10 na prévia e no valor pago; o envio tem de mandar o mesmo, senão o servidor recusa.
test('o envio tira o separador do fim, e só do fim', () => {
    assert.equal(semSeparadorFinal('10,'), '10');
    assert.equal(semSeparadorFinal('10.'), '10');
    assert.equal(semSeparadorFinal('10,5'), '10,5');
    assert.equal(semSeparadorFinal('1.234,56'), '1.234,56');
    assert.equal(semSeparadorFinal(''), '');
    assert.equal(semSeparadorFinal(null), '');
});
