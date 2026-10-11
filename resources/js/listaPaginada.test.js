import { test } from 'node:test';
import assert from 'node:assert/strict';
import { reactive } from 'vue';
import { criarListaPaginada } from './listaPaginada.js';

const esperar = () => new Promise((resolver) => setImmediate(resolver));

/** Servidor falso: cada pedido fica pendente até o teste responder. */
function servidor() {
    const pedidos = [];
    const buscar = (url) => new Promise((resolver) => pedidos.push({ url, resolver }));

    return { pedidos, buscar };
}

/** Resposta de /dados: bloco de 4 páginas a partir de `inicio` (ids sequenciais), 200 linhas no total. */
function bloco(inicio, porPagina = 25, total = 200) {
    const primeiro = (inicio - 1) * porPagina;
    const quantidade = Math.max(0, Math.min(4 * porPagina, total - primeiro));

    return {
        dados: {
            linhas: Array.from({ length: quantidade }, (_, indice) => ({ id: primeiro + indice + 1 })),
            totais: [],
            total_geral: { quantidade: total, soma_centavos: 0 },
            pagina: { inicio, por_pagina: porPagina, total },
        },
    };
}

function lista(servidorFalso) {
    const filtro = reactive({ mes: '2026-10', canceladas: false, pagina: 1, por_pagina: 25 });

    return { filtro, lista: criarListaPaginada({ url: '/x/dados', filtro, buscar: servidorFalso.buscar }) };
}

test('busca o bloco e mostra a página; dentro do bloco, sem pedido novo', async () => {
    const falso = servidor();
    const { filtro, lista: l } = lista(falso);

    l.irPara(1);
    assert.equal(falso.pedidos[0].url, '/x/dados?mes=2026-10&pagina=1&por_pagina=25');
    assert.equal(l.estado.value, 'carregando');
    falso.pedidos[0].resolver(bloco(1));
    await esperar();
    assert.equal(l.estado.value, 'lista');

    l.irPara(3);
    assert.equal(falso.pedidos.length, 1);
    assert.deepEqual([filtro.pagina, l.linhas.value[0].id], [3, 51]);
});

test('clicar numa página do cache enquanto outra carrega: a resposta atrasada não muda a página', async () => {
    const falso = servidor();
    const { filtro, lista: l } = lista(falso);
    l.irPara(1);
    falso.pedidos[0].resolver(bloco(1));
    await esperar();

    l.irPara(5); // fora do cache: fica pendente
    l.irPara(3); // no cache: é a escolha mais recente
    falso.pedidos[1].resolver(bloco(5));
    await esperar();

    assert.deepEqual([filtro.pagina, l.linhas.value[0].id, l.carregando.value], [3, 51, false]);
});

test('422 na primeira carga: estado de erro no filtro, não "carregando" para sempre', async () => {
    const falso = servidor();
    const { lista: l } = lista(falso);

    l.irPara(1);
    falso.pedidos[0].resolver({ erros: { ate: 'Escolha um período de até 36 meses.' } });
    await esperar();

    assert.equal(l.estado.value, 'erro-filtro');
    assert.equal(l.erros.value.ate, 'Escolha um período de até 36 meses.');
});

test('ação termina enquanto outra página carrega: a lista recarrega mesmo assim', async () => {
    const falso = servidor();
    const { filtro, lista: l } = lista(falso);
    l.irPara(1);
    falso.pedidos[0].resolver(bloco(1));
    await esperar();

    l.irPara(6);
    l.aoTerminarAcao();

    assert.equal(falso.pedidos.length, 3, 'a recarga sai mesmo com a página 6 carregando');
    falso.pedidos[1].resolver(bloco(6));
    falso.pedidos[2].resolver(bloco(6));
    await esperar();
    assert.equal(filtro.pagina, 6);
});

test('chegada à tela: o "success" logo depois de montar não pede de novo', () => {
    const falso = servidor();
    const { lista: l } = lista(falso);

    l.irPara(1);
    l.aoTerminarAcao();

    assert.equal(falso.pedidos.length, 1);
});
