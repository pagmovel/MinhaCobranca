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

export const SEMANA = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];
const plural = (quantidade, singular, varios) => `${quantidade} ${quantidade === 1 ? singular : varios}`;

/** Dia da semana (0 = domingo) do `dia` do mês `mes` (`AAAA-MM`), sem depender do fuso do aparelho. */
export function diaDaSemana(mes, dia) {
    const [ano, numero] = mes.split('-').map(Number);

    return new Date(Date.UTC(ano, numero - 1, dia)).getUTCDay();
}

/** Valor curto para caber na pílula (~38 px): 650, 3500, 12k, 1,2M. O valor exato fica no rótulo e na dica. */
export function valorCompacto(centavos) {
    const reais = Math.round(centavos / 100);
    if (reais < 10000) {
        return String(reais);
    }
    const milhares = Math.round(reais / 1000);

    return milhares < 1000 ? `${milhares}k` : `${(reais / 1000000).toFixed(1).replace('.', ',')}M`;
}

/** Aparência do ponto: a situação (vencida, vence hoje) vale mais que a etapa; recebida nunca é vencida. */
export function tipoDoPonto(ponto) {
    if (ponto.etapa === 'recebida') {
        return 'recebida';
    }
    if (ponto.situacao === 'vencida') {
        return 'vencida';
    }

    return ponto.situacao === 'vence_hoje' ? 'hoje' : ponto.etapa;
}

/** Quantos níveis cabem na pilha de um dia, de cada lado do eixo; acima disso o último nível vira a pílula "+N". */
export const NIVEIS_POR_DIA = 6;

/** Pilha com no máximo `NIVEIS_POR_DIA` níveis: passou do teto, ficam os primeiros e o resto vira o "+N" (nunca "+1"). */
function limitarPilha(itens) {
    if (itens.length <= NIVEIS_POR_DIA) {
        return { visiveis: itens, mais: 0 };
    }
    const visiveis = itens.slice(0, NIVEIS_POR_DIA - 1);

    return { visiveis, mais: itens.length - visiveis.length };
}

/**
 * Uma coluna por dia: vencimentos (acima do eixo) e pagamentos (abaixo), cada lista já com o nível de empilhamento e
 * limitada a `NIVEIS_POR_DIA` (`maisVencimentos` e `maisPagamentos` dizem quantos ficaram de fora, para a pílula "+N").
 * A ordem das colunas, e dentro delas vencimentos antes de pagamentos, é a ordem de tabulação dos pontos. O teto vale só
 * para o desenho: a régua de dinheiro (`acumuladoPorDia`) continua somando todos os pontos.
 */
export function colunasDoMes(pontos, dias) {
    const colunas = Array.from({ length: dias }, (_, indice) => ({ dia: indice + 1, vencimentos: [], pagamentos: [], maisVencimentos: 0, maisPagamentos: 0 }));
    for (const item of empilharPontos(pontos.filter((ponto) => ponto.etapa !== 'recebida'))) {
        colunas[diaDoPonto(item.ponto) - 1]?.vencimentos.push(item);
    }
    for (const item of empilharPontos(pontos.filter((ponto) => ponto.etapa === 'recebida'))) {
        colunas[diaDoPonto(item.ponto) - 1]?.pagamentos.push(item);
    }
    for (const coluna of colunas) {
        const vencimentos = limitarPilha(coluna.vencimentos.sort((a, b) => a.nivel - b.nivel));
        const pagamentos = limitarPilha(coluna.pagamentos.sort((a, b) => a.nivel - b.nivel));
        Object.assign(coluna, {
            vencimentos: vencimentos.visiveis,
            maisVencimentos: vencimentos.mais,
            pagamentos: pagamentos.visiveis,
            maisPagamentos: pagamentos.mais,
        });
    }

    return colunas;
}

/** Ids dos pontos na ordem em que o Tab os percorre (e em que as setas andam). */
export function ordemDeTabulacao(colunas) {
    return colunas.flatMap((coluna) => [...coluna.vencimentos, ...coluna.pagamentos].map(({ ponto }) => ponto.id));
}

/** Vizinho de `id` na ordem (`passo` -1 ou 1), parando nas pontas; id desconhecido devolve o primeiro. */
export function vizinhoDoPonto(ordem, id, passo) {
    const indice = ordem.indexOf(id);

    return indice < 0 ? ordem[0] : ordem[Math.min(ordem.length - 1, Math.max(0, indice + passo))];
}

/** Soma acumulada do previsto por dia: o recebido mais o que falta, que entra no vencimento (atrasadas e de hoje entram em `hoje`). */
export function previstoPorDia(pontos, dias, hoje) {
    const entradas = new Array(dias).fill(0);
    for (const ponto of pontos) {
        if (ponto.etapa !== 'recebida') {
            entradas[Math.min(dias, Math.max(ponto.vencimento_dia, hoje)) - 1] += ponto.valor_centavos;
        }
    }
    let acumulado = 0;

    return acumuladoPorDia(pontos, dias).map((recebido, indice) => recebido + (acumulado += entradas[indice]));
}

