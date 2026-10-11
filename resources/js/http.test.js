import { test } from 'node:test';
import assert from 'node:assert/strict';
import { buscarJson, enviarJson } from './http.js';

const FALHA = 'Não foi possível salvar agora. Tente de novo.';

test('envia JSON com o token CSRF do cookie e devolve os dados', async () => {
    globalThis.document = { cookie: 'outro=1; XSRF-TOKEN=abc%3D%3D' };
    let pedido;
    globalThis.fetch = async (url, opcoes) => {
        pedido = { url, ...opcoes };
        return new Response(JSON.stringify({ id: 7, nome: 'Cliente Novo' }), { status: 201 });
    };

    assert.deepEqual(await enviarJson('/clientes', { nome: 'Cliente Novo' }), { dados: { id: 7, nome: 'Cliente Novo' } });
    assert.equal(pedido.method, 'POST');
    assert.equal(pedido.headers['X-XSRF-TOKEN'], 'abc==');
    assert.equal(pedido.headers.Accept, 'application/json');
    assert.equal(pedido.body, JSON.stringify({ nome: 'Cliente Novo' }));
});

test('422 vira erros por campo (a primeira mensagem de cada um)', async () => {
    globalThis.fetch = async () => new Response(JSON.stringify({
        message: 'Dados inválidos.',
        errors: { nome: ['Preencha o campo nome.', 'outra'], 'itens.0.preco': ['Informe um valor válido.'] },
    }), { status: 422 });

    assert.deepEqual(await enviarJson('/x', {}), { erros: { nome: 'Preencha o campo nome.', 'itens.0.preco': 'Informe um valor válido.' } });
});

test('falha de rede ou resposta sem JSON vira mensagem; nunca lança', async () => {
    globalThis.fetch = async () => { throw new TypeError('sem rede'); };
    assert.deepEqual(await enviarJson('/x', {}), { mensagem: FALHA });

    globalThis.fetch = async () => new Response('<html>419</html>', { status: 419 });
    assert.deepEqual(await enviarJson('/x', {}), { mensagem: 'A sessão expirou. Recarregue a página e tente de novo.' });

    globalThis.fetch = async () => new Response('<html>erro</html>', { status: 500 });
    assert.deepEqual(await enviarJson('/x', {}), { mensagem: FALHA });

    // Redirecionamento seguido pelo fetch (ex.: conta bloqueada → /entrar) chega como 200 com HTML.
    globalThis.fetch = async () => new Response('<html>entrar</html>', { status: 200 });
    assert.deepEqual(await enviarJson('/x', {}), { mensagem: FALHA });
});

test('buscarJson faz GET sem corpo e avisa a falha como "carregar"', async () => {
    let pedido;
    globalThis.fetch = async (url, opcoes) => {
        pedido = { url, ...opcoes };
        return new Response(JSON.stringify({ linhas: [] }), { status: 200 });
    };

    assert.deepEqual(await buscarJson('/cobrancas/dados?pagina=2'), { dados: { linhas: [] } });
    assert.equal(pedido.method, 'GET');
    assert.equal(pedido.body, undefined);

    globalThis.fetch = async () => { throw new TypeError('sem rede'); };
    assert.deepEqual(await buscarJson('/x'), { mensagem: 'Não foi possível carregar agora. Tente de novo.' });
});
