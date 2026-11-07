<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { explicacaoDoBloqueio } from '../menuPrincipal.js';
import { quantosCabem } from '../menuResponsivo.js';
import { iniciaisDoNome } from '../iniciais.js';
import LogoRecibo from './LogoRecibo.vue';

/**
 * Casca superior (Régua): logo e nome à esquerda, itens no meio, usuário à direita. O que não cabe na largura vai
 * para o "Mais ▾" (nunca fica escondido); abaixo de ~860 px o menu desce para uma segunda linha. A medida usa os
 * próprios itens: os que não cabem ficam `invisible absolute` (fora do fluxo e fora do foco, mas ainda medíveis).
 */
const props = defineProps({
    itens: { type: Array, required: true },
    usuario: { type: Object, required: true },
    painelAberto: { type: Boolean, default: false },
    pulsando: { type: Boolean, default: false }, // chama a atenção para o botão do nome (popup da novidade)
});
const emit = defineEmits(['abrir-painel']);

const page = usePage();
const idLista = `${useId()}-mais`;
const navegacao = ref(null);
const listaDaBarra = ref(null);
const blocoMais = ref(null);
const botaoMais = ref(null);
const listaMais = ref(null);

const FOLGA_ENTRE_ITENS = 2; // o `gap-0.5` entre os itens
const FOLGA_DO_PONTO = 14; // ponto de aviso do "Mais" (8 px + espaço): reservado quando ainda não está na tela

const cabem = ref(props.itens.length);
const maisAberto = ref(false);
const sobra = computed(() => props.itens.slice(cabem.value));
const avisosDoMais = computed(() => sobra.value.filter((item) => item.contador).map((item) => `${item.rotulo}: ${item.contador}`));

const ativo = (href) => page.url.startsWith(href);

// Tailwind v4: `max-[861px]:` é `width < 861px` e `min-[861px]:` é `width >= 861px` (juntos cobrem todas as larguras).
function medir() {
    if (!navegacao.value || !listaDaBarra.value || !blocoMais.value) {
        return;
    }
    const larguras = [...listaDaBarra.value.children].map((li) => li.offsetWidth + FOLGA_ENTRE_ITENS);
    const estilo = getComputedStyle(navegacao.value);
    const disponivel = navegacao.value.clientWidth - parseFloat(estilo.paddingLeft) - parseFloat(estilo.paddingRight);
    // shortcut: se o ponto aparecer ou sumir depois desta medida, a reserva fica 14 px torta até a próxima mudança de largura.
    const larguraDoMais = blocoMais.value.offsetWidth + (avisosDoMais.value.length ? 0 : FOLGA_DO_PONTO);
    cabem.value = quantosCabem(larguras, disponivel, larguraDoMais);
}

function fechar(devolverFoco = false) {
    if (!maisAberto.value) {
        return;
    }
    maisAberto.value = false;
    if (devolverFoco) {
        botaoMais.value?.focus();
    }
}

async function alternar() {
    maisAberto.value = !maisAberto.value;
    if (maisAberto.value) {
        await nextTick();
        listaMais.value?.querySelector('a')?.focus();
    }
}

const aoClicarFora = (evento) => {
    if (!blocoMais.value?.contains(evento.target)) {
        fechar();
    }
};
const aoTeclar = (evento) => {
    if (evento.key === 'Escape') {
        evento.stopPropagation();
        fechar(true);
    }
};
// Tab que sai do "Mais" (último link, Shift+Tab no botão) também fecha.
const aoPerderOFoco = (evento) => {
    if (!blocoMais.value?.contains(evento.relatedTarget)) {
        fechar();
    }
};

watch(maisAberto, (aberto) => {
    const acao = aberto ? 'addEventListener' : 'removeEventListener';
    document[acao]('click', aoClicarFora);
    document[acao]('keydown', aoTeclar);
});
watch(() => sobra.value.length, (quantos) => {
    if (quantos === 0) {
        maisAberto.value = false;
    }
});
// Mudou o que se mede: item ativo (negrito), 🔒 de item liberado, contador.
watch([() => props.itens, () => page.url], () => nextTick(medir), { deep: true });

let observador = null;
onMounted(() => {
    medir();
    observador = new ResizeObserver(medir);
    observador.observe(navegacao.value);
    document.fonts?.ready.then(medir);
});
onBeforeUnmount(() => {
    observador?.disconnect();
    document.removeEventListener('click', aoClicarFora);
    document.removeEventListener('keydown', aoTeclar);
});
</script>

