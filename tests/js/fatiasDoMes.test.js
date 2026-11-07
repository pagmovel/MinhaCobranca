import { test } from 'node:test';
import assert from 'node:assert/strict';
import { fatiasDoMes } from '../../resources/js/fatiasDoMes.js';

const porcentos = (partes) => fatiasDoMes(partes).map((fatia) => fatia.porcento);

test('porcentos_somam_100', () => {
    const casos = [
        { recebido: 1, aguardando: 1, paraEnviar: 1 },
        { recebido: 12345, aguardando: 67890, paraEnviar: 333 },
        { recebido: 700000, aguardando: 200000, paraEnviar: 100000 },
        { recebido: 5, aguardando: 3, paraEnviar: 2 },
        { recebido: 1, aguardando: 0, paraEnviar: 2 },
    ];

    for (const caso of casos) {
        assert.equal(porcentos(caso).reduce((soma, porcento) => soma + porcento, 0), 100, JSON.stringify(caso));
    }
});

test('soma_zero_nao_divide_por_zero', () => {
    assert.deepEqual(fatiasDoMes({ recebido: 0, aguardando: 0, paraEnviar: 0 }), []);
});

test('fatia_zerada_continua_na_lista', () => {
    assert.deepEqual(fatiasDoMes({ recebido: 300, aguardando: 0, paraEnviar: 100 }), [
        { chave: 'recebida', centavos: 300, porcento: 75 },
        { chave: 'aguardando', centavos: 0, porcento: 0 },
        { chave: 'enviar', centavos: 100, porcento: 25 },
    ]);
});

test('maior_resto_desempata_pela_ordem', () => {
    // 33,33% cada: sobra 1 ponto e o empate vai para a primeira da ordem (recebida).
    assert.deepEqual(porcentos({ recebido: 1, aguardando: 1, paraEnviar: 1 }), [34, 33, 33]);
    // Primeira zerada e as outras iguais: 0, 50, 50, sem sobra.
    assert.deepEqual(porcentos({ recebido: 0, aguardando: 1, paraEnviar: 1 }), [0, 50, 50]);
    // Restos 66,6 / 33,3 / 0: o ponto que sobra vai para quem tem o maior resto (a primeira), não para a última.
    assert.deepEqual(porcentos({ recebido: 2, aguardando: 1, paraEnviar: 0 }), [67, 33, 0]);
    // Restos 33,3 / 66,6: o ponto que sobra vai para a segunda.
    assert.deepEqual(porcentos({ recebido: 1, aguardando: 2, paraEnviar: 0 }), [33, 67, 0]);
});
