import { test } from 'node:test';
import assert from 'node:assert/strict';
import { effectScope } from 'vue';
import { criarAvisoTemporario } from './avisoTemporario.js';

test('o aviso começa vazio, aparece e some sozinho depois do tempo', (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const { aviso, mostrar } = criarAvisoTemporario(2000);

    assert.equal(aviso.value, '');
    mostrar('Máximo: 100%');
    assert.equal(aviso.value, 'Máximo: 100%');
    t.mock.timers.tick(1999);
    assert.equal(aviso.value, 'Máximo: 100%');
    t.mock.timers.tick(1);
    assert.equal(aviso.value, '');
});

test('o tempo padrão é de 2 segundos', (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const { aviso, mostrar } = criarAvisoTemporario();

    mostrar('Máximo: 100%');
    t.mock.timers.tick(1999);
    assert.equal(aviso.value, 'Máximo: 100%');
    t.mock.timers.tick(1);
    assert.equal(aviso.value, '');
});

test('mostrar de novo troca o texto e recomeça a contagem (o primeiro prazo não apaga o novo aviso)', (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const { aviso, mostrar } = criarAvisoTemporario(2000);

    mostrar('primeiro');
    t.mock.timers.tick(1500);
    mostrar('segundo');
    t.mock.timers.tick(1500);
    assert.equal(aviso.value, 'segundo');
    t.mock.timers.tick(500);
    assert.equal(aviso.value, '');
});

test('limpar apaga na hora e cancela a contagem', (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const { aviso, mostrar, limpar } = criarAvisoTemporario(2000);

    mostrar('Máximo: R$ 247,57');
    limpar();
    assert.equal(aviso.value, '');
    mostrar('outro');
    t.mock.timers.tick(1000);
    limpar();
    t.mock.timers.tick(5000);
    assert.equal(aviso.value, '');
});

test('sair da tela (escopo encerrado) cancela a contagem, sem mexer no texto', (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const escopo = effectScope();
    const { aviso, mostrar } = escopo.run(() => criarAvisoTemporario(2000));

    mostrar('Máximo: 100%');
    escopo.stop();
    t.mock.timers.tick(5000);
    assert.equal(aviso.value, 'Máximo: 100%');
});
