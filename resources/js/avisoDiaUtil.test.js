import { test } from 'node:test';
import assert from 'node:assert/strict';
import { criarAvisoDeDiaUtil } from './avisoDiaUtil.js';

const esperar = (ms) => new Promise((resolve) => { setTimeout(resolve, ms); });
const naoUtil = (data) => ({ dados: { util: false, proximo: `${data}+` } });

test('trocar a data apaga o aviso da data anterior na hora, antes de perguntar de novo', async () => {
    const mostrados = [];
    const conferir = criarAvisoDeDiaUtil(async (data) => naoUtil(data), (aviso) => mostrados.push(aviso?.proximo ?? null), 0);

    conferir('2026-10-10');
    await esperar(5);
    assert.equal(mostrados.at(-1), '2026-10-10+');

    conferir('2026-10-20');
    assert.equal(mostrados.at(-1), null, 'o "Usar…" da data anterior não pode continuar clicável');
    await esperar(5);
    assert.equal(mostrados.at(-1), '2026-10-20+');
});

test('resposta atrasada de uma data já trocada é descartada', async () => {
    const mostrados = [];
    const atraso = { '2026-10-10': 30, '2026-10-17': 0 };
    const conferir = criarAvisoDeDiaUtil(async (data) => {
        await esperar(atraso[data]);
        return naoUtil(data);
    }, (aviso) => mostrados.push(aviso?.proximo ?? null), 0);

    conferir('2026-10-10');
    await esperar(5);
    conferir('2026-10-17');
    await esperar(50);

    assert.deepEqual(mostrados, [null, null, '2026-10-17+']);
});

test('dia útil, falha do servidor, ano incompleto e campo vazio não mostram aviso', async () => {
    const mostrados = [];
    const perguntadas = [];
    const respostas = { '2026-10-14': { dados: { util: true } }, '2026-10-15': { mensagem: 'falhou' } };
    const conferir = criarAvisoDeDiaUtil(async (data) => {
        perguntadas.push(data);
        return respostas[data];
    }, (aviso) => mostrados.push(aviso), 0);

    for (const data of ['2026-10-14', '2026-10-15', '0202-10-10', '']) {
        conferir(data);
        await esperar(5);
    }

    assert.deepEqual(perguntadas, ['2026-10-14', '2026-10-15'], 'ano incompleto e vazio nem perguntam ao servidor');
    assert.ok(mostrados.every((aviso) => aviso === null));
});
