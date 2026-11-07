import { onMounted, onUnmounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { buscarJson } from './http.js';

const FALHA = 'Não foi possível carregar a linha do tempo.';

/**
 * Os dados do mês corrente (`GET /painel/mes`) para a linha do tempo e os números do mês. Carrega ao montar e de novo
 * quando uma ação da página termina (evento "success" do Inertia, como as listas do Painel). `estado`: 'carregando'
 * (só na primeira carga), 'falha' (sem nada para mostrar) ou 'pronto'; `falha` guarda o texto do último erro, que numa
 * recarga convive com os dados antigos. Uma resposta atrasada nunca sobrescreve uma mais nova.
 */
export function usarMesDoPainel(componente = 'Painel') {
    const dados = ref(null);
    const estado = ref('carregando');
    const falha = ref('');
    let versao = 0;

    async function carregar() {
        const minha = ++versao;
        if (!dados.value) {
            estado.value = 'carregando';
        }
        const resposta = await buscarJson('/painel/mes');
        if (minha !== versao) {
            return;
        }
        if (resposta.dados) {
            dados.value = resposta.dados;
            falha.value = '';
            estado.value = 'pronto';
        } else {
            falha.value = FALHA;
            estado.value = dados.value ? 'pronto' : 'falha';
        }
    }

    let pararDeOuvir = () => {};
    onMounted(() => {
        carregar();
        pararDeOuvir = router.on('success', (evento) => {
            if (evento.detail.page.component === componente) {
                carregar();
            }
        });
    });
    onUnmounted(() => {
        pararDeOuvir();
        versao++;
    });

    return { dados, estado, falha, carregar };
}
