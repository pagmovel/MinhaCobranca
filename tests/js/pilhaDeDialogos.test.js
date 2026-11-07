import { test } from 'node:test';
import assert from 'node:assert/strict';
import { liberarRolagem, quemFechaComEsc, travarRolagem } from '../../resources/js/pilhaDeDialogos.js';

const corpoFalso = () => ({ style: { overflow: '' } });

test('com_popup_aberto_so_o_popup_reage_ao_esc', () => {
    assert.equal(quemFechaComEsc(true, true), 'popup');
    assert.equal(quemFechaComEsc(false, true), 'popup');
});

test('sem_popup_o_painel_reage_e_sem_nenhum_ninguem', () => {
    assert.equal(quemFechaComEsc(true, false), 'painel');
    assert.equal(quemFechaComEsc(false, false), null);
});

test('fechar_o_popup_nao_destrava_o_corpo_enquanto_o_painel_segue_aberto', () => {
    const corpo = corpoFalso();
    const painel = Symbol('painel');
    const popup = Symbol('popup');

    travarRolagem(painel, corpo);
    travarRolagem(popup, corpo);
    liberarRolagem(popup, corpo);
    assert.equal(corpo.style.overflow, 'hidden');

    liberarRolagem(painel, corpo);
    assert.equal(corpo.style.overflow, '');
});

test('a_trava_tambem_segura_quando_o_painel_libera_antes_do_popup', () => {
    const corpo = corpoFalso();
    const painel = Symbol('painel');
    const popup = Symbol('popup');

    travarRolagem(painel, corpo);
    travarRolagem(popup, corpo);
    liberarRolagem(painel, corpo);
    assert.equal(corpo.style.overflow, 'hidden');

    liberarRolagem(popup, corpo);
    assert.equal(corpo.style.overflow, '');
});

test('liberar_quem_nunca_travou_nao_apaga_a_trava_de_outro', () => {
    // O Modal nasce fechado (watch com immediate) e libera sem ter travado: não pode destravar um modal já aberto.
    const corpo = corpoFalso();
    const aberto = Symbol('aberto');
    const nasceuFechado = Symbol('nasceu-fechado');

    travarRolagem(aberto, corpo);
    liberarRolagem(nasceuFechado, corpo);
    assert.equal(corpo.style.overflow, 'hidden');

    liberarRolagem(aberto, corpo); // a contagem é do módulo: não deixar dono pendurado para o teste seguinte
    assert.equal(corpo.style.overflow, '');
});

test('travar_duas_vezes_o_mesmo_dono_e_liberar_uma_vez_destrava', () => {
    const corpo = corpoFalso();
    const dono = Symbol('dono');

    travarRolagem(dono, corpo);
    travarRolagem(dono, corpo);
    liberarRolagem(dono, corpo);
    assert.equal(corpo.style.overflow, '');
});
