import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    condicoesNaData,
    descontoDoContrato,
    diasDeAtraso,
    entradaDaRegra,
    exemploDasCondicoes,
    jurosEmCentavos,
    multaEmCentavos,
    regraDaEntrada,
    regraDoCampo,
} from './condicoes.js';

// Mesmos casos de tests/Unit/CondicoesDePagamentoTest.php: a tela e o servidor precisam concordar.
// Rode também com TZ=America/Sao_Paulo: as datas são de calendário e não podem depender do fuso.
const multas = [
    [21000, 'percentual', 200, 420],
    [21000, 'valor', 500, 500],
    [10050, 'percentual', 100, 101],
    [21000, null, 200, 0],
    [21000, 'percentual', null, 0],
    [21000, 'percentual', 0, 0],
    [21000, 'valor', null, 0],
];

for (const [valor, tipo, regra, esperado] of multas) {
    test(`multa ${tipo} ${regra} sobre ${valor} dá ${esperado}`, () => {
        assert.equal(multaEmCentavos(valor, tipo, regra), esperado);
    });
}

const juros = [
    [21000, 'percentual', 100, 0, 0],
    [21000, 'percentual', 100, 1, 7],
    [21000, 'percentual', 100, 15, 105],
    [21000, 'percentual', 100, 30, 210],
    [21000, 'percentual', 100, 300, 2100],
    [45000, 'percentual', 10, 1, 2],
    [21000, 'valor', 3000, 1, 100],
    [21000, 'valor', 3000, 15, 1500],
    [21000, 'valor', 45, 1, 2],
    [21000, 'valor', 3000, 0, 0],
    [21000, null, 100, 30, 0],
    [21000, 'percentual', null, 30, 0],
    [21000, 'valor', 0, 30, 0],
    [21000, 'percentual', 100, -5, 0],
    [10000, 'valor', 9_999_999_999, 10, 3333333333],
    [1000000000, 'percentual', 10000, 3650, 121666666667], // o produto passa de 2^53: só BigInt acerta
];

for (const [valor, tipo, regra, dias, esperado] of juros) {
    test(`juros ${tipo} ${regra} em ${dias} dia(s) sobre ${valor} dá ${esperado}`, () => {
        assert.equal(jurosEmCentavos(valor, tipo, regra, dias), esperado);
    });
}

const descontos = [
    [10000, 'percentual', 1500, 1500],
    [10000, 'valor', 1500, 1500],
    [10000, 'valor', 20000, 10000],
    [10000, 'percentual', 10000, 10000],
    [10000, null, 1500, 0],
    [10000, 'percentual', null, 0],
    [10000, 'valor', 0, 0],
];

for (const [valor, tipo, regra, esperado] of descontos) {
    test(`desconto ${tipo} ${regra} sobre ${valor} dá ${esperado}`, () => {
        assert.equal(descontoDoContrato(valor, tipo, regra), esperado);
    });
}

const entradas = [
    ['percentual', '2', 200],
    ['percentual', '2,5', 250],
    ['percentual', '2.5', 250],
    ['percentual', '100', 10000],
    ['percentual', '100,00', 10000],
    ['percentual', '150', null],
    ['percentual', '100,01', null],
    ['percentual', 'abc', null],
    ['percentual', '1,234', null],
    ['percentual', '', 0],
    ['percentual', null, 0],
    ['percentual', '0', 0],
    ['valor', '15,00', 1500],
    ['valor', '1.234,56', 123456],
    ['valor', '0', 0],
    ['valor', '  ', 0],
    ['valor', 'abc', null],
    ['valor', '-5', null],
    ['valor', '99999999999999999999', null],
    ['valor', '99.999.999,99', 9_999_999_999],
    ['valor', '100.000.000,00', null],
    ['valor', '9999999999999999,99', null],
    ['abatimento', '10', null],
];

