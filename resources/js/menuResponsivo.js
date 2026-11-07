/**
 * Quantos itens (em ordem) ficam na barra; o resto vai para o "Mais ▾". Se todos cabem, nenhum vai para o "Mais".
 * Senão, reserva `larguraMais` para o botão e nunca deixa a barra sem item (mínimo 1, se houver itens).
 */
export function quantosCabem(larguras, disponivel, larguraMais) {
    if (larguras.length === 0) {
        return 0;
    }
    if (larguras.reduce((soma, largura) => soma + largura, 0) <= disponivel) {
        return larguras.length;
    }

    let usado = 0;
    let quantos = 0;
    for (const largura of larguras) {
        if (usado + largura > disponivel - larguraMais) {
            break;
        }
        usado += largura;
        quantos++;
    }

    return Math.max(quantos, 1);
}
