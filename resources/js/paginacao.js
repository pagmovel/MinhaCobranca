/** Páginas do paginador: primeira, última e a atual ±1, com '…' onde pula. */
export function paginasVisiveis(atual, ultima) {
    const paginas = [...new Set([1, atual - 1, atual, atual + 1, ultima])]
        .filter((pagina) => pagina >= 1 && pagina <= ultima)
        .sort((a, b) => a - b);

    return paginas.flatMap((pagina, indice) => (indice > 0 && pagina - paginas[indice - 1] > 1 ? ['…', pagina] : [pagina]));
}

/** Chave do cache da lista: os filtros que mudam o resultado e o "por página" (a página não entra). */
export function chaveDoFiltro({ mes, ate, status, cliente_id: clienteId, canceladas, por_pagina: porPagina }) {
    return JSON.stringify([mes, ate, status, clienteId, Boolean(canceladas), Number(porPagina)]);
}

/** Bloco que o servidor mandou → linhas por página ({ 5: [...], 6: [...] }). */
export function fatiarBloco(linhas, inicio, porPagina) {
    const paginas = {};
    for (let indice = 0; indice < linhas.length; indice += porPagina) {
        paginas[inicio + indice / porPagina] = linhas.slice(indice, indice + porPagina);
    }

    return paginas;
}
