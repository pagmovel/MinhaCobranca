/**
 * Funções puras da linha do tempo do mês. Ponto vindo de `/painel/mes`:
 * `{ id, cliente, valor_centavos, vencimento_dia, etapa, situacao, pago_dia, pago_centavos }`.
 */

/** Dia em que o ponto aparece: o do pagamento se já foi recebida, senão o do vencimento. */
export function diaDoPonto(ponto) {
    return ponto.etapa === 'recebida' ? ponto.pago_dia : ponto.vencimento_dia;
}

/** No mesmo dia os pontos sobem um nível, pela ordem do `id`; a lista devolvida mantém a ordem de entrada. */
export function empilharPontos(pontos) {
    const niveis = new Map();
    const usadosPorDia = new Map();
    for (const ponto of [...pontos].sort((a, b) => a.id - b.id)) {
        const dia = diaDoPonto(ponto);
        const nivel = usadosPorDia.get(dia) ?? 0;
        niveis.set(ponto, nivel);
        usadosPorDia.set(dia, nivel + 1);
    }

    return pontos.map((ponto) => ({ ponto, nivel: niveis.get(ponto) }));
}

/** Régua de dinheiro: soma acumulada do que foi pago, por dia. Posição 0 = dia 1; só entram as recebidas. */
export function acumuladoPorDia(pontos, dias) {
    const porDia = new Array(dias).fill(0);
    for (const ponto of pontos) {
        if (ponto.etapa === 'recebida' && ponto.pago_dia >= 1 && ponto.pago_dia <= dias) {
            porDia[ponto.pago_dia - 1] += ponto.pago_centavos ?? 0;
        }
    }
    let acumulado = 0;

    return porDia.map((valor) => (acumulado += valor));
}

/** Posição (0 a 1) do centro da célula do dia no eixo. */
export function posicaoDoDia(dia, dias) {
    return (dia - 0.5) / dias;
}
