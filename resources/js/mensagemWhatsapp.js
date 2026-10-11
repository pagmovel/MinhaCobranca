import { formatarCentavos } from './itens.js';

/** Limite prático observado em clientes do WhatsApp: acima disso, alguns cortam a mensagem. */
const LIMITE_PRATICO = 1000;

/** Usado quando não há modelo cadastrado nem padrão. Em bloco (não numa frase), para não exigir concordância verbal
 * com a quantidade de itens — "estám vencendo" era o bug corrigido (achado da revisão, T24.9). */
export const MENSAGEM_SUGERIDA = 'Olá {cliente}! Segue o lembrete de vencimento ({vencimento}):\n{itens}\n\n'
    + 'Total: {total}\n\nQualquer dúvida, estou à disposição.\n\n{prestador}';

function linhaDoItem(item) {
    return `- ${item.nome}: R$ ${formatarCentavos(item.valorCentavos)}`;
}

/** "2026-10-15" (ISO, sem hora) → "15/10/2026" ou "15/10" sem o ano. */
function formatarData(iso, comAno) {
    const [ano, mes, dia] = iso.split('-');

    return comAno ? `${dia}/${mes}/${ano}` : `${dia}/${mes}`;
}

/** Uma data: dia/mês/ano. Duas ou mais: "a, b e c" — só a última com o ano. */
function juntarVencimentos(vencimentos) {
    if (vencimentos.length === 1) {
        return formatarData(vencimentos[0], true);
    }
    const curtas = vencimentos.slice(0, -1).map((data) => formatarData(data, false));
    const ultima = formatarData(vencimentos.at(-1), true);

    return `${curtas.join(', ')} e ${ultima}`;
}

/**
 * Substitui as variáveis conhecidas por valores reais; o que não reconhece fica literal no texto (erro de
 * digitação no modelo não pode travar nem sumir pedaço da mensagem).
 */
export function montarMensagem(texto, { cliente, itens, vencimentos, totalCentavos, prestador }) {
    return texto
        .replaceAll('{cliente}', cliente)
        .replaceAll('{itens}', itens.map(linhaDoItem).join('\n'))
        .replaceAll('{vencimento}', juntarVencimentos(vencimentos))
        .replaceAll('{total}', `R$ ${formatarCentavos(totalCentavos)}`)
        .replaceAll('{prestador}', prestador);
}

/** Celular válido (DDI 55 + DDD + 9 dígitos começando em "9") para o link do WhatsApp; senão, null. */
export function numeroWhatsapp(telefone) {
    const digitos = String(telefone ?? '').replace(/\D/g, '');

    return digitos.length === 11 && digitos[2] === '9' ? `55${digitos}` : null;
}

export function montarLinkWaMe(numero, texto) {
    return `https://wa.me/${numero}?text=${encodeURIComponent(texto)}`;
}

export function mensagemMuitoLonga(texto) {
    return texto.length > LIMITE_PRATICO;
}