/** Totais do mês em centavos: recebido (valor pago), aguardando, a enviar e o previsto (a soma dos três). */
export function somasPorEtapa(pontos) {
    const somas = { recebido: 0, aguardando: 0, enviar: 0 };
    for (const ponto of pontos) {
        if (ponto.etapa === 'recebida') {
            somas.recebido += ponto.pago_centavos ?? 0;
        } else {
            somas[ponto.etapa] += ponto.valor_centavos;
        }
    }

    return { ...somas, previsto: somas.recebido + somas.aguardando + somas.enviar };
}

const UNIDADE = 100; // largura de um dia no espaço do desenho (viewBox), qualquer que seja o mês
const RAMPA = 36; // quanto antes do centro do dia a curva começa a subir
const centroDoDia = (dia) => (dia - 0.5) * UNIDADE;

/**
 * Traços da régua de dinheiro num espaço de `dias * 100` por 100: `linha` e `area` do recebido até `hoje`, `projecao`
 * (o pontilhado) de `hoje` ao fim do mês. `recebido` e `previsto` são as somas acumuladas por dia (índice = dia - 1).
 */
export function caminhosDaRegua({ recebido, previsto, dias, hoje }) {
    const maximo = Math.max(previsto.at(-1) ?? 0, 1);
    const y = (valor) => +(100 - (valor / maximo) * 100).toFixed(2);
    const rec = [0, ...recebido];
    const prev = [0, ...previsto];
    const degrau = (serie, dia) => ` L${centroDoDia(dia) - RAMPA} ${y(serie[dia - 1])} L${centroDoDia(dia)} ${y(serie[dia])}`;

    let linha = 'M0 100';
    for (let dia = 1; dia <= hoje; dia++) {
        linha += degrau(rec, dia);
    }
    let projecao = `M${centroDoDia(hoje)} ${y(rec[hoje])} L${centroDoDia(hoje)} ${y(prev[hoje])}`;
    for (let dia = hoje + 1; dia <= dias; dia++) {
        projecao += degrau(prev, dia);
    }

    return {
        maximo,
        linha,
        area: `${linha} L${centroDoDia(hoje)} 100 Z`,
        projecao: `${projecao} L${dias * UNIDADE} ${y(prev[dias])}`,
        larguraTotal: dias * UNIDADE,
        larguraRecebido: centroDoDia(hoje),
        pontaY: y(rec[hoje]),
    };
}

const PASSOS_DA_GRADE = [100, 200, 500, 1000, 2000, 5000, 10000, 20000, 50000, 100000, 200000, 500000]; // em reais

/** Valores (centavos) das linhas de referência da régua: no máximo três, a passos redondos, abaixo do topo. */
export function linhasDaGrade(maximo) {
    const passo = (PASSOS_DA_GRADE.find((candidato) => maximo / 100 / candidato <= 3) ?? PASSOS_DA_GRADE.at(-1)) * 100;
    const linhas = [];
    for (let valor = passo; valor < maximo * 0.95 && linhas.length < 3; valor += passo) {
        linhas.push(valor);
    }

    return linhas;
}

/** O que o ponto diz, para a dica e para o leitor de tela: etapa, prazo e se cai em fim de semana ou feriado. */
export function descricaoDoPonto(ponto, { mes, hoje, feriados, fim_de_semana: fimDeSemana }) {
    const numeroDoMes = Number(mes.split('-')[1]);
    if (ponto.etapa === 'recebida') {
        return `Recebida em ${ponto.pago_dia}/${numeroDoMes}`;
    }
    const dia = ponto.vencimento_dia;
    const etapa = ponto.etapa === 'enviar' ? 'Recibo ainda não enviado' : 'Aguardando pagamento';
    const data = `${SEMANA[diaDaSemana(mes, dia)]}, ${dia}/${numeroDoMes}`;
    const prazo = {
        vencida: `vencida há ${plural(hoje - dia, 'dia', 'dias')}`,
        vence_hoje: 'vence hoje',
    }[ponto.situacao] ?? `vence em ${plural(dia - hoje, 'dia', 'dias')}, ${data}`;
    const nota = feriados[dia] ? `. Cai em feriado: ${feriados[dia]}` : fimDeSemana.includes(dia) ? '. Cai no fim de semana' : '';

    return `${etapa}, ${prazo}${nota}`;
}

/** "Sexta, 6 de novembro. Faltam 24 dias para fechar o mês." */
export function frasePeloDia({ mes, hoje, dias }) {
    const [ano, numero] = mes.split('-').map(Number);
    const data = new Date(Date.UTC(ano, numero - 1, hoje));
    const semana = data.toLocaleDateString('pt-BR', { weekday: 'long', timeZone: 'UTC' }).split('-')[0];
    const nomeDoMes = data.toLocaleDateString('pt-BR', { month: 'long', timeZone: 'UTC' });
    const faltam = dias - hoje;
    const fecho = faltam === 0 ? 'Último dia do mês.' : `${faltam === 1 ? 'Falta' : 'Faltam'} ${plural(faltam, 'dia', 'dias')} para fechar o mês.`;

    return `${semana.charAt(0).toUpperCase()}${semana.slice(1)}, ${hoje} de ${nomeDoMes}. ${fecho}`;
}
