import { test } from 'node:test';
import assert from 'node:assert/strict';
import { ajustarAte, anoCompleto, periodoAoRedor } from './filtroPeriodo.js';

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

test('período ao redor: de 12 meses atrás a 12 meses à frente (cabe nos 36 meses)', () => {
    assert.deepEqual(periodoAoRedor(new Date(2026, 9, 15)), { mes: '2025-10', ate: '2027-10' });
    assert.deepEqual(periodoAoRedor(new Date(2026, 0, 31)), { mes: '2025-01', ate: '2027-01' });
});

test('ano completo: o campo de data do Chrome muda a cada dígito do ano (0002, 0020, 0202, 2026); só 2026 filtra', () => {
    assert.deepEqual(['0002-10', '0020-10', '0202-10', '2026-10'].map(anoCompleto), [false, false, false, true]);
    assert.deepEqual(['0001-08-01', '1999-08-01', '20261-08-01', '72026-08-01'].map(anoCompleto), [false, true, false, false]);
    assert.equal(anoCompleto(''), true, 'campo apagado filtra (o servidor usa o padrão)');
});
