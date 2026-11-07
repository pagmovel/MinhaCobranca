<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, useId } from 'vue';
import { router } from '@inertiajs/vue3';
import { proximoDaArmadilha, saindoDaArmadilha } from '../../armadilhaDeFoco.js';
import { buscarJson } from '../../http.js';
import AvisarWhatsapp from '../AvisarWhatsapp.vue';
import LinhaCobranca from '../LinhaCobranca.vue';
import AcoesDaCobranca from './AcoesDaCobranca.vue';

/**
 * Popover do ponto da linha do tempo: busca a mesma linha das listas (`GET /painel/cobrancas/{id}`) e mostra as ações
 * da etapa com os componentes de sempre. Não é modal: nada escurece, a rolagem do corpo fica livre e o popover
 * acompanha o ponto (`position: fixed`, fora do bloco que rola). Esc fecha só ele (com uma janela por cima, ela é a
 * que fecha); clique fora também, exceto com um formulário aberto dentro (baixa, forma de pagamento), para não perder
 * o que foi digitado. Pensado para uma instância por ponto: o pai usa `:key` com o id.
 */
const props = defineProps({
    id: { type: Number, required: true },
    ancora: { type: Object, default: null }, // o botão do ponto
    descricao: { type: String, required: true },
    chavesPix: { type: Array, required: true },
    formasPagamento: { type: Array, required: true },
    modelos: { type: Array, required: true },
    prestador: { type: String, required: true },
});
const emit = defineEmits(['fechar']);

const idTitulo = `${useId()}-titulo`;
const raiz = ref(null);
const cobranca = ref(null);
const estado = ref('carregando'); // 'carregando' | 'pronto' | 'falha'
const avisando = ref(false);
const estilo = ref({});

// Etapa da linha, a mesma regra de AcoesDaCobranca: quando ela muda depois de uma ação (enviou, deu baixa), o popover
// cumpriu o papel e fecha, para o ponto saltar ou mudar de cor à vista (aberto, ele cobriria o salto).
const etapaDe = (linha) => (linha.situacao === 'paga' ? 'recebida' : linha.enviado ? 'aguardando' : 'enviar');

async function carregar() {
    if (!cobranca.value) {
        estado.value = 'carregando';
    }
    const resposta = await buscarJson(`/painel/cobrancas/${props.id}`);
    if (resposta.dados) {
        const mudou = cobranca.value && etapaDe(cobranca.value) !== etapaDe(resposta.dados);
        cobranca.value = resposta.dados;
        estado.value = 'pronto';
        if (mudou) {
            emit('fechar', true);
        }
    } else if (!cobranca.value) {
        estado.value = 'falha';
    }
}

const focaveis = () => [...(raiz.value?.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled])') ?? [])]
    .filter((elemento) => elemento.getClientRects().length);

// No documento, não no popover: clicar em "Dar baixa" troca o botão pelo formulário e o foco cai no `body`; Esc e Tab
// precisam continuar valendo. Cede a vez a quem está por cima (Avisar no WhatsApp, painel do usuário) e ao formulário
// aberto, que fecha primeiro com o Esc dele.
function teclado(evento) {
    if (document.querySelector('[aria-modal="true"]')) {
        return;
    }
    const foco = document.activeElement;
    const dentro = raiz.value?.contains(foco);
    const noPonto = foco?.closest?.('[data-ponto]'); // o foco pode estar em outro ponto da linha do tempo
    if (evento.key === 'Escape' && (dentro || noPonto || foco === document.body) && !evento.target.closest?.('form')) {
        evento.stopPropagation();
        emit('fechar', true);
    } else if (evento.key === 'Tab' && (dentro || foco === document.body)) {
        const lista = focaveis();
        const indice = lista.indexOf(foco);
        if (saindoDaArmadilha(lista.length, indice, evento.shiftKey)) {
            evento.preventDefault();
            lista[proximoDaArmadilha(lista.length, indice, evento.shiftKey)]?.focus();
        }
    }
}

// Um clique que troca o botão pelo formulário (ou o contrário) deixa o foco no `body`: leva-o ao primeiro campo do
// formulário que abriu, ou de volta ao popover.
function depoisDoClique() {
    nextTick(() => {
        if (document.activeElement === document.body && raiz.value) {
            (raiz.value.querySelector('form input:not([type=hidden]), form select') ?? raiz.value).focus({ preventScroll: true });
        }
    });
}

