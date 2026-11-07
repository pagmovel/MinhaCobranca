import { test } from 'node:test';
import assert from 'node:assert/strict';
import { precisaRecarregar, trocarTema } from '../../resources/js/usarTema.js';

const talao = { id: 'talao', nome: 'Talão', casca: 'lateral', painel: 'empilhado', linha_do_tempo: false, amostra: ['#F5F7F9'] };
const regua = { id: 'regua', nome: 'Régua', casca: 'superior', painel: 'regua', linha_do_tempo: true, amostra: ['#FFFFFF'] };
const almanaque = { id: 'almanaque', nome: 'Almanaque', casca: 'lateral', painel: 'mosaico', linha_do_tempo: true, amostra: ['#0B1F3A'] };
const lista = [talao, regua, almanaque];

/** Cenário sem navegador: `desfecho` é o que o Inertia faria com o PUT. */
function cenario(desfecho = 'sucesso', temaNaPagina = 'talao', aoEnviar = () => {}) {
    const dataset = { tema: temaNaPagina };
    const requisicoes = [];
    const retornos = {};
    let recargas = 0;
    const roteador = {
        put(url, dados, opcoes) {
            requisicoes.push({ url, dados, opcoes, temaNoEnvio: dataset.tema });
            aoEnviar();
            if (desfecho === 'sucesso') {
                opcoes.onSuccess();
            } else if (desfecho === 'http') {
                retornos.http = opcoes.onHttpException({ status: 500 });
            } else if (desfecho === 'rede') {
                retornos.rede = opcoes.onNetworkError(new Error('sem rede'));
            }
            opcoes.onFinish();
        },
    };

    return {
        dataset,
        requisicoes,
        retornos,
        recargas: () => recargas,
        trocar: (id, anterior = talao) => trocarTema(id, {
            lista,
            anterior,
            documento: { documentElement: { dataset } },
            roteador,
            recarregar: () => { recargas++; },
        }),
    };
}

test('trocar_para_id_desconhecido_cai_no_primeiro', async () => {
    const c = cenario('sucesso', 'regua');

    const resultado = await c.trocar('inexistente', regua);

    assert.deepEqual(resultado, { ok: true });
    assert.equal(c.dataset.tema, 'talao');
    assert.equal(c.requisicoes[0].url, '/perfil/tema');
    assert.deepEqual(c.requisicoes[0].dados, { tema: 'talao' });
});

test('grava_sem_perder_a_rolagem_nem_o_estado_da_pagina', async () => {
    const c = cenario();

    await c.trocar('regua');

    assert.equal(c.requisicoes[0].opcoes.preserveScroll, true);
    assert.equal(c.requisicoes[0].opcoes.preserveState, true);
});

test('aplica_o_dataset_antes_de_a_resposta_chegar', async () => {
    const c = cenario('sucesso');

    await c.trocar('regua');

    assert.equal(c.requisicoes[0].temaNoEnvio, 'regua');
});

test('reverte_o_dataset_se_a_troca_falha', async () => {
    for (const desfecho of ['erro', 'http', 'rede']) {
        const c = cenario(desfecho);

        const resultado = await c.trocar('regua');

        assert.deepEqual(resultado, { ok: false }, desfecho);
        assert.equal(c.dataset.tema, 'talao', desfecho);
        assert.equal(c.recargas(), 0, desfecho);
    }
});

test('falha_de_http_e_de_rede_nao_abre_o_modal_de_erro_do_inertia', async () => {
    const http = cenario('http');
    const rede = cenario('rede');

    await http.trocar('regua');
    await rede.trocar('regua');

    assert.equal(http.retornos.http, false);
    assert.equal(rede.retornos.rede, false);
});

test('falha_nao_desfaz_a_escolha_mais_nova', async () => {
    const c = cenario('erro', 'talao', () => { c.dataset.tema = 'almanaque'; });

    await c.trocar('regua');

    assert.equal(c.dataset.tema, 'almanaque');
});

test('recarrega_depois_do_sucesso_quando_a_casca_muda', async () => {
    const c = cenario('sucesso');

    await c.trocar('regua');

    assert.equal(c.recargas(), 1);
});

test('recarrega_quando_a_casca_muda', () => {
    assert.equal(precisaRecarregar(talao, regua), true);
});

test('recarrega_quando_o_painel_muda_e_na_duvida_das_fontes', () => {
    assert.equal(precisaRecarregar(regua, almanaque), true);
    assert.equal(precisaRecarregar(talao, { ...talao, id: 'outro' }), true);
});

test('nao_recarrega_quando_so_a_amostra_muda', () => {
    assert.equal(precisaRecarregar(talao, { ...talao, amostra: ['#000000'], nome: 'Talão 2' }), false);
});

test('mesmo_tema_nao_recarrega_depois_do_sucesso', async () => {
    const c = cenario('sucesso');

    await c.trocar('talao');

    assert.equal(c.recargas(), 0);
});