<template>
    <header
        class="relative grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 border-b border-menu-linha bg-menu-fundo px-(--margem) text-menu-texto [--margem:clamp(1rem,2.4vw,2rem)] min-[861px]:grid-cols-[auto_minmax(0,1fr)_auto] min-[861px]:gap-x-7"
    >
        <Link
            href="/painel"
            class="flex items-center gap-2.5 py-3 font-titulo text-lg leading-[1.05] font-extrabold tracking-tight text-grafite focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-carbono"
            aria-label="Gerenciador de Cobranças, ir para o Painel"
        >
            <LogoRecibo />
            <span>Gerenciador de Cobranças</span>
        </Link>

        <nav
            ref="navegacao"
            aria-label="Principal"
            class="flex min-w-0 items-center max-[861px]:col-span-full max-[861px]:row-start-2 max-[861px]:-mx-(--margem) max-[861px]:border-t max-[861px]:border-menu-linha max-[861px]:px-(--margem)"
        >
            <ul ref="listaDaBarra" class="flex gap-0.5">
                <li v-for="(item, indice) in itens" :key="item.href" :class="indice >= cabem ? 'invisible absolute' : ''">
                    <span
                        v-if="item.bloqueado"
                        class="flex min-h-14 items-center gap-2 px-3 text-sm font-semibold whitespace-nowrap text-menu-texto/50"
                        :title="explicacaoDoBloqueio(item.href)"
                    >
                        🔒 {{ item.rotulo }}
                    </span>
                    <Link
                        v-else
                        :href="item.href"
                        class="relative flex min-h-14 items-center gap-2 px-3 text-sm font-semibold whitespace-nowrap text-menu-texto transition-colors hover:bg-menu-ativo hover:text-menu-texto-ativo focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-carbono"
                        :class="ativo(item.href) ? 'font-bold text-menu-texto-ativo after:absolute after:inset-x-3 after:bottom-0 after:h-[3px] after:rounded-t-[3px] after:bg-carbono' : ''"
                        :aria-current="ativo(item.href) ? 'page' : undefined"
                    >
                        {{ item.rotulo }}
                        <span
                            v-if="item.contador"
                            class="rounded-full bg-tinta-vermelha px-2 text-xs font-semibold text-white"
                            :aria-label="`${item.contador} cadastros aguardando liberação`"
                        >{{ item.contador }}</span>
                    </Link>
                </li>
            </ul>

            <div ref="blocoMais" :class="sobra.length ? 'relative flex-none' : 'invisible absolute'" @focusout="aoPerderOFoco">
                <button
                    ref="botaoMais"
                    type="button"
                    class="flex min-h-14 items-center gap-1.5 px-3 text-sm font-semibold text-menu-texto transition-colors hover:bg-menu-ativo hover:text-menu-texto-ativo aria-expanded:bg-menu-ativo aria-expanded:text-menu-texto-ativo focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-carbono"
                    :aria-expanded="maisAberto"
                    :aria-controls="idLista"
                    :title="avisosDoMais.length ? avisosDoMais.join(', ') : undefined"
                    @click="alternar"
                >
                    Mais
                    <span v-if="avisosDoMais.length" class="size-2 rounded-full bg-ouro" aria-hidden="true" />
                    <span v-if="avisosDoMais.length" class="sr-only">({{ avisosDoMais.join(', ') }})</span>
                    <svg
                        aria-hidden="true"
                        width="12"
                        height="12"
                        viewBox="0 0 12 12"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="transition-transform"
                        :class="maisAberto ? 'rotate-180' : ''"
                    >
                        <path d="M2.5 4.5L6 8l3.5-3.5" />
                    </svg>
                </button>
                <Transition name="surgir">
                    <ul
                        v-show="maisAberto"
                        :id="idLista"
                        ref="listaMais"
                        class="absolute top-[calc(100%-4px)] right-0 z-40 flex min-w-[14.5rem] flex-col rounded-(--raio-bloco) border border-menu-linha bg-superficie p-1.5 shadow-lg"
                    >
                        <li v-for="item in sobra" :key="item.href">
                            <span
                                v-if="item.bloqueado"
                                class="flex min-h-11 items-center justify-between gap-3 px-3 text-sm whitespace-nowrap text-menu-texto/50"
                                :title="explicacaoDoBloqueio(item.href)"
                            >
                                🔒 {{ item.rotulo }}
                            </span>
                            <Link
                                v-else
                                :href="item.href"
                                class="flex min-h-11 items-center justify-between gap-3 rounded-md px-3 text-sm whitespace-nowrap hover:bg-menu-ativo focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-carbono"
                                :class="ativo(item.href) ? 'bg-menu-ativo font-bold text-menu-texto-ativo' : ''"
                                :aria-current="ativo(item.href) ? 'page' : undefined"
                                @click="fechar()"
                            >
                                {{ item.rotulo }}
                                <span
                                    v-if="item.contador"
                                    class="rounded-full bg-tinta-vermelha px-2 text-xs font-semibold text-white"
                                    :aria-label="`${item.contador} cadastros aguardando liberação`"
                                >{{ item.contador }}</span>
                            </Link>
                        </li>
                    </ul>
                </Transition>
            </div>
        </nav>

        <button
            type="button"
            class="flex min-h-11 items-center gap-2 rounded-(--raio-controle) px-2 text-sm font-semibold hover:bg-menu-ativo focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-carbono"
            :class="pulsando ? 'pulsar-botao' : ''"
            :aria-label="usuario.name"
            data-botao-do-usuario
            aria-haspopup="dialog"
            :aria-expanded="painelAberto"
            @click="emit('abrir-painel', $event)"
        >
            <span class="grid size-8 flex-none place-items-center rounded-full bg-carbono text-xs font-bold text-sobre-acao" aria-hidden="true">
                {{ iniciaisDoNome(usuario.name) }}
            </span>
            <span class="max-w-48 truncate max-[480px]:hidden" aria-hidden="true">{{ usuario.name }}</span>
        </button>
    </header>
</template>
