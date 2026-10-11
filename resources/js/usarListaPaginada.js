import { nextTick, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { buscarJson } from './http.js';
import { criarListaPaginada } from './listaPaginada.js';

/**
 * Liga a lista paginada (listaPaginada.js) a uma tela Inertia: primeira carga ao montar; filtros e página na URL
 * (`endereco`; sem ele a URL não muda — o Painel tem três listas); foco em `idDoFoco` ao trocar de página; e recarga
 * quando uma ação da tela termina (evento "success", que só sai de resposta do servidor, da página `componente`).
 */
export function usarListaPaginada({ url, filtro, componente, endereco = null, idDoFoco = 'paginador-topo', aoMostrar = () => {} }) {
    const lista = criarListaPaginada({
        url,
        filtro,
        buscar: buscarJson,
        aoMostrar(pagina, focar) {
            aoMostrar(pagina);
            if (endereco) {
                // Sem pedido ao servidor nem entrada nova no histórico: F5, voltar e link copiado reabrem igual.
                router.replace({
                    url: `${endereco}?${lista.parametros(pagina)}`,
                    props: (atuais) => ({ ...atuais, filtros: { ...filtro } }),
                    preserveState: true,
                    preserveScroll: true,
                });
            }
            if (focar) {
                nextTick(() => {
                    const alvo = document.getElementById(idDoFoco);
                    alvo?.scrollIntoView({ block: 'start' });
                    alvo?.focus({ preventScroll: true });
                });
            }
        },
    });

    let pararDeOuvir = () => {};
    onMounted(() => {
        lista.irPara(filtro.pagina);
        pararDeOuvir = router.on('success', (evento) => {
            if (evento.detail.page.component === componente) {
                lista.aoTerminarAcao();
            }
        });
    });
    onUnmounted(() => {
        pararDeOuvir();
        lista.encerrar();
    });

    return lista;
}
