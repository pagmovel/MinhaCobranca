import { test } from 'node:test';
import assert from 'node:assert/strict';
import { ajustarAte } from './filtroPeriodo.js';

test('até acompanha o mês enquanto os dois eram iguais', () => {
    assert.equal(ajustarAte('2026-10', '2026-11', '2026-10'), '2026-11');
});

test('até escolhido à frente fica onde está', () => {
    assert.equal(ajustarAte('2026-10', '2026-11', '2026-12'), '2026-12');
});

test('até nunca fica antes do mês', () => {
    assert.equal(ajustarAte('2026-10', '2027-02', '2026-12'), '2027-02');
});

test('mês levado para 36 meses ou mais antes do até: volta a um mês só', () => {
    assert.equal(ajustarAte('2026-10', '2025-12', '2028-12'), '2025-12');
    assert.equal(ajustarAte('2026-10', '2026-01', '2028-12'), '2028-12', '35 meses ainda vale');
});

test('campos apagados: até vazio vira o mês; mês vazio leva o até junto', () => {
    assert.equal(ajustarAte(null, '2026-10', ''), '2026-10');
    assert.equal(ajustarAte('2026-10', '', '2026-12'), '');
});
