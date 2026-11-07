/**
 * Partes do mês para o anel do bloco Dinheiro. Valores em centavos; a base dos porcentos é a soma das três partes.
 * Porcentos inteiros pelo método do maior resto (somam 100; empate pela ordem recebida, aguardando, enviar).
 * Soma zero devolve lista vazia (nada a desenhar).
 */
export function fatiasDoMes({ recebido, aguardando, paraEnviar }) {
    const chaves = ['recebida', 'aguardando', 'enviar'];
    const centavos = [recebido, aguardando, paraEnviar];
    const total = centavos.reduce((soma, valor) => soma + valor, 0);
    if (total <= 0) {
        return [];
    }

    // Conta em inteiros (centavos * 100) para o resto nunca empatar por erro de ponto flutuante.
    const porcentos = centavos.map((valor) => Math.floor((valor * 100) / total));
    const restos = centavos.map((valor, indice) => ({ indice, resto: (valor * 100) % total }));
    restos.sort((a, b) => b.resto - a.resto || a.indice - b.indice);
    const faltam = 100 - porcentos.reduce((soma, porcento) => soma + porcento, 0);
    for (let i = 0; i < faltam; i++) {
        porcentos[restos[i].indice]++;
    }

    return chaves.map((chave, indice) => ({ chave, centavos: centavos[indice], porcento: porcentos[indice] }));
}
