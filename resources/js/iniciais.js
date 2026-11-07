/**
 * Iniciais do nome para o avatar (primeira e última palavra; uma palavra só dá uma inicial).
 * Sem nome devolve "?", para o avatar nunca ficar vazio.
 */
export function iniciaisDoNome(nome) {
    const palavras = String(nome ?? '').trim().split(/\s+/).filter(Boolean);
    if (palavras.length === 0) {
        return '?';
    }
    const primeira = palavras[0];
    const ultima = palavras.length > 1 ? palavras[palavras.length - 1] : '';

    return (primeira[0] + (ultima[0] ?? '')).toLocaleUpperCase('pt-BR');
}