for (const [tipo, entrada, esperado] of entradas) {
    test(`regraDaEntrada ${tipo} ${JSON.stringify(entrada)} dá ${esperado}`, () => {
        assert.equal(regraDaEntrada(tipo, entrada), esperado);
    });
}

const regras = [
    ['percentual', 1500, '15'],
    ['percentual', 250, '2,5'],
    ['percentual', 1005, '10,05'],
    ['percentual', 5, '0,05'],
    ['valor', 1500, '15,00'],
    ['valor', 123456, '1.234,56'],
    [null, null, ''],
    ['percentual', null, ''],
    [null, 1500, ''],
    ['valor', 0, ''],
];

for (const [tipo, regra, esperado] of regras) {
    test(`entradaDaRegra ${tipo} ${regra} dá ${JSON.stringify(esperado)}`, () => {
        assert.equal(entradaDaRegra(tipo, regra), esperado);
    });
}

test('a entrada da regra volta a mesma regra', () => {
    for (const [tipo, regra] of [['percentual', 1500], ['percentual', 250], ['percentual', 5], ['percentual', 10000], ['valor', 1500], ['valor', 123456], ['valor', 1]]) {
        assert.equal(regraDaEntrada(tipo, entradaDaRegra(tipo, regra)), regra);
    }
});

const atrasos = [
    ['2026-01-05', '2026-11-01', 300],
    ['2026-11-01', '2026-11-01', 0],
    ['2026-11-05', '2026-11-01', 0],
    ['2026-02-15', '2026-02-16', 1],
    ['2026-12-31', '2027-01-01', 1],
    ['2028-02-28', '2028-03-01', 2],
    ['2026-01-05', '', 0],
    ['', '2026-01-05', 0],
];

for (const [vencimento, data, esperado] of atrasos) {
    test(`de ${vencimento || 'vazio'} a ${data || 'vazio'} são ${esperado} dia(s) de atraso`, () => {
        assert.equal(diasDeAtraso(vencimento, data), esperado);
    });
}

test('a virada do horário de verão (outubro e fevereiro) não muda os dias corridos', () => {
    // O Brasil teve horário de verão até 2018: com Date local, 2018-11-03 → 2018-11-05 daria 1 dia e 23 h.
    assert.equal(diasDeAtraso('2018-11-03', '2018-11-05'), 2);
    assert.equal(diasDeAtraso('2019-02-16', '2019-02-18'), 2);
});

const condicoes = {
    desconto: { tipo: 'percentual', valor: 1500 },
    multa: { tipo: 'percentual', valor: 200 },
    juros: { tipo: 'percentual', valor: 100 },
};

test('até o vencimento vale o desconto e não há acréscimo', () => {
    assert.deepEqual(condicoesNaData(10000, '2026-01-05', '2026-01-05', condicoes), {
        dias: 0,
        descontoCentavos: 1500,
        multaCentavos: 0,
        jurosCentavos: 0,
        totalCentavos: 8500,
    });
    assert.equal(condicoesNaData(10000, '2026-01-05', '2025-12-20', condicoes).totalCentavos, 8500);
});

test('depois do vencimento vale multa e juros e não há desconto', () => {
    assert.deepEqual(condicoesNaData(10000, '2026-01-05', '2026-02-04', condicoes), {
        dias: 30,
        descontoCentavos: 0,
        multaCentavos: 200,
        jurosCentavos: 100,
        totalCentavos: 10300,
    });
});

test('só com multa e juros o valor em dia é o original; só com desconto o atrasado também', () => {
    const soAcrescimos = { desconto: null, multa: { tipo: 'valor', valor: 700 }, juros: { tipo: 'percentual', valor: 300 } };
    assert.equal(condicoesNaData(10000, '2026-01-05', '2026-01-05', soAcrescimos).totalCentavos, 10000);
    assert.equal(condicoesNaData(10000, '2026-01-05', '2026-02-04', soAcrescimos).totalCentavos, 11000);

    const soDesconto = { desconto: { tipo: 'percentual', valor: 1500 }, multa: null, juros: null };
    assert.equal(condicoesNaData(10000, '2026-01-05', '2026-02-04', soDesconto).totalCentavos, 10000);
});

