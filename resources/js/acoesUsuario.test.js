import { test } from 'node:test';
import assert from 'node:assert/strict';
import { acoesDoUsuario } from './acoesUsuario.js';

test('ação principal pela situação; "Bloquear" no menu, nunca para a própria conta nem para quem já está bloqueado', () => {
    assert.deepEqual(acoesDoUsuario({ status: 'pendente', eu: false }), { principal: 'aprovar', menu: ['bloquear'] });
    assert.deepEqual(acoesDoUsuario({ status: 'aprovado', eu: false }), { principal: null, menu: ['bloquear'] });
    assert.deepEqual(acoesDoUsuario({ status: 'bloqueado', eu: false }), { principal: 'reativar', menu: [] });
    assert.deepEqual(acoesDoUsuario({ status: 'aprovado', eu: true }), { principal: null, menu: [] });
});
