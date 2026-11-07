function paraCanais(hex) {
    let corpo = hex.trim().replace(/^#/, '');
    if (corpo.length === 3) {
        corpo = [...corpo].map((digito) => digito + digito).join('');
    }
    if (!/^[0-9a-f]{6}$/i.test(corpo)) {
        throw new Error(`Cor hexadecimal inválida: ${hex}`);
    }

    return [0, 2, 4].map((posicao) => parseInt(corpo.slice(posicao, posicao + 2), 16) / 255);
}

function luminancia(hex) {
    const [r, g, b] = paraCanais(hex).map((canal) => (canal <= 0.03928 ? canal / 12.92 : ((canal + 0.055) / 1.055) ** 2.4));

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

/** Razão de contraste WCAG 2.x entre duas cores hexadecimais (3 ou 6 dígitos, com `#`): de 1 a 21. */
export function razaoDeContraste(corA, corB) {
    const [maior, menor] = [luminancia(corA), luminancia(corB)].sort((x, y) => y - x);

    return (maior + 0.05) / (menor + 0.05);
}

/**
 * Cor que o olho vê quando `texto` é pintado com `opacidade` (0 a 1) sobre `fundo`, como o `text-grafite/60` do Tailwind:
 * mistura canal a canal em sRGB, a mesma conta de composição do navegador. Devolve hexadecimal de 6 dígitos com `#`.
 */
export function misturarComFundo(texto, fundo, opacidade) {
    const [a, b] = [paraCanais(texto), paraCanais(fundo)];

    return `#${a.map((canal, indice) => Math.round((canal * opacidade + b[indice] * (1 - opacidade)) * 255).toString(16).padStart(2, '0')).join('').toUpperCase()}`;
}

/** A cor, entre `clara` e `escura`, que se lê melhor sobre `fundo` (a de maior razão de contraste; empate fica com a clara). */
export function corLegivelSobre(fundo, clara, escura) {
    return razaoDeContraste(fundo, clara) >= razaoDeContraste(fundo, escura) ? clara : escura;
}

/** Extrai `--nome: #hex;` de qualquer trecho de CSS; ignora comentários e valores que não sejam hex (var(), oklch...). */
export function lerTokens(css) {
    const tokens = {};
    const semComentarios = css.replace(/\/\*[\s\S]*?\*\//g, '');
    for (const [, nome, valor] of semComentarios.matchAll(/(--[\w-]+)\s*:\s*(#(?:[0-9a-fA-F]{6}|[0-9a-fA-F]{3}))\s*;/g)) {
        tokens[nome] = valor;
    }

    return tokens;
}

/** Tokens de cor de um tema: os da base (`app.css`) sobrescritos pelos do arquivo do tema. */
export function tokensDoTema(cssBase, cssTema) {
    return { ...lerTokens(cssBase), ...lerTokens(cssTema) };
}
