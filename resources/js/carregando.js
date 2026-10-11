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
 * Atraso antes de mostrar o indicador (como a barra nativa do Inertia): pedido mais rápido que `ms` nem chega a aparecer,
 * então a pílula não pisca e o leitor de tela não anuncia a cada clique. `contador` é algo com `.value` (o `pendentes`
 * acima, ou um objeto simples em teste); chamar a função devolvida a cada mudança dele. Esconder é sempre na hora.
 */
export function criarIndicadorComAtraso(contador, mostrar, ms = 250) {
    let temporizador = null;
    let visivel = false;

    return () => {
        if (contador.value > 0) {
            if (temporizador === null && !visivel) {
                temporizador = setTimeout(() => {
                    temporizador = null;
                    if (contador.value > 0) {
                        visivel = true;
                        mostrar(true);
                    }
                }, ms);
            }
            return;
        }

        if (temporizador !== null) {
            clearTimeout(temporizador);
            temporizador = null;
        }
        if (visivel) {
            visivel = false;
            mostrar(false);
        }
    };
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
