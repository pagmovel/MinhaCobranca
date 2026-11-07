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
