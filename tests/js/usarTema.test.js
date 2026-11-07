import { test } from 'node:test';
import assert from 'node:assert/strict';
import { ref } from 'vue';
import { sincronizarTemaDaPagina, trocarTema } from '../../resources/js/usarTema.js';

const talao = { id: 'talao', nome: 'Talão', casca: 'lateral', painel: 'empilhado', linha_do_tempo: false, amostra: ['#F5F7F9'] };
const regua = { id: 'regua', nome: 'Régua', casca: 'superior', painel: 'regua', linha_do_tempo: true, amostra: ['#FFFFFF'] };
const almanaque = { id: 'almanaque', nome: 'Almanaque', casca: 'lateral', painel: 'mosaico', linha_do_tempo: true, amostra: ['#0B1F3A'] };
const lista = [talao, regua, almanaque];

/**
 * Cenário sem navegador: `desfecho` é o que o Inertia faria com o PUT e `fontes` o que o carregamento de fontes devolve
 * (uma função pode devolver uma promessa que nunca termina).
 */
function cenario(desfecho = 'sucesso', temaNaPagina = 'talao', aoEnviar = () => {}, fontes = () => 'ok') {
    const dataset = { tema: temaNaPagina };
    const emTroca = ref(null);
    const confirmado = ref(temaNaPagina);
    const requisicoes = [];
    const retornos = {};
    const ordem = [];
    const roteador = {
        put(url, dados, opcoes) {
            ordem.push('put');
            requisicoes.push({ url, dados, opcoes, temaNoEnvio: dataset.tema, emTrocaNoEnvio: emTroca.value });
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
        emTroca,
        confirmado,
        requisicoes,
        retornos,
        ordem,
        trocar: (id, anterior = talao) => trocarTema(id, {
            lista,
            anterior,
            documento: { documentElement: { dataset } },
            roteador,
            fontes: (idDasFontes) => { ordem.push(`fontes:${idDasFontes}`); return fontes(idDasFontes); },
            emTroca,
            confirmado,
        }),
    };
}

test('trocar_para_id_desconhecido_cai_no_primeiro', async () => {
    const c = cenario('sucesso', 'regua');

    const resultado = await c.trocar('inexistente', regua);

    assert.deepEqual(resultado, { ok: true, fontes: 'ok' });
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

test('aplica_o_tema_antes_de_a_resposta_chegar', async () => {
    const c = cenario('sucesso');

    await c.trocar('regua');

    assert.equal(c.requisicoes[0].temaNoEnvio, 'regua');
    assert.equal(c.requisicoes[0].emTrocaNoEnvio, 'regua');
});

test('tema_em_troca_some_quando_a_gravacao_termina_com_sucesso_ou_falha', async () => {
    for (const desfecho of ['sucesso', 'erro', 'http', 'rede']) {
        const c = cenario(desfecho);

        await c.trocar('regua');

        assert.equal(c.emTroca.value, null, desfecho);
    }
});

test('reverte_o_dataset_se_a_troca_falha', async () => {
    for (const desfecho of ['erro', 'http', 'rede']) {
        const c = cenario(desfecho);

        const resultado = await c.trocar('regua');

        assert.deepEqual(resultado, { ok: false, substituida: false }, desfecho);
        assert.equal(c.dataset.tema, 'talao', desfecho);
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

test('gravacao_ok_confirma_o_tema_e_falha_deixa_o_confirmado_como_estava', async () => {
    const ok = cenario('sucesso');
    await ok.trocar('regua');
    assert.equal(ok.confirmado.value, 'regua');

    for (const desfecho of ['erro', 'http', 'rede']) {
        const c = cenario(desfecho);
        await c.trocar('regua');
        assert.equal(c.confirmado.value, 'talao', desfecho);
    }
});

test('falha_nao_desfaz_a_escolha_mais_nova_e_nao_conta_como_erro', async () => {
    const c = cenario('erro', 'talao', () => { c.dataset.tema = 'almanaque'; c.emTroca.value = 'almanaque'; });

    const resultado = await c.trocar('regua');

    assert.deepEqual(resultado, { ok: false, substituida: true });
    assert.equal(c.dataset.tema, 'almanaque');
    assert.equal(c.emTroca.value, 'almanaque'); // a escolha mais nova continua em andamento
});

test('nunca_recarrega_a_pagina', async () => {
    let recargas = 0;
    globalThis.window = { location: { reload: () => { recargas++; } } };
    try {
        for (const desfecho of ['sucesso', 'erro']) {
            await cenario(desfecho).trocar('regua');
            await cenario(desfecho).trocar('talao');
        }
    } finally {
        delete globalThis.window;
    }

    assert.equal(recargas, 0);
});

test('carrega_as_fontes_do_tema_novo_em_paralelo_com_a_gravacao', async () => {
    const c = cenario('sucesso');

    await c.trocar('regua');

    assert.deepEqual(c.ordem, ['fontes:regua', 'put']);
});

test('fonte_que_nao_carrega_nao_desfaz_o_tema_e_fica_anotada', async () => {
    const c = cenario('sucesso', 'talao', () => {}, () => 'reserva');

    const resultado = await c.trocar('regua');

    assert.deepEqual(resultado, { ok: true, fontes: 'reserva' });
    assert.equal(c.dataset.tema, 'regua');
});

test('falha_na_gravacao_nao_espera_as_fontes', async () => {
    const c = cenario('http', 'talao', () => {}, () => new Promise(() => {}));

    const resultado = await c.trocar('regua');

    assert.deepEqual(resultado, { ok: false, substituida: false });
});

test('mesmo_tema_grava_e_nao_pede_nada_de_diferente', async () => {
    const c = cenario('sucesso');

    const resultado = await c.trocar('talao');

    assert.deepEqual(resultado, { ok: true, fontes: 'ok' });
    assert.equal(c.dataset.tema, 'talao');
});

/**
 * Cenário da visita do Inertia que muda o usuário (login e saída), ou do Voltar do navegador (`tipo: 'history'`),
 * sem navegador. `confirmadoAntes` é o último tema que o servidor confirmou.
 */
function visita(temaNaPagina, usuario, emTrocaNaVisita = null, { tipo = 'visit', confirmadoAntes = null } = {}) {
    const dataset = { tema: temaNaPagina };
    const fontesPedidas = [];
    const props = [];
    const confirmado = ref(confirmadoAntes);
    sincronizarTemaDaPagina(
        { props: { auth: { user: usuario }, temas: lista } },
        {
            tipo,
            documento: { documentElement: { dataset } },
            fontes: (id) => fontesPedidas.push(id),
            emTroca: ref(emTrocaNaVisita),
            confirmado,
            roteador: { replaceProp: (nome, valor) => props.push([nome, valor]) },
        },
    );

    return { dataset, fontesPedidas, props, confirmado };
}

test('login_aplica_o_tema_do_usuario_e_busca_as_fontes_dele', () => {
    const { dataset, fontesPedidas } = visita('talao', { tema: 'almanaque' });

    assert.equal(dataset.tema, 'almanaque');
    assert.deepEqual(fontesPedidas, ['almanaque']);
});

test('saida_volta_a_tela_publica_para_o_talao_sem_buscar_fontes', () => {
    const { dataset, fontesPedidas } = visita('almanaque', null);

    assert.equal(dataset.tema, 'talao');
    assert.deepEqual(fontesPedidas, []);
});

test('mesmo_tema_na_pagina_nao_faz_nada', () => {
    const { dataset, fontesPedidas } = visita('regua', { tema: 'regua' });

    assert.equal(dataset.tema, 'regua');
    assert.deepEqual(fontesPedidas, []);
});

test('tema_desconhecido_do_usuario_cai_no_primeiro_da_lista', () => {
    assert.equal(visita('almanaque', { tema: 'inexistente' }).dataset.tema, 'talao');
});

test('nao_mexe_durante_uma_troca_em_andamento', () => {
    const { dataset, fontesPedidas } = visita('almanaque', { tema: 'talao' }, 'almanaque');

    assert.equal(dataset.tema, 'almanaque');
    assert.deepEqual(fontesPedidas, []);
});

test('voltar_no_historico_mantem_o_tema_confirmado_e_corrige_a_prop_antiga', () => {
    // A tela já está em Régua (o banco também); a entrada do histórico guardou o Talão.
    const { dataset, fontesPedidas, props, confirmado } = visita('regua', { tema: 'talao' }, null, { tipo: 'history', confirmadoAntes: 'regua' });

    assert.equal(dataset.tema, 'regua');
    assert.deepEqual(props, [['auth.user.tema', 'regua']]);
    assert.deepEqual(fontesPedidas, []);
    assert.equal(confirmado.value, 'regua');
});

test('voltar_no_historico_com_a_prop_igual_ao_confirmado_nao_mexe_em_nada', () => {
    const { dataset, props } = visita('regua', { tema: 'regua' }, null, { tipo: 'history', confirmadoAntes: 'regua' });

    assert.equal(dataset.tema, 'regua');
    assert.deepEqual(props, []);
});

test('visita_nova_vale_mais_que_o_confirmado_porque_vem_do_servidor', () => {
    const { dataset, props, confirmado } = visita('regua', { tema: 'almanaque' }, null, { tipo: 'visit', confirmadoAntes: 'regua' });

    assert.equal(dataset.tema, 'almanaque');
    assert.deepEqual(props, []);
    assert.equal(confirmado.value, 'almanaque');
});

test('saida_esquece_o_tema_confirmado', () => {
    const { confirmado } = visita('regua', null, null, { tipo: 'visit', confirmadoAntes: 'regua' });

    assert.equal(confirmado.value, null);
});

test('voltar_a_uma_tela_sem_usuario_nao_aplica_o_confirmado', () => {
    const { dataset, props } = visita('regua', null, null, { tipo: 'history', confirmadoAntes: 'regua' });

    assert.equal(dataset.tema, 'talao');
    assert.deepEqual(props, []);
});