let quadro = 0;
function posicionar() {
    cancelAnimationFrame(quadro);
    quadro = requestAnimationFrame(() => {
        const caixa = raiz.value;
        if (!caixa) {
            return;
        }
        if (window.innerWidth < 640 || !props.ancora?.isConnected) {
            estilo.value = { left: '8px', right: '8px', bottom: '8px', maxHeight: '80vh' }; // celular: folha embaixo
            return;
        }
        const ponto = props.ancora.getBoundingClientRect();
        const largura = Math.min(448, window.innerWidth - 16);
        const altura = caixa.offsetHeight;
        let topo = ponto.bottom + 10;
        if (topo + altura > window.innerHeight - 8) {
            topo = ponto.top - altura - 10;
        }
        topo = Math.max(8, Math.min(topo, window.innerHeight - altura - 8));
        estilo.value = {
            left: `${Math.min(Math.max(8, ponto.left + ponto.width / 2 - largura / 2), window.innerWidth - largura - 8)}px`,
            top: `${topo}px`,
            width: `${largura}px`,
            maxHeight: `${window.innerHeight - 16}px`,
        };
    });
}

function cliqueFora(evento) {
    const alvo = evento.target;
    // Janela por cima (Avisar no WhatsApp, painel do usuário) e formulário aberto: o popover fica.
    if (raiz.value?.contains(alvo) || props.ancora?.contains(alvo) || document.querySelector('[aria-modal="true"]') || raiz.value?.querySelector('form')) {
        return;
    }
    emit('fechar', false); // clicou em outro lugar: o foco é de quem foi clicado
}

let pararDeOuvir = () => {};
let observador = null;
onMounted(() => {
    carregar();
    posicionar();
    raiz.value?.focus({ preventScroll: true });
    observador = new ResizeObserver(posicionar);
    observador.observe(raiz.value);
    window.addEventListener('resize', posicionar);
    window.addEventListener('scroll', posicionar, { capture: true, passive: true }); // também a rolagem do bloco da linha do tempo
    document.addEventListener('pointerdown', cliqueFora);
    document.addEventListener('keydown', teclado);
    // Uma ação terminou (baixa, envio, forma de pagamento): a linha muda de etapa, então busca de novo e, se o foco
    // estava no que sumiu (o botão "Confirmar baixa"), traz o foco de volta para o popover.
    pararDeOuvir = router.on('success', (evento) => {
        if (evento.detail.page.component === 'Painel') {
            carregar().then(() => requestAnimationFrame(() => {
                if (raiz.value && !raiz.value.contains(document.activeElement) && !document.querySelector('[aria-modal="true"]')) {
                    raiz.value.focus({ preventScroll: true });
                }
            }));
        }
    });
});
onBeforeUnmount(() => {
    cancelAnimationFrame(quadro);
    observador?.disconnect();
    pararDeOuvir();
    window.removeEventListener('resize', posicionar);
    window.removeEventListener('scroll', posicionar, { capture: true });
    document.removeEventListener('pointerdown', cliqueFora);
    document.removeEventListener('keydown', teclado);
});
</script>

<template>
    <Teleport to="body">
        <!-- z-30: abaixo do painel do usuário (z-40) e de qualquer Modal (z-50); acima do menu fixo e do conteúdo. -->
        <div
            ref="raiz"
            role="dialog"
            :aria-labelledby="idTitulo"
            tabindex="-1"
            class="popover-do-ponto fixed z-30 flex flex-col overflow-y-auto rounded-(--raio-bloco) border border-linha bg-superficie shadow-lg focus:outline-none"
            :style="estilo"
            @click="depoisDoClique"
        >
            <div class="flex items-start justify-between gap-3 border-b border-linha px-4 py-2">
                <h2 :id="idTitulo" class="font-estreita text-sm text-apagado">{{ descricao }}</h2>
                <button type="button" class="botao-secundario -mr-2 flex-none px-2 py-1" aria-label="Fechar" @click="emit('fechar', true)">✕</button>
            </div>
            <p v-if="estado === 'carregando'" role="status" class="px-4 py-4 text-sm text-apagado">Abrindo a cobrança…</p>
            <div v-else-if="estado === 'falha'" role="alert" class="flex flex-wrap items-center gap-3 px-4 py-4 text-sm">
                <span>Não foi possível abrir esta cobrança.</span>
                <button type="button" class="botao-secundario" @click="carregar">Tentar de novo</button>
            </div>
            <template v-else>
                <ul>
                    <LinhaCobranca :cobranca="cobranca">
                        <AcoesDaCobranca :cobranca="cobranca" :chaves-pix="chavesPix" :formas-pagamento="formasPagamento" />
                        <button v-if="cobranca.situacao !== 'paga'" type="button" class="botao-secundario" aria-haspopup="dialog" @click="avisando = true">Avisar no WhatsApp</button>
                    </LinhaCobranca>
                </ul>
                <AvisarWhatsapp :aberto="avisando" :ids="[cobranca.id]" :modelos="modelos" :prestador="prestador" @fechar="avisando = false" />
            </template>
        </div>
    </Teleport>
</template>

<style>
@keyframes popover-surge {
    from { opacity: 0; transform: translateY(4px) scale(0.98); }
}
.popover-do-ponto {
    animation: popover-surge 140ms ease-out;
}
@media (prefers-reduced-motion: reduce) {
    .popover-do-ponto { animation: none; }
}
</style>
