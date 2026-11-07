/**
 * Convivência de janelas empilhadas: o painel lateral do usuário e, por cima dele, um popup (`Modal.vue`).
 * As duas camadas escutam o teclado no `document` e mexem na rolagem do corpo; estas regras decidem quem age.
 */

/**
 * Quem reage ao Esc (e ao Tab): só a camada de cima. Com o popup aberto o painel fica quieto (não fecha, não puxa o
 * foco de volta); sem popup, o painel; sem nenhum aberto, ninguém (`null`).
 */
export function quemFechaComEsc(painelAberto, popupAberto) {
    if (popupAberto) {
        return 'popup';
    }

    return painelAberto ? 'painel' : null;
}

/**
 * Há outra janela (`aria-modal`) aberta além do `painel`? Todo `Modal` (z-50) fica por cima do painel (z-40), então
 * qualquer outra é "de cima" e o painel deve ficar quieto: sem isto, um Esc fecharia as duas. A janela que está só
 * saindo da tela (`display: none` da rede de segurança do `Modal`) não conta.
 */
export function haJanelaPorCima(painel, documento = document) {
    return [...documento.querySelectorAll('[role="dialog"][aria-modal="true"]')]
        .some((janela) => janela !== painel && janela.getClientRects().length > 0);
}

const donosDaTrava = new Set();

/**
 * Trava a rolagem do corpo para `dono` (quem abriu a janela). A trava só sai quando **todos** os donos liberaram:
 * fechar o popup não destrava o corpo enquanto o painel segue aberto. Idempotente por dono.
 */
export function travarRolagem(dono, corpo = document.body) {
    donosDaTrava.add(dono);
    corpo.style.overflow = 'hidden';
}

/** Solta a trava de `dono`; sem donos restantes, devolve a rolagem. Liberar quem nunca travou não muda nada. */
export function liberarRolagem(dono, corpo = document.body) {
    donosDaTrava.delete(dono);
    if (donosDaTrava.size === 0) {
        corpo.style.overflow = '';
    }
}
