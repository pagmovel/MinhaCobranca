import { test } from 'node:test';
import assert from 'node:assert/strict';
import { contarPedido, pendentes, vigiarFetch } from './carregando.js';

test('o contador sobe durante o pedido e volta a zero no fim, com sucesso ou falha', async () => {
    let terminar;
    const pedido = contarPedido(new Promise((resolve) => { terminar = resolve; }));
    assert.equal(pendentes.value, 1);

    terminar('ok');
    assert.equal(await pedido, 'ok');
    assert.equal(pendentes.value, 0);

    await assert.rejects(contarPedido(Promise.reject(new Error('rede'))));
    assert.equal(pendentes.value, 0, 'falha também apaga o indicador');
});

test('o piso de zero evita que uma baixa extra deixe o contador negativo e trave o próximo aviso', async () => {
    let terminar;
    const pedido = contarPedido(new Promise((resolve) => { terminar = resolve; }));
    assert.equal(pendentes.value, 1);

    pendentes.value = 0; // outra baixa chegou por fora enquanto este pedido ainda estava em andamento
    terminar('ok');
    await pedido;
    assert.equal(pendentes.value, 0, 'não fica negativo');

    let outroTerminar;
    const outroPedido = contarPedido(new Promise((resolve) => { outroTerminar = resolve; }));
    assert.equal(pendentes.value, 1, 'o próximo pedido ainda liga o aviso');
    outroTerminar('ok');
    await outroPedido;
});

test('vigiarFetch faz todo fetch contar, sem mudar o que ele devolve', async () => {
    const alvo = { fetch: async (url) => `resposta de ${url}` };
    vigiarFetch(alvo);

    const pedido = alvo.fetch('/cobrancas/dados');
    assert.equal(pendentes.value, 1);
    assert.equal(await pedido, 'resposta de /cobrancas/dados');
    assert.equal(pendentes.value, 0);
});

test('vigiarFetch chamado duas vezes não conta em dobro', async () => {
    const alvo = { fetch: async () => 'ok' };
    vigiarFetch(alvo);
    vigiarFetch(alvo);

    const pedido = alvo.fetch('/x');
    assert.equal(pendentes.value, 1);
    await pedido;
    assert.equal(pendentes.value, 0);
});
