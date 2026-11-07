import { computed } from 'vue';
import { router as roteadorDoInertia, usePage } from '@inertiajs/vue3';
import { temaValido } from './temas.js';

/**
 * Trocar de tema pede recarga completa: o `<head>` com as fontes vem do Blade e a navegação do Inertia não o refaz.
 * O payload de `temas` não traz as fontes (só id, casca, painel, linha do tempo e amostra), então não dá para
 * saber se duas fontes diferem; na dúvida recarrega, por segurança. Só deixa de recarregar quando é o mesmo tema
 * (por exemplo, a amostra ou o texto mudaram, o que não toca no `<head>`).
 * shortcut: recarrega até quando duas fontes seriam iguais; refinar se o payload ganhar `fontes`.
 */
export function precisaRecarregar(anterior, novo) {
    return anterior?.id !== novo?.id;
}

/**
 * Aplica o tema na hora (`data-tema`), grava com `PUT /perfil/tema` e, se falhar, volta ao tema anterior.
 * Resolve `{ ok }`; nunca rejeita. `anterior` é o tema gravado hoje (item de `lista`). `documento`, `roteador` e
 * `recarregar` existem só para os testes rodarem sem navegador.
 */
export async function trocarTema(id, {
    lista,
    anterior,
    documento = document,
    roteador = roteadorDoInertia,
    recarregar = () => window.location.reload(),
}) {
    const novoId = temaValido(id, lista);
    const raiz = documento.documentElement;
    raiz.dataset.tema = novoId;

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

    if (!ok) {
        // Só desfaz se ninguém trocou de novo no meio: senão apagaria a escolha mais nova.
        if (raiz.dataset.tema === novoId) {
            raiz.dataset.tema = anterior.id;
        }

        return { ok: false };
    }

    if (precisaRecarregar(anterior, lista.find((tema) => tema.id === novoId))) {
        recarregar();
    }

    return { ok: true };
}

/** `atual` (tema gravado do usuário) e `lista` são reativos: o Inertia troca `page.props` a cada visita. */
export function usarTema() {
    const pagina = usePage();
    const lista = computed(() => pagina.props.temas);
    const atual = computed(() => {
        const id = temaValido(pagina.props.auth.user?.tema, lista.value);

        return lista.value.find((tema) => tema.id === id);
    });

    return { atual, lista, trocar: (id) => trocarTema(id, { lista: lista.value, anterior: atual.value }) };
}