test('desconto em reais maior que o valor não deixa o total negativo', () => {
    const regrasDoContrato = { desconto: { tipo: 'valor', valor: 20000 }, multa: null, juros: null };
    const resultado = condicoesNaData(10000, '2026-01-05', '2026-01-05', regrasDoContrato);

    assert.equal(resultado.descontoCentavos, 10000);
    assert.equal(resultado.totalCentavos, 0);
});

test('sem regra (cobrança avulsa ou contrato sem condições) o total é o valor', () => {
    for (const regrasDoContrato of [null, undefined, { desconto: null, multa: null, juros: null }]) {
        assert.deepEqual(condicoesNaData(10000, '2026-01-05', '2026-02-04', regrasDoContrato), {
            dias: 30,
            descontoCentavos: 0,
            multaCentavos: 0,
            jurosCentavos: 0,
            totalCentavos: 10000,
        });
    }
});

const camposDaTela = [
    // [tipo, o que se digitou, a regra que vale]
    ['percentual', '2,5', { tipo: 'percentual', valor: 250 }],
    ['valor', '15,00', { tipo: 'valor', valor: 1500 }],
    ['percentual', '12,', { tipo: 'percentual', valor: 1200 }],
    ['percentual', '', null],
    ['percentual', '0', null],
    ['valor', '0,00', null],
    ['percentual', '100,01', null],
    ['percentual', 'abc', null],
    ['valor', 'abc', null],
    [null, '5', null],
];

for (const [tipo, entrada, esperado] of camposDaTela) {
    test(`o campo ${tipo} ${JSON.stringify(entrada)} da tela vale ${JSON.stringify(esperado)}`, () => {
        assert.deepEqual(regraDoCampo(tipo, entrada), esperado);
    });
}

test('exemplo da tela do contrato: em dia com o desconto e com 30 dias de atraso', () => {
    // O exemplo da spec: R$ 100,00, desconto 15%, multa 2% e juros 1% ao mês → em dia R$ 85,00, 30 dias R$ 103,00.
    assert.deepEqual(exemploDasCondicoes(10000, condicoes), { dias: 30, emDiaCentavos: 8500, atrasadoCentavos: 10300 });
});

test('o exemplo bate com a conta da baixa nas duas pontas', () => {
    const exemplo = exemploDasCondicoes(21000, condicoes);

    assert.equal(exemplo.emDiaCentavos, condicoesNaData(21000, '2026-01-05', '2026-01-05', condicoes).totalCentavos);
    assert.equal(exemplo.atrasadoCentavos, condicoesNaData(21000, '2026-01-05', '2026-02-04', condicoes).totalCentavos);
});

test('exemplo só com parte das regras e com desconto em reais acima do valor', () => {
    assert.deepEqual(
        exemploDasCondicoes(10000, { desconto: null, multa: { tipo: 'valor', valor: 700 }, juros: { tipo: 'valor', valor: 300 } }),
        { dias: 30, emDiaCentavos: 10000, atrasadoCentavos: 11000 },
    );
    assert.deepEqual(
        exemploDasCondicoes(10000, { desconto: { tipo: 'valor', valor: 20000 }, multa: null, juros: null }),
        { dias: 30, emDiaCentavos: 0, atrasadoCentavos: 10000 },
    );
});

test('sem regra nenhuma ou sem valor do contrato não há exemplo', () => {
    assert.equal(exemploDasCondicoes(10000, { desconto: null, multa: null, juros: null }), null);
    assert.equal(exemploDasCondicoes(10000, undefined), null);
    assert.equal(exemploDasCondicoes(0, condicoes), null);
});
