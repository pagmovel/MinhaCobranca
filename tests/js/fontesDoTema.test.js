import { test } from 'node:test';
import assert from 'node:assert/strict';
import { carregarFontesDoTema } from '../../resources/js/fontesDoTema.js';

const FAMILIAS = { 'Bricolage Grotesque': [700, 800], Figtree: [400, 500] };

/** Elemento de mentira com o que o código toca: `tagName`, `dataset`. */
const elemento = (tagName) => ({ tagName, dataset: {} });

/**
 * Documento sem navegador. `carregar` decide o que `document.fonts.load` devolve; `elementos` é o que o HTML do servidor
 * traria depois de interpretado.
 */
function cenario({ elementos = [elemento('LINK'), elemento('STYLE')], carregar = async () => [{}], temas = new Map(), dados } = {}) {
    const noHead = [];
    const pedidosDeFonte = [];
    const buscas = [];
    const documento = {
        createElement: () => ({ set innerHTML(html) { this.html = html; }, content: { children: elementos } }),
        head: { append: (no) => noHead.push(no) },
        fonts: { load: (descricao) => { pedidosDeFonte.push(descricao); return carregar(descricao); } },
    };
    const buscar = async (url) => {
        buscas.push(url);

        return dados === undefined ? { dados: { html: '<link><style></style>', familias: FAMILIAS } } : dados;
    };

    return { documento, noHead, pedidosDeFonte, buscas, temas, rodar: (id, extra = {}) => carregarFontesDoTema(id, { buscar, documento, temas, limite: 50, ...extra }) };
}

test('busca_acrescenta_ao_head_e_espera_cada_peso_de_cada_familia', async () => {
    const c = cenario();

    const resultado = await c.rodar('regua');

    assert.equal(resultado, 'ok');
    assert.deepEqual(c.buscas, ['/perfil/tema/regua/fontes']);
    assert.equal(c.noHead.length, 2);
    assert.deepEqual(c.noHead.map((no) => no.dataset.fontesDoTema), ['regua', 'regua']);
    assert.deepEqual(c.pedidosDeFonte, ['700 16px "Bricolage Grotesque"', '800 16px "Bricolage Grotesque"', '400 16px "Figtree"', '500 16px "Figtree"']);
});

test('so_entram_no_head_link_e_style', async () => {
    const c = cenario({ elementos: [elemento('SCRIPT'), elemento('LINK'), elemento('IMG'), elemento('STYLE')] });

    await c.rodar('regua');

    assert.deepEqual(c.noHead.map((no) => no.tagName), ['LINK', 'STYLE']);
});

test('um_tema_so_entra_uma_vez_no_head_e_nao_busca_de_novo', async () => {
    const c = cenario();

    await c.rodar('regua');
    const segunda = await c.rodar('regua');

    assert.equal(segunda, 'ok');
    assert.equal(c.buscas.length, 1);
    assert.equal(c.noHead.length, 2);
    assert.equal(c.pedidosDeFonte.length, 4); // a segunda nem espera: já está confirmado
});

test('tema_da_carga_ja_esta_no_head_e_nao_busca', async () => {
    const c = cenario({ temas: new Map([['talao', { familias: {}, confirmada: true }]]) });

    assert.equal(await c.rodar('talao'), 'ok');
    assert.equal(c.buscas.length, 0);
    assert.equal(c.noHead.length, 0);
});

test('fonte_que_nao_carrega_devolve_reserva_e_a_proxima_tentativa_so_espera_de_novo', async () => {
    let falhar = true;
    const c = cenario({ carregar: async () => { if (falhar) { throw new Error('rede'); } return [{}]; } });

    assert.equal(await c.rodar('regua'), 'reserva');
    falhar = false;
    assert.equal(await c.rodar('regua'), 'ok');
    assert.equal(c.buscas.length, 1, 'não busca nem insere de novo');
    assert.equal(c.noHead.length, 2);
});

test('face_ausente_conta_como_reserva', async () => {
    const c = cenario({ carregar: async () => [] });

    assert.equal(await c.rodar('regua'), 'reserva');
});

test('demora_alem_do_limite_devolve_reserva', async () => {
    const c = cenario({ carregar: () => new Promise(() => {}) });

    assert.equal(await c.rodar('regua'), 'reserva');
});

test('falha_da_busca_devolve_reserva_sem_inserir_nada', async () => {
    for (const dados of [{ mensagem: 'Não foi possível carregar agora.' }, { erros: {} }, null]) {
        const c = cenario({ dados });

        assert.equal(await c.rodar('regua'), 'reserva');
        assert.equal(c.noHead.length, 0);
        assert.equal(c.temas.size, 0, 'sem registrar o tema: a próxima troca tenta de novo');
    }
});

test('nunca_rejeita', async () => {
    const quebrado = { createElement: () => { throw new Error('sem DOM'); } };

    assert.equal(await carregarFontesDoTema('regua', { buscar: async () => ({ dados: { html: '', familias: FAMILIAS } }), documento: quebrado, temas: new Map(), limite: 50 }), 'reserva');
});
