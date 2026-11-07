/**
 * Próximo índice da armadilha de foco (Tab / Shift+Tab dentro de um diálogo): circular.
 * Lista vazia devolve -1; índice desconhecido (foco fora da lista) vai ao primeiro, ou ao último com `voltar`.
 */
export function proximoDaArmadilha(total, indice, voltar) {
    if (total <= 0) {
        return -1;
    }
    if (indice < 0 || indice >= total) {
        return voltar ? total - 1 : 0;
    }

    return (indice + (voltar ? -1 : 1) + total) % total;
}

/**
 * O Tab (ou Shift+Tab) deste índice tiraria o foco do diálogo? Só nesse caso a armadilha intervém: no meio da
 * lista vale a ordem natural do navegador (grupos de rádio, tabindex), que a ordem do DOM não reproduz.
 * Foco fora da lista (índice desconhecido) também precisa voltar. Lista vazia não prende nada.
 */
export function saindoDaArmadilha(total, indice, voltar) {
    if (total <= 0) {
        return false;
    }
    if (indice < 0 || indice >= total) {
        return true;
    }

    return voltar ? indice === 0 : indice === total - 1;
}
