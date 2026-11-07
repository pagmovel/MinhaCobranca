import { formatarCentavos } from './itens.js';
import { diaDaSemana, SEMANA } from './linhaDoTempo.js';

const ETAPAS_DAS_ABAS = ['enviar', 'aguardando', 'recebida'];

/**
 * Arcos do anel do bloco Dinheiro: cada fatia (de `fatiasDoMes`) vira um traço de um círculo com `pathLength` 100.
 * `deslocamento` é negativo (o traço começa depois do que já foi desenhado) e entre dois arcos sobra uma folga de
 * 0,9 para o olho separar as partes; uma fatia sozinha com 100% fecha o círculo, sem folga.
 */
export function arcosDoAnel(fatias, folga = 0.9) {
    let inicio = 0;

    return fatias.map((fatia) => {
        const inteira = fatia.porcento >= 100;
        const arco = {
            chave: fatia.chave,
            comprimento: inteira ? 100 : Math.max(0, fatia.porcento - folga),
            deslocamento: -(inteira ? 0 : inicio + folga / 2),
        };
        inicio += fatia.porcento;

        return arco;
    });
}

/** Frase lida pelo leitor de tela no anel: "62% recebido: R$ 1.000,00 de R$ 1.600,00 previstos para novembro". */
export function textoDoAnel({ porcento, recebidoCentavos, previstoCentavos, nomeDoMes }) {
    if (previstoCentavos <= 0) {
        return `Nada previsto para ${nomeDoMes} ainda`;
    }

    return `${porcento}% recebido: R$ ${formatarCentavos(recebidoCentavos)} de R$ ${formatarCentavos(previstoCentavos)} previstos para ${nomeDoMes}`;
}

/** Aba que as setas, Home e End escolhem a partir da atual (as setas dão a volta); `null` para qualquer outra tecla. */
export function abaPorTecla(tecla, atual, abas = ETAPAS_DAS_ABAS) {
    const indice = abas.indexOf(atual);
    const destino = { ArrowRight: indice + 1, ArrowLeft: indice - 1, Home: 0, End: abas.length - 1 }[tecla];

    return destino === undefined ? null : abas[(destino + abas.length) % abas.length];
}

/** Uma etapa que tinha cobranças e agora não tem: é o momento do confete. `antes` é `null` até a primeira contagem. */
export function esvaziou(antes, depois) {
    return typeof antes === 'number' && antes > 0 && depois === 0;
}

/** "sexta, 6/11": o dia de hoje na nota do bloco Hoje (`dados` é a resposta de `/painel/mes`). */
export function notaDoDia({ mes, hoje }) {
    return `${SEMANA[diaDaSemana(mes, hoje)]}, ${hoje}/${Number(mes.slice(5))}`;
}

/** "há 1 dia", "há 12 dias": completa "venceu ..." e "Vencida ...". */
export function haQuantosDias(dias) {
    return `há ${dias} ${dias === 1 ? 'dia' : 'dias'}`;
}
