import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readdirSync, readFileSync } from 'node:fs';
import { corLegivelSobre, lerTokens, misturarComFundo, razaoDeContraste, tokensDoTema } from '../../resources/js/contraste.js';

const MINIMO = 4.5;

/**
 * Pares [texto, fundo] que precisam de contraste mínimo. Cada ponta é um token (`--color-...`) ou um hex literal.
 * Para ampliar: acrescente o par aqui; ele vale para o Talão (`app.css`) e para todo `resources/css/temas/*.css`.
 */
const PARES = [
    // Texto sobre o fundo da página (papel) e sobre a superfície (cartão, lista, modal, campo).
    ['--color-grafite', '--color-papel'],
    ['--color-grafite', '--color-superficie'],
    ['--color-carbono', '--color-papel'],
    ['--color-carbono', '--color-superficie'],
    ['--color-tinta-verde', '--color-papel'],
    ['--color-tinta-verde', '--color-superficie'],
    ['--color-tinta-vermelha', '--color-papel'],
    ['--color-tinta-vermelha', '--color-superficie'],
    ['--color-tinta-laranja', '--color-papel'],
    ['--color-tinta-laranja', '--color-superficie'],
    // Ação: texto do botão sobre carbono e sobre a cor de cada etapa (pílulas cheias da linha do tempo).
    ['--color-sobre-acao', '--color-carbono'],
    ['--color-sobre-acao', '--color-etapa-enviar'],
    ['--color-sobre-acao', '--color-etapa-aguardando'],
    ['--color-sobre-acao', '--color-etapa-recebida'],
    // `text-white` literal sobre vermelho (contador do menu e `.botao-perigo-confirmar`): o branco não é token.
    ['#FFFFFF', '--color-tinta-vermelha'],
    // Etapas como texto ou contorno sobre a superfície e sobre o fundo da página.
    ['--color-etapa-enviar', '--color-superficie'],
    ['--color-etapa-aguardando', '--color-superficie'],
    ['--color-etapa-recebida', '--color-superficie'],
    ['--color-etapa-enviar', '--color-papel'],
    ['--color-etapa-aguardando', '--color-papel'],
    ['--color-etapa-recebida', '--color-papel'],
    // Menu: texto sobre o fundo e sobre o item ativo/hover.
    ['--color-menu-texto', '--color-menu-fundo'],
    ['--color-menu-texto', '--color-menu-ativo'],
    // Item ativo do menu (e hover da barra superior): sobre o fundo do item ativo e sobre o fundo do menu. No Almanaque
    // o ativo fica sobre um azul escuro: `text-carbono` dava 1,56 (ilegível); por isso o token próprio.
    ['--color-menu-texto-ativo', '--color-menu-ativo'],
    ['--color-menu-texto-ativo', '--color-menu-fundo'],
    // Linha do tempo: texto secundário e dias do eixo sobre a faixa e sobre a sombra de fim de semana e de feriado;
    // `text-white` literal sobre laranja (pílula "vence hoje").
    ['--color-apagado', '--color-papel'],
    ['--color-apagado', '--color-superficie'],
    ['--color-apagado', '--color-faixa'],
    ['--color-carbono', '--color-faixa'], // links dos avisos do topo
    ['--color-apagado', '--color-fim-de-semana'],
    ['--color-apagado', '--color-feriado'],
    ['--color-grafite', '--color-faixa'],
    ['--color-grafite', '--color-fim-de-semana'],
    ['--color-grafite', '--color-feriado'],
    ['#FFFFFF', '--color-tinta-laranja'],
    // Contador do menu: o do Talão e da Régua é vermelho com texto branco; o do Almanaque, dourado com texto azul-marinho.
    ['--color-contador-texto', '--color-contador-fundo'],
];

/**
 * Texto com opacidade (`text-grafite/60`, 12 usos: etiquetas, contagens das abas, paginador, carimbo) sobre o papel e
 * sobre a superfície: [texto, fundo, opacidade]. Só os temas novos (`resources/css/temas`) são cobrados: o do Talão
 * (4,05 e 3,98) é um achado conhecido e a decisão de mexer nele é do Marcos.
 */
const PARES_COM_OPACIDADE = [
    ['--color-grafite', '--color-papel', 0.6],
    ['--color-grafite', '--color-superficie', 0.6],
];
/** Folga sobre o mínimo: o texto com opacidade tem de passar com margem, não raspando. */
const FOLGA_COM_OPACIDADE = 0.25;

/**
 * Pares dos blocos do Painel (PainelMosaico), só dos temas que usam a composição `mosaico`: cada arquivo de tema
 * listado aqui precisa definir estes tokens e passar nestes pares. Os outros temas não são cobrados por eles.
 */
