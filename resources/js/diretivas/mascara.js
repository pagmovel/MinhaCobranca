import { formatadores } from '../mascaras.js';

function formatar(el, colou = false) {
    // Colar um valor pronto ("1.500") não pode ser lido pelos centavos (daria 15,00).
    const nome = colou && el.dataset.mascara === 'valor' ? 'valorColado' : el.dataset.mascara;
    const formatador = formatadores[nome];
    if (!formatador) {
        return;
    }
    const formatado = formatador(el.value);
    if (formatado !== el.value) {
        el.value = formatado;
        // O v-model escuta 'input': assim ele guarda o valor já formatado. Na segunda passada nada muda.
        el.dispatchEvent(new Event('input'));
    }
}

/** Uso: v-mascara="'valor'". O cursor vai para o fim ao formatar — aceitável nesses campos. */
export default {
    mounted(el, { value }) {
        el.dataset.mascara = value;
        el.addEventListener('input', (evento) => formatar(el, evento.inputType === 'insertFromPaste'));
        formatar(el);
    },
    updated(el, { value }) {
        el.dataset.mascara = value;
        formatar(el);
    },
};
