import { CEM_POR_CENTO, aparar, descontoEmCentavos, pontosBase, semSeparadorFinal } from './desconto.js';
import { formatadores } from './mascaras.js';

/**
 * Mesmas contas do servidor (App\Support\CondicoesDePagamento), só para mostrar o valor do dia enquanto se digita.
 * Regra em inteiros: percentual em pontos-base (2% = 200) ou R$ em centavos, conforme o tipo. Sem tipo ou sem
 * valor não há regra e a conta dá 0.
 */
const PERCENTUAL = 'percentual';
const VALOR = 'valor';
const DIA_EM_MILISSEGUNDOS = 86_400_000;
/** Teto de sanidade do R$ da regra (R$ 99.999.999,99, o mesmo de App\Rules\ValorEmReais::MAXIMO_CENTAVOS). */
export const MAXIMO_EM_CENTAVOS = 9_999_999_999;

/** Dia do calendário de "AAAA-MM-DD" (UTC: sem fuso nem horário de verão na conta); null se não for uma data. */
function diaDoCalendario(iso) {
    const partes = String(iso ?? '').match(/^(\d{4})-(\d{2})-(\d{2})/);

    return partes ? Date.UTC(Number(partes[1]), Number(partes[2]) - 1, Number(partes[3])) / DIA_EM_MILISSEGUNDOS : null;
}

/** Dias corridos do vencimento até a data (0 se não passou ou se alguma data não for válida). */
export function diasDeAtraso(vencimentoIso, dataIso) {
    const de = diaDoCalendario(vencimentoIso);
    const ate = diaDoCalendario(dataIso);

    return de === null || ate === null ? 0 : Math.max(0, ate - de);
}

/** 1250 → "12,5", 1500 → "15", 5 → "0,05" (vírgula, sem zeros à direita), como Desconto::textoDoPercentual. */
function textoDoPercentual(pontos) {
    const decimais = String(pontos % 100).padStart(2, '0').replace(/0+$/, '');

    return Math.floor(pontos / 100) + (decimais === '' ? '' : `,${decimais}`);
}

/** A regra como se digita no campo: 1500 percentual → "15", 250 → "2,5", 1500 R$ → "15,00"; sem regra, "". */
export function entradaDaRegra(tipo, regra) {
    if (!(regra > 0)) {
        return '';
    }
    if (tipo === PERCENTUAL) {
        return textoDoPercentual(regra);
    }

    return tipo === VALOR ? formatadores.valor(String(regra)) : '';
}

/** O que se digitou virou regra: vazio ou 0 é 0 (sem regra); inválido ou percentual acima de 100% é null. */
export function regraDaEntrada(tipo, entrada) {
    const texto = aparar(entrada);

    if (texto === '') {
        return 0;
    }
    if (tipo === PERCENTUAL) {
        const pontos = pontosBase(texto);

        return pontos !== null && pontos <= CEM_POR_CENTO ? pontos : null;
    }

    if (tipo !== VALOR) {
        return null;
    }
    const centavos = descontoEmCentavos(0, VALOR, texto);

    return centavos > MAXIMO_EM_CENTAVOS ? null : centavos;
}

/** Multa e desconto são a mesma conta do desconto da baixa, aplicada à regra gravada no contrato. */
function aplicar(valor, tipo, regra) {
    return tipo && regra > 0 ? (descontoEmCentavos(valor, tipo, entradaDaRegra(tipo, regra)) ?? 0) : 0;
}

/** Multa de uma vez só: percentual do valor ou o próprio valor em R$. */
export const multaEmCentavos = aplicar;

/** Desconto por pontualidade, limitado ao valor. */
export const descontoDoContrato = (valor, tipo, regra) => Math.min(valor, aplicar(valor, tipo, regra));

/** Juros simples ao mês, proporcionais aos dias (mês de 30 dias). BigInt: o produto passa de 2^53. */
export function jurosEmCentavos(valor, tipo, regra, dias) {
    if (!(regra > 0) || !(dias > 0)) {
        return 0;
    }
    if (tipo === PERCENTUAL) {
        return Number((BigInt(valor) * BigInt(regra) * BigInt(dias) + 150000n) / 300000n);
    }

    return tipo === VALOR ? Number((BigInt(regra) * BigInt(dias) + 15n) / 30n) : 0;
}

/**
 * O valor da cobrança numa data, pelas regras do contrato ({ desconto, multa, juros }, cada uma { tipo, valor } ou null):
 * até o vencimento, o desconto; depois dele, multa e juros. Sem regra, o valor original.
 */
export function condicoesNaData(valor, vencimentoIso, dataIso, regras) {
    const dias = diasDeAtraso(vencimentoIso, dataIso);
    const { desconto, multa, juros } = regras ?? {};
    const descontoCentavos = dias === 0 ? descontoDoContrato(valor, desconto?.tipo, desconto?.valor) : 0;
    const multaCentavos = dias > 0 ? multaEmCentavos(valor, multa?.tipo, multa?.valor) : 0;
    const jurosCentavos = jurosEmCentavos(valor, juros?.tipo, juros?.valor, dias);

    return { dias, descontoCentavos, multaCentavos, jurosCentavos, totalCentavos: valor - descontoCentavos + multaCentavos + jurosCentavos };
}

/** O campo da tela do contrato como regra ({ tipo, valor }); vazio, 0, inválido ou sem tipo é sem regra (null). */
export function regraDoCampo(tipo, entrada) {
    // A vírgula no fim ("12,") é digitação em curso: conta como o número antes dela.
    const valor = regraDaEntrada(tipo, semSeparadorFinal(entrada));

    return valor > 0 ? { tipo, valor } : null;
}

const DIAS_DO_EXEMPLO = 30;

/**
 * Exemplo da tela do contrato, com o valor atual dele: o valor em dia (com o desconto) e com 30 dias de atraso (com
 * multa e juros), pela mesma conta da baixa. null sem valor ou sem nenhuma regra.
 */
export function exemploDasCondicoes(valor, regras) {
    const { desconto, multa, juros } = regras ?? {};

    if (!(valor > 0) || ![desconto, multa, juros].some((regra) => regra?.valor > 0)) {
        return null;
    }

    return {
        dias: DIAS_DO_EXEMPLO,
        emDiaCentavos: valor - descontoDoContrato(valor, desconto?.tipo, desconto?.valor),
        atrasadoCentavos: valor + multaEmCentavos(valor, multa?.tipo, multa?.valor) + jurosEmCentavos(valor, juros?.tipo, juros?.valor, DIAS_DO_EXEMPLO),
    };
}
