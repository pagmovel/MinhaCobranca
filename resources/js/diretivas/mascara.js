import { formatadores } from '../mascaras.js';

function formatar(el) {
    const formatador = formatadores[el.dataset.mascara];
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
        el.addEventListener('input', () => formatar(el));
        formatar(el);
    },
    updated(el, { value }) {
        el.dataset.mascara = value;
        formatar(el);
    },
};
