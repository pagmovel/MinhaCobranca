import { test } from 'node:test';
import assert from 'node:assert/strict';
import { avisosDoPainel } from '../../resources/js/avisosDoPainel.js';

const tudoEmDia = { perfilCompleto: true, vencimentosEmDiaNaoUtil: 0, contratosParaRenovar: 0 };

test('sem_pendencia_nao_ha_aviso', () => {
    assert.deepEqual(avisosDoPainel(tudoEmDia), []);
});

test('perfil_incompleto_oferece_completar', () => {
    const [aviso] = avisosDoPainel({ ...tudoEmDia, perfilCompleto: false });

    assert.deepEqual(aviso, { texto: 'Complete seu perfil para emitir recibos.', acao: 'Completar perfil', href: '/perfil' });
});

test('textos_concordam_no_singular_e_no_plural', () => {
    const um = avisosDoPainel({ ...tudoEmDia, vencimentosEmDiaNaoUtil: 1, contratosParaRenovar: 1 });
    const varios = avisosDoPainel({ ...tudoEmDia, vencimentosEmDiaNaoUtil: 3, contratosParaRenovar: 2 });

    assert.equal(um[0].texto, '1 cobrança em aberto vence em sábado, domingo ou feriado.');
    assert.equal(um[1].texto, '1 contrato encerra nos próximos dias.');
    assert.equal(varios[0].texto, '3 cobranças em aberto vencem em sábado, domingo ou feriado.');
    assert.equal(varios[1].texto, '2 contratos encerram nos próximos dias.');
});

test('ordem_e_destinos', () => {
    const avisos = avisosDoPainel({ perfilCompleto: false, vencimentosEmDiaNaoUtil: 1, contratosParaRenovar: 1 });

    assert.deepEqual(avisos.map((aviso) => aviso.href), ['/perfil', '/perfil#feriados', '/contratos']);
});
