/**
 * Ações de uma cobrança na lista, na ordem de importância: a primeira vira o botão da linha e as demais
 * vão para o menu "⋯" (skill recibos, padrão 12).
 */
function emOrdem(cobranca) {
    // Prevista é calculada pelo contrato: ainda não existe cobrança para agir sobre ela.
    if (cobranca.situacao === 'prevista') return [];
    const pdf = cobranca.recibo ? ['pdf'] : [];
    const enviar = cobranca.recibo ? ['enviar'] : [];

    if (cobranca.situacao === 'paga') {
        return [...pdf, ...enviar, 'corrigir', 'desfazer'];
    }
    if (cobranca.situacao === 'cancelada') {
        return pdf;
    }

    // Em aberto: antes de enviar, o próximo passo é enviar; depois, é receber.
    const inicio = cobranca.enviado ? ['baixa', ...enviar] : [...enviar, 'baixa'];

    return [...inicio, ...pdf, 'avisar', 'pagamento', ...(cobranca.editavel ? ['editar'] : []), 'cancelar'];
}

export function acoesDaCobranca(cobranca) {
    const acoes = emOrdem(cobranca);

    return { principal: acoes[0] ?? null, menu: acoes.slice(1) };
}

const ROTULOS = {
    pdf: 'Ver PDF',
    baixa: 'Dar baixa',
    pagamento: 'Forma de pagamento',
    editar: 'Editar',
    cancelar: 'Cancelar cobrança',
    corrigir: 'Corrigir pagamento',
    desfazer: 'Desfazer baixa',
    avisar: 'Avisar no WhatsApp',
};

export function rotuloDaAcao(acao, cobranca) {
    if (acao === 'enviar') {
        return cobranca.situacao === 'paga' ? 'Enviar recibo pago' : cobranca.enviado ? 'Reenviar' : 'Enviar';
    }

    return ROTULOS[acao];
}
