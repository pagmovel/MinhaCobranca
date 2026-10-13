const BLOCOS_POR_HREF = {
    '/clientes': 'perfil',
    '/produtos-servicos': 'perfil',
    '/contratos': 'liberado',
    '/cobrancas': 'liberado',
};

/** Só os 4 itens de cadastro bloqueiam, e só pelo bloco que a Fase 25 exige para cada um. */
export function itemBloqueado(href, progresso) {
    if (!progresso) {
        return false;
    }
    const bloco = BLOCOS_POR_HREF[href];

    return bloco ? !progresso[bloco] : false;
}
