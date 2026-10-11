import { test } from 'node:test';
import assert from 'node:assert/strict';
import { acoesDaCobranca, rotuloDaAcao } from './acoesCobranca.js';

const recibo = { numero: '2026/000001' };
const cobranca = (extra) => ({ situacao: 'a_vencer', enviado: false, editavel: true, recibo, ...extra });

test('em aberto e não enviada: enviar é a principal', () => {
    assert.deepEqual(acoesDaCobranca(cobranca()), {
        principal: 'enviar',
        menu: ['baixa', 'pdf', 'pagamento', 'editar', 'cancelar'],
    });
});

test('em aberto e já enviada: dar baixa é a principal, sem editar', () => {
    assert.deepEqual(acoesDaCobranca(cobranca({ situacao: 'vencida', enviado: true, editavel: false })), {
        principal: 'baixa',
        menu: ['enviar', 'pdf', 'pagamento', 'cancelar'],
    });
});

test('paga: ver o PDF; corrigir e desfazer ficam no menu', () => {
    assert.deepEqual(acoesDaCobranca(cobranca({ situacao: 'paga', enviado: true })), {
        principal: 'pdf',
        menu: ['enviar', 'corrigir', 'desfazer'],
    });
});

test('cancelada: só o PDF; sem recibo, nada', () => {
    assert.deepEqual(acoesDaCobranca(cobranca({ situacao: 'cancelada' })), { principal: 'pdf', menu: [] });
    assert.deepEqual(acoesDaCobranca(cobranca({ situacao: 'cancelada', recibo: null })), { principal: null, menu: [] });
});

test('sem recibo não oferece PDF nem envio', () => {
    assert.deepEqual(acoesDaCobranca(cobranca({ recibo: null })), {
        principal: 'baixa',
        menu: ['pagamento', 'editar', 'cancelar'],
    });
});

test('rótulo do envio depende da situação', () => {
    assert.equal(rotuloDaAcao('enviar', cobranca()), 'Enviar');
    assert.equal(rotuloDaAcao('enviar', cobranca({ enviado: true })), 'Reenviar');
    assert.equal(rotuloDaAcao('enviar', cobranca({ situacao: 'paga' })), 'Enviar recibo pago');
    assert.equal(rotuloDaAcao('cancelar', cobranca()), 'Cancelar cobrança');
});

test('prevista: sem ações (a cobrança ainda não existe)', () => {
    assert.deepEqual(acoesDaCobranca(cobranca({ situacao: 'prevista', recibo: null, editavel: false })), { principal: null, menu: [] });
});
