import { test } from 'node:test';
import assert from 'node:assert/strict';
import { chaveDoFiltro, fatiarBloco, paginasVisiveis } from './paginacao.js';

test('páginas visíveis: primeira, última, atual ±1 e reticências nos buracos', () => {
    assert.deepEqual(paginasVisiveis(1, 1), [1]);
    assert.deepEqual(paginasVisiveis(1, 3), [1, 2, 3]);
    assert.deepEqual(paginasVisiveis(5, 12), [1, '…', 4, 5, 6, '…', 12]);
    assert.deepEqual(paginasVisiveis(2, 12), [1, 2, 3, '…', 12]);
    assert.deepEqual(paginasVisiveis(12, 12), [1, '…', 11, 12]);
    assert.deepEqual(paginasVisiveis(3, 5), [1, 2, 3, 4, 5]);
});

test('chave do cache muda com os filtros e o por página, não com a página', () => {
    const base = { mes: '2026-10', ate: '2026-12', status: '', cliente_id: '', canceladas: false, por_pagina: 25, pagina: 1 };
    assert.equal(chaveDoFiltro(base), chaveDoFiltro({ ...base, pagina: 3 }));
    assert.notEqual(chaveDoFiltro(base), chaveDoFiltro({ ...base, por_pagina: 50 }));
    assert.notEqual(chaveDoFiltro(base), chaveDoFiltro({ ...base, canceladas: true }));
});

test('bloco do servidor vira linhas por página a partir da página inicial', () => {
    const linhas = Array.from({ length: 7 }, (_, indice) => indice + 1);
    assert.deepEqual(fatiarBloco(linhas, 5, 3), { 5: [1, 2, 3], 6: [4, 5, 6], 7: [7] });
    assert.deepEqual(fatiarBloco([], 1, 25), {});
});
