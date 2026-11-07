import { computed, ref } from 'vue';
import { router as roteadorDoInertia, usePage } from '@inertiajs/vue3';
import { carregarFontesDoTema } from './fontesDoTema.js';
import { temaValido } from './temas.js';

/**
 * Tema que já está na tela mas o servidor ainda não confirmou (a troca está em andamento). Faz o menu e o Painel
 * trocarem junto com as cores, sem esperar a resposta; falhando, volta a ser `null` e o tema gravado reaparece.
 */
export const temaEmTroca = ref(null);

/**
 * Aplica o tema na hora (`data-tema` e `temaEmTroca`), grava com `PUT /perfil/tema` e, se falhar, volta ao tema
 * anterior. Nunca recarrega a página: as fontes do tema novo entram no `<head>` por `fontes(id)` (em paralelo com a
 * gravação) e, se não carregarem, o tema vale com as fontes de reserva.
 *
 * Resolve `{ ok: true, fontes: 'ok' | 'reserva' }` ou `{ ok: false, substituida }` (`substituida`: uma escolha mais nova
 * tomou o lugar desta, então não há erro a mostrar); nunca rejeita. `anterior` é o tema gravado hoje (item de `lista`).
 * `documento`, `roteador`, `fontes` e `emTroca` existem só para os testes rodarem sem navegador.
 */
export async function trocarTema(id, {
    lista,
    anterior,
    documento = document,
    roteador = roteadorDoInertia,
    fontes = carregarFontesDoTema,
    emTroca = temaEmTroca,
}) {
    const novoId = temaValido(id, lista);
    const raiz = documento.documentElement;
    raiz.dataset.tema = novoId;
    emTroca.value = novoId;
    const fontesDoTema = fontes(novoId); // não rejeita; só se espera depois que a gravação der certo

    const { ok } = await new Promise((resolver) => {
        let gravou = false;
        roteador.put('/perfil/tema', { tema: novoId }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => { gravou = true; },
            // false: sem o modal de erro do Inertia; quem chamou mostra a própria mensagem com "Tentar de novo".
            onHttpException: () => false,
            onNetworkError: () => false,
            // onFinish vem por último em qualquer desfecho (sucesso, erro de validação, HTTP, rede ou cancelamento).
            onFinish: () => resolver({ ok: gravou }),
        });
    });

    if (emTroca.value === novoId) {
        emTroca.value = null;
    }

    if (!ok) {
        // Só desfaz se ninguém trocou de novo no meio: senão apagaria a escolha mais nova.
        const substituida = raiz.dataset.tema !== novoId;
        if (!substituida) {
            raiz.dataset.tema = anterior.id;
        }

        return { ok: false, substituida };
    }

    return { ok: true, fontes: await fontesDoTema };
}

/**
 * `lista` e `gravado` (o tema que está no banco) são reativos: o Inertia troca `page.props` a cada visita. `atual` é o
 * que a tela mostra: o tema em troca, se houver, senão o gravado.
 */
export function usarTema() {
    const pagina = usePage();
    const lista = computed(() => pagina.props.temas);
    const buscar = (id) => lista.value.find((tema) => tema.id === temaValido(id, lista.value));
    const gravado = computed(() => buscar(pagina.props.auth.user?.tema));
    const atual = computed(() => buscar(temaEmTroca.value ?? pagina.props.auth.user?.tema));

    return { atual, lista, trocar: (id) => trocarTema(id, { lista: lista.value, anterior: gravado.value }) };
}
