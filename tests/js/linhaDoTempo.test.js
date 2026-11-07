import { test } from 'node:test';
import assert from 'node:assert/strict';
import { acumuladoPorDia, diaDoPonto, empilharPontos, posicaoDoDia } from '../../resources/js/linhaDoTempo.js';

const ponto = (id, etapa, vencimento_dia, pago_dia = null, pago_centavos = null) => ({
    id,
    cliente: `Cliente ${id}`,
    valor_centavos: 10000,
    vencimento_dia,
    etapa,
    situacao: etapa === 'recebida' ? 'paga' : 'a_vencer',
    pago_dia,
    pago_centavos,
});

const quase = (obtido, esperado) => assert.ok(Math.abs(obtido - esperado) < 1e-9, `${obtido} != ${esperado}`);

test('dois_no_mesmo_dia_empilham', () => {
    // Entram fora de ordem de id: o nível segue o id crescente, a lista mantém a ordem de entrada.
    const pontos = [ponto(7, 'aguardando', 10), ponto(3, 'enviar', 10), ponto(5, 'aguardando', 11), ponto(9, 'aguardando', 10)];

    assert.deepEqual(
        empilharPontos(pontos).map(({ ponto: p, nivel }) => [p.id, nivel]),
        [[7, 1], [3, 0], [5, 0], [9, 2]],
    );
});

test('recebida_empilha_no_dia_do_pagamento', () => {
    const pontos = [ponto(1, 'aguardando', 20), ponto(2, 'recebida', 5, 20, 10000), ponto(3, 'recebida', 20, 5, 10000)];

    assert.equal(diaDoPonto(pontos[1]), 20);
    assert.equal(diaDoPonto(pontos[2]), 5);
    assert.deepEqual(empilharPontos(pontos).map(({ nivel }) => nivel), [0, 1, 0]);
});

test('acumulado_so_soma_recebidos', () => {
    const pontos = [
        ponto(1, 'recebida', 3, 2, 10000),
        ponto(2, 'recebida', 3, 2, 5000),
        ponto(3, 'recebida', 9, 4, 20000),
        ponto(4, 'aguardando', 4),
        ponto(5, 'enviar', 1),
    ];
    const acumulado = acumuladoPorDia(pontos, 5);

    assert.deepEqual(acumulado, [0, 15000, 15000, 35000, 35000]);
});

test('mes_de_28_dias', () => {
    assert.equal(acumuladoPorDia([ponto(1, 'recebida', 28, 28, 100)], 28).length, 28);
    assert.equal(acumuladoPorDia([ponto(1, 'recebida', 28, 28, 100)], 28).at(-1), 100);
    quase(posicaoDoDia(28, 28), 27.5 / 28);
});

test('mes_de_31_dias', () => {
    const acumulado = acumuladoPorDia([ponto(1, 'recebida', 31, 31, 100), ponto(2, 'recebida', 1, 1, 50)], 31);

    assert.equal(acumulado.length, 31);
    assert.equal(acumulado[0], 50);
    assert.equal(acumulado.at(-1), 150);
    quase(posicaoDoDia(31, 31), 30.5 / 31);
});

test('sem_pontos_devolve_zeros', () => {
    assert.deepEqual(acumuladoPorDia([], 4), [0, 0, 0, 0]);
    assert.deepEqual(empilharPontos([]), []);
});

test('posicao_do_primeiro_e_do_ultimo_dia', () => {
    quase(posicaoDoDia(1, 30), 0.5 / 30);
    quase(posicaoDoDia(30, 30), 29.5 / 30);
});
