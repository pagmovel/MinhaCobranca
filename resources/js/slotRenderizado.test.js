import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createCommentVNode, createVNode, Fragment, h } from 'vue';
import { slotTemConteudo } from './slotRenderizado.js';

test('sem slot (o pai não passou) não tem conteúdo', () => {
    assert.equal(slotTemConteudo(undefined), false);
    assert.equal(slotTemConteudo([]), false);
});

test('o slot com `<p v-if>` falso rende só um comentário e não tem conteúdo', () => {
    assert.equal(slotTemConteudo([createCommentVNode('v-if', true)]), false);
});

test('o slot com um elemento tem conteúdo', () => {
    assert.equal(slotTemConteudo([h('p', { id: 'multa-calculado' }, '= R$ 4,20')]), true);
});

test('Fragment vazio ou só com comentário não tem conteúdo; com elemento, tem', () => {
    assert.equal(slotTemConteudo([createVNode(Fragment, null, [])]), false);
    assert.equal(slotTemConteudo([createVNode(Fragment, null, [createCommentVNode('v-if', true)])]), false);
    assert.equal(slotTemConteudo([createVNode(Fragment, null, [h('p', '= R$ 4,20')])]), true);
});
