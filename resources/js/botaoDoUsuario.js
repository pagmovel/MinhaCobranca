/**
 * O botão do nome que está na tela agora. Cada menu tem o seu (e a casca lateral tem dois, um no cabeçalho de celular
 * e outro no rodapé do menu, um deles escondido pelo CSS), todos marcados com `data-botao-do-usuario`.
 */
export function botaoDoUsuarioVisivel(documento = document) {
    return [...documento.querySelectorAll('[data-botao-do-usuario]')].find((botao) => botao.getClientRects().length > 0) ?? null;
}
