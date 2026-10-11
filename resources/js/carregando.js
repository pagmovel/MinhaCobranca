import { ref } from 'vue';

/** Pedidos ao servidor em andamento (fetch e visitas do Inertia): o indicador global aparece enquanto houver algum. */
export const pendentes = ref(0);

/** Conta o pedido enquanto ele não termina, com sucesso ou falha, e devolve a mesma promessa. */
export function contarPedido(promessa) {
    pendentes.value++;

    return promessa.finally(() => {
        pendentes.value = Math.max(0, pendentes.value - 1);
    });
}

/**
 * Embrulha o `fetch` uma vez só: todo pedido da aplicação (listas, autosugestão, prévias, PDF) passa a contar, inclusive
 * os componentes que chamam `fetch` direto. A promessa resolve na chegada dos cabeçalhos — a espera é o servidor.
 */
export function vigiarFetch(alvo = globalThis) {
    if (alvo.fetch.vigiado) {
        return;
    }

    const original = alvo.fetch.bind(alvo);
    alvo.fetch = (...argumentos) => contarPedido(original(...argumentos));
    alvo.fetch.vigiado = true;
}
