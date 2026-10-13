import { test } from 'node:test';
import assert from 'node:assert/strict';
import { itemBloqueado } from './progresso.js';

const progresso = { perfil: false, catalogo: false, liberado: false, pendencias: [] };

test('clientes e produtos bloqueiam só quando o bloco perfil está incompleto', () => {
    assert.equal(itemBloqueado('/clientes', progresso), true);
    assert.equal(itemBloqueado('/produtos-servicos', progresso), true);
    assert.equal(itemBloqueado('/clientes', { ...progresso, perfil: true }), false);
});

test('contratos e cobranças bloqueiam só quando o bloco liberado está incompleto', () => {
    assert.equal(itemBloqueado('/contratos', progresso), true);
    assert.equal(itemBloqueado('/cobrancas', { ...progresso, perfil: true, catalogo: true, liberado: true }), false);
});

test('painel, perfil, relatórios, auditoria e admin nunca bloqueiam', () => {
    for (const href of ['/painel', '/perfil', '/relatorios', '/auditoria', '/admin/usuarios']) {
        assert.equal(itemBloqueado(href, progresso), false);
    }
});

test('sem usuário logado (progresso nulo) nada bloqueia — a tela de login não usa isso', () => {
    assert.equal(itemBloqueado('/clientes', null), false);
});
