import { test } from 'node:test';
import assert from 'node:assert/strict';
import { consultar, preencherVazios } from './consultas.js';

test('preenche só campos vazios e só com o que veio preenchido', () => {
    const form = { nome: '', logradouro: 'Rua já digitada', cidade: '  ', uf: '' };

    const total = preencherVazios(
        form,
        { nome: 'BANCO DO BRASIL SA', logradouro: 'SAUN QUADRA 5', cidade: 'BRASILIA', uf: '' },
        { nome: 'nome', logradouro: 'logradouro', cidade: 'cidade', uf: 'uf' },
    );

    assert.equal(total, 2);
    assert.deepEqual(form, { nome: 'BANCO DO BRASIL SA', logradouro: 'Rua já digitada', cidade: 'BRASILIA', uf: '' });
});

test('campo de destino com outro nome (o perfil usa name)', () => {
    const form = { name: '' };

    preencherVazios(form, { nome: 'EMPRESA X' }, { nome: 'name' });

    assert.equal(form.name, 'EMPRESA X');
});

test('consultar nunca lança: falha vira mensagem', async () => {
    const falha = 'Não foi possível consultar agora; preencha manualmente.';

    globalThis.fetch = async () => { throw new TypeError('sem rede'); };
    assert.deepEqual(await consultar('/x'), { mensagem: falha });

    globalThis.fetch = async () => new Response(JSON.stringify({ mensagem: 'Informe um CNPJ válido para buscar os dados.' }), { status: 422 });
    assert.deepEqual(await consultar('/x'), { mensagem: 'Informe um CNPJ válido para buscar os dados.' });

    globalThis.fetch = async () => new Response('<html>muitas</html>', { status: 429 });
    assert.deepEqual(await consultar('/x'), { mensagem: falha });

    globalThis.fetch = async () => new Response(JSON.stringify({ cidade: 'São Paulo' }), { status: 200 });
    assert.deepEqual(await consultar('/x'), { dados: { cidade: 'São Paulo' } });
});