const PARES_DO_MOSAICO = [
    ['--color-grafite', '--color-hoje-fundo'],
    ['--color-tinta-laranja', '--color-hoje-fundo'], // título e número do bloco Hoje
    ['--color-apagado', '--color-hoje-fundo'],
    ['--color-superficie', '--color-tinta-laranja'], // contagem dos atalhos do bloco Hoje
];
/** Arcos do anel sobre o bloco Dinheiro: não são texto, então o mínimo é o de elementos gráficos (WCAG 1.4.11). */
const PARES_GRAFICOS_DO_MOSAICO = [
    ['--color-anel-recebida', '--color-carbono'],
    ['--color-anel-aguardando', '--color-carbono'],
    ['--color-anel-enviar', '--color-carbono'],
];
const MINIMO_GRAFICO = 3;
const TEMAS_COM_MOSAICO = ['almanaque.css'];

const caminho = (relativo) => new URL(`../../${relativo}`, import.meta.url);
const cssBase = readFileSync(caminho('resources/css/app.css'), 'utf8');

function verificarPares(tokens, rotulo, pares = PARES, minimo = MINIMO) {
    for (const [texto, fundo] of pares) {
        const corTexto = texto.startsWith('#') ? texto : tokens[texto];
        const corFundo = fundo.startsWith('#') ? fundo : tokens[fundo];
        assert.ok(corTexto, `${rotulo}: token ${texto} não encontrado`);
        assert.ok(corFundo, `${rotulo}: token ${fundo} não encontrado`);
        const razao = razaoDeContraste(corTexto, corFundo);
        assert.ok(razao >= minimo, `${rotulo}: ${texto} (${corTexto}) sobre ${fundo} (${corFundo}) = ${razao.toFixed(2)}, mínimo ${minimo}`);
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

const pastaDosTemas = caminho('resources/css/temas/');
const arquivosDosTemas = readdirSync(pastaDosTemas).filter((nome) => nome.endsWith('.css'));

test('todos_os_temas_passam_nos_pares_de_texto', () => {
    assert.ok(arquivosDosTemas.length > 0, 'nenhum tema em resources/css/temas');
    for (const arquivo of arquivosDosTemas) {
        verificarPares(tokensDoTema(cssBase, readFileSync(new URL(arquivo, pastaDosTemas), 'utf8')), arquivo);
    }
});

test('mistura_com_o_fundo_e_a_conta_do_navegador', () => {
    assert.equal(misturarComFundo('#000000', '#FFFFFF', 0.6), '#666666');
    assert.equal(misturarComFundo('#102030', '#FFFFFF', 1), '#102030');
    assert.equal(misturarComFundo('#102030', '#FFFFFF', 0), '#FFFFFF');
});

test('temas_novos_passam_no_texto_com_opacidade_com_folga', () => {
    for (const arquivo of arquivosDosTemas) {
        const tokens = tokensDoTema(cssBase, readFileSync(new URL(arquivo, pastaDosTemas), 'utf8'));
        for (const [texto, fundo, opacidade] of PARES_COM_OPACIDADE) {
            const visto = misturarComFundo(tokens[texto], tokens[fundo], opacidade);
            const razao = razaoDeContraste(visto, tokens[fundo]);
            assert.ok(razao >= MINIMO + FOLGA_COM_OPACIDADE, `${arquivo}: ${texto}/${opacidade * 100} sobre ${fundo} (${tokens[fundo]}) = ${razao.toFixed(2)}, mínimo ${MINIMO + FOLGA_COM_OPACIDADE}`);
        }
    }
});

test('temas_com_mosaico_passam_nos_pares_dos_blocos', () => {
    for (const arquivo of TEMAS_COM_MOSAICO) {
        const tokens = tokensDoTema(cssBase, readFileSync(new URL(arquivo, pastaDosTemas), 'utf8'));
        verificarPares(tokens, arquivo, PARES_DO_MOSAICO);
        verificarPares(tokens, arquivo, PARES_GRAFICOS_DO_MOSAICO, MINIMO_GRAFICO);
    }
});

test('cada_tema_define_os_proprios_tokens_de_cor', () => {
    // Token que o tema esquece herda o do Talão em silêncio e destoa da paleta (ex.: menu azul-marinho com texto escuro).
    const tokensDosPares = new Set(PARES.flat().filter((token) => token.startsWith('--')));
    for (const arquivo of arquivosDosTemas) {
        const proprios = lerTokens(readFileSync(new URL(arquivo, pastaDosTemas), 'utf8'));
        const doMosaico = TEMAS_COM_MOSAICO.includes(arquivo) ? [...PARES_DO_MOSAICO, ...PARES_GRAFICOS_DO_MOSAICO].flat().filter((token) => token.startsWith('--')) : [];
        const faltando = [...tokensDosPares, ...doMosaico].filter((token) => !proprios[token]);
        assert.deepEqual(faltando, [], `${arquivo}: tokens de cor herdados do Talão por esquecimento`);
    }
});

test('cor_legivel_sobre_escolhe_a_clara_no_escuro_e_a_escura_no_claro', () => {
    assert.equal(corLegivelSobre('#18214A', '#FFFFFF', '#161B33'), '#FFFFFF'); // menu do Almanaque
    assert.equal(corLegivelSobre('#FFFFFF', '#FFFFFF', '#1F2933'), '#1F2933'); // menu do Talão
    assert.equal(corLegivelSobre('#808080', '#FFFFFF', '#FFFFFF'), '#FFFFFF');
});
