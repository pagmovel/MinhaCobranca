import { test } from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { lerTokens, razaoDeContraste, tokensDoTema } from '../../resources/js/contraste.js';

const MINIMO = 4.5;

/**
 * Pares [texto, fundo] que precisam de contraste mínimo. Cada ponta é um token (`--color-...`) ou um hex literal.
 * Para ampliar: acrescente o par aqui; ele vale para o Talão (`app.css`) e para todo `resources/css/temas/*.css`.
 */
const PARES = [
    ['--color-grafite', '--color-papel'],
    ['--color-carbono', '#FFFFFF'],
    ['--color-tinta-verde', '#FFFFFF'],
    ['--color-tinta-vermelha', '#FFFFFF'],
    ['--color-tinta-laranja', '#FFFFFF'],
];

const caminho = (relativo) => new URL(`../../${relativo}`, import.meta.url);
const cssBase = readFileSync(caminho('resources/css/app.css'), 'utf8');

function verificarPares(tokens, rotulo) {
    for (const [texto, fundo] of PARES) {
        const corTexto = texto.startsWith('#') ? texto : tokens[texto];
        const corFundo = fundo.startsWith('#') ? fundo : tokens[fundo];
        assert.ok(corTexto, `${rotulo}: token ${texto} não encontrado`);
        assert.ok(corFundo, `${rotulo}: token ${fundo} não encontrado`);
        const razao = razaoDeContraste(corTexto, corFundo);
        assert.ok(razao >= MINIMO, `${rotulo}: ${texto} (${corTexto}) sobre ${fundo} (${corFundo}) = ${razao.toFixed(2)}, mínimo ${MINIMO}`);
    }
}

test('preto_sobre_branco_e_21', () => {
    assert.equal(razaoDeContraste('#000000', '#FFFFFF'), 21);
    assert.equal(razaoDeContraste('#FFFFFF', '#000000'), 21);
});

test('cores_iguais_dao_1', () => {
    assert.equal(razaoDeContraste('#2F3C9E', '#2F3C9E'), 1);
});

test('aceita_hex_curto', () => {
    assert.equal(razaoDeContraste('#000', '#fff'), 21);
    assert.equal(razaoDeContraste('#abc', '#aabbcc'), 1);
    assert.throws(() => razaoDeContraste('azul', '#fff'), /inválida/);
});

test('le_tokens_hex_e_ignora_o_resto', () => {
    const tokens = lerTokens(`
        /* --comentado: #123456; */
        html[data-tema='x'] {
            --color-a: #AABBCC;
            --color-b: #abc;
            --color-c: var(--color-a);
            --color-d: oklch(50% 0.1 200);
            --color-e: #abcd;
            --raio-controle: 4px;
        }
    `);

    assert.deepEqual(tokens, { '--color-a': '#AABBCC', '--color-b': '#abc' });
});

test('tema_sobrescreve_a_base', () => {
    const base = ':root { --color-a: #111111; --color-b: #222222; }';
    const tema = "html[data-tema='x'] { --color-b: #333333; --color-c: #444444; }";

    assert.deepEqual(tokensDoTema(base, tema), { '--color-a': '#111111', '--color-b': '#333333', '--color-c': '#444444' });
});

test('talao_passa_nos_pares_de_texto', () => {
    verificarPares(lerTokens(cssBase), 'talao');
});

test('todos_os_temas_passam_nos_pares_de_texto', () => {
    const pasta = caminho('resources/css/temas/');
    if (!existsSync(pasta)) {
        return; // A pasta nasce com o primeiro tema (tarefa 3.2 em diante).
    }
    for (const arquivo of readdirSync(pasta).filter((nome) => nome.endsWith('.css'))) {
        verificarPares(tokensDoTema(cssBase, readFileSync(new URL(arquivo, pasta), 'utf8')), arquivo);
    }
});
