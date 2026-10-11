/** Páginas do paginador: primeira, última e a atual ±1, com '…' onde pula. */
export function paginasVisiveis(atual, ultima) {
    const paginas = [...new Set([1, atual - 1, atual, atual + 1, ultima])]
        .filter((pagina) => pagina >= 1 && pagina <= ultima)
        .sort((a, b) => a - b);

    return paginas.flatMap((pagina, indice) => (indice > 0 && pagina - paginas[indice - 1] > 1 ? ['…', pagina] : [pagina]));
}

/** Chave do cache da lista: todos os filtros menos a página (o "por página" entra: muda o fatiamento). */
export function chaveDoFiltro(filtro) {
    const { pagina, ...resto } = filtro;

    return JSON.stringify(Object.keys(resto).sort().map((chave) => [chave, resto[chave]]));
}

/** Algum filtro difere do padrão? (mostra "Limpar filtros"). Compara como texto: o id vem número ou texto. */
export function filtrosAlterados(filtro, padroes) {
    return Object.entries(padroes).some(([campo, valor]) => String(filtro[campo] ?? '') !== String(valor ?? ''));
}

/** Bloco que o servidor mandou → linhas por página ({ 5: [...], 6: [...] }). */
export function fatiarBloco(linhas, inicio, porPagina) {
    const paginas = {};
    for (let indice = 0; indice < linhas.length; indice += porPagina) {
        paginas[inicio + indice / porPagina] = linhas.slice(indice, indice + porPagina);
    }

    return paginas;
}

/** O paginador do painel (só embaixo) aparece se o total passa da menor opção: quem escolheu 25 consegue voltar a 10. */
export function paginadorVisivel(total, opcoes) {
    return total > Math.min(...opcoes);
}
