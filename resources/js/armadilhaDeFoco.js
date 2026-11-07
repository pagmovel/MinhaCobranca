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
