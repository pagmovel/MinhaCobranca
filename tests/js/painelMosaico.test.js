import { test } from 'node:test';
import assert from 'node:assert/strict';
import { fatiasDoMes } from '../../resources/js/fatiasDoMes.js';
import { abaPorTecla, arcosDoAnel, esvaziou, haQuantosDias, notaDoDia, textoDoAnel } from '../../resources/js/painelMosaico.js';

test('arcos_somam_o_anel_inteiro_menos_as_folgas', () => {
    const arcos = arcosDoAnel(fatiasDoMes({ recebido: 500, aguardando: 300, paraEnviar: 200 }));

    assert.deepEqual(arcos.map((arco) => arco.chave), ['recebida', 'aguardando', 'enviar']);
    // 50 + 30 + 20 por cento, cada arco perde 0,9 de folga.
    assert.deepEqual(arcos.map((arco) => Number(arco.comprimento.toFixed(2))), [49.1, 29.1, 19.1]);
    // Cada arco começa onde o anterior terminou, deslocado meia folga.
    assert.deepEqual(arcos.map((arco) => Number(arco.deslocamento.toFixed(2))), [-0.45, -50.45, -80.45]);
});

test('fatia_zerada_nao_desenha_arco', () => {
    const arcos = arcosDoAnel(fatiasDoMes({ recebido: 100, aguardando: 0, paraEnviar: 100 }));

    assert.equal(arcos[1].comprimento, 0);
    assert.equal(arcos[2].deslocamento, -(50 + 0.45));
});

test('fatia_unica_com_cem_por_cento_fecha_o_circulo', () => {
    const arcos = arcosDoAnel(fatiasDoMes({ recebido: 0, aguardando: 700, paraEnviar: 0 }));

    assert.deepEqual(arcos.map((arco) => arco.comprimento), [0, 100, 0]);
    assert.equal(arcos[1].deslocamento, -0);
});

test('mes_sem_nada_nao_tem_arcos', () => {
    assert.deepEqual(arcosDoAnel(fatiasDoMes({ recebido: 0, aguardando: 0, paraEnviar: 0 })), []);
});

test('texto_do_anel_diz_o_percentual_e_os_valores', () => {
    assert.equal(
        textoDoAnel({ porcento: 62, recebidoCentavos: 100000, previstoCentavos: 160000, nomeDoMes: 'novembro' }),
        '62% recebido: R$ 1.000,00 de R$ 1.600,00 previstos para novembro',
    );
    assert.equal(textoDoAnel({ porcento: 0, recebidoCentavos: 0, previstoCentavos: 0, nomeDoMes: 'novembro' }), 'Nada previsto para novembro ainda');
});

test('setas_dao_a_volta_e_home_end_vao_as_pontas', () => {
    assert.equal(abaPorTecla('ArrowRight', 'enviar'), 'aguardando');
    assert.equal(abaPorTecla('ArrowRight', 'recebida'), 'enviar');
    assert.equal(abaPorTecla('ArrowLeft', 'enviar'), 'recebida');
    assert.equal(abaPorTecla('Home', 'recebida'), 'enviar');
    assert.equal(abaPorTecla('End', 'enviar'), 'recebida');
});

test('outra_tecla_nao_muda_a_aba', () => {
    assert.equal(abaPorTecla('Enter', 'enviar'), null);
    assert.equal(abaPorTecla('a', 'aguardando'), null);
});

test('so_esvazia_quem_tinha_cobranca_e_ja_foi_contado', () => {
    assert.equal(esvaziou(3, 0), true);
    assert.equal(esvaziou(1, 0), true);
    assert.equal(esvaziou(null, 0), false); // a primeira contagem não é esvaziar
    assert.equal(esvaziou(0, 0), false);
    assert.equal(esvaziou(3, 2), false);
});

test('nota_do_dia_usa_o_nome_da_semana_e_o_mes_numerico', () => {
    assert.equal(notaDoDia({ mes: '2026-11', hoje: 6 }), 'sexta, 6/11');
    assert.equal(notaDoDia({ mes: '2026-11', hoje: 1 }), 'domingo, 1/11');
    assert.equal(notaDoDia({ mes: '2026-02', hoje: 28 }), 'sábado, 28/2');
});

test('ha_quantos_dias_concorda_no_plural', () => {
    assert.equal(haQuantosDias(1), 'há 1 dia');
    assert.equal(haQuantosDias(12), 'há 12 dias');
});
