/**
 * Textos das etapas da cobrança no Painel, num lugar só: o Talão, a Régua e o Almanaque mostram as mesmas palavras.
 * "Para enviar" não dizia o quê nem para quem (decisão do Marcos, 2026-11-07): agora é o recibo que vai ao cliente.
 *
 * Etapas: recibo pronto e ainda não enviado → enviado e sem pagamento → pago.
 */
export const textosDoPainel = {
    enviar: {
        titulo: 'Recibos a enviar',
        aba: 'A enviar',
        dica: 'Recibo pronto que ainda não foi mandado ao cliente.',
    },
    aguardando: {
        titulo: 'Aguardando pagamento',
        aba: 'Aguardando',
        dica: 'Recibo já enviado; falta o cliente pagar.',
        vazio: 'Nenhuma cobrança enviada esperando pagamento.',
    },
    avisar: 'Cobranças a avisar',
};

/** Texto da lista de recibos a enviar quando está vazia; as cobranças novas entram `dias` antes do vencimento. */
export function vazioDeEnviar(dias) {
    return `Nenhum recibo para enviar agora. As cobranças novas aparecem aqui ${dias} ${dias === 1 ? 'dia' : 'dias'} antes do vencimento.`;
}
