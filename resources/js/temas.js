// shortcut: 'colunas' (Fluxo e Livro-caixa) ainda não existe; entra aqui junto com a tarefa 4.2.
const PAINEIS = ['empilhado', 'mosaico', 'regua'];

/** Devolve `id` se estiver na lista de temas; senão o primeiro; lista vazia cai no Talão. */
export function temaValido(id, lista) {
    if (lista.some((tema) => tema.id === id)) {
        return id;
    }

    return lista[0]?.id ?? 'talao';
}

/** Composição do Painel de um tema; valor desconhecido usa o `empilhado` (o do Talão). */
export function componenteDoPainel(painel) {
    return PAINEIS.includes(painel) ? painel : 'empilhado';
}
