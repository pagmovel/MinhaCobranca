<script setup>
import { computed } from 'vue';
import { corLegivelSobre } from '../contraste.js';

/**
 * Mini-esquema de um tema (item de `temas`): a casca (menu lateral ou superior), a composição do Painel
 * (`empilhado`, `regua` ou `mosaico`, com a linha do tempo quando o tema a tem) e as cores da `amostra`
 * (fundo, texto, destaque, linha e menu). Só desenho: o texto do tema fica no cartão que o usa.
 * shortcut: os blocos são brancos; um tema escuro precisa de uma sexta cor na `amostra` (a superfície).
 */
const props = defineProps({
    tema: { type: Object, required: true },
});

const SUPERFICIE = '#FFFFFF';
const [fundo, texto, destaque, linha, menu] = [0, 1, 2, 3, 4].map((indice) => computed(() => props.tema.amostra[indice]));
const traco = computed(() => corLegivelSobre(menu.value, SUPERFICIE, texto.value)); // itens do menu
const lateral = computed(() => props.tema.casca === 'lateral');
const painel = computed(() => props.tema.painel);
// Cantos: o Talão é de quinas retas, a Régua tem cantos pequenos e o Mosaico (Almanaque), bem arredondados.
const raio = computed(() => ({ empilhado: 0.6, regua: 2, mosaico: 4 })[painel.value] ?? 1);
const x0 = computed(() => (lateral.value ? 30 : 8));
const y0 = computed(() => (lateral.value ? 8 : 22));
const largura = computed(() => 112 - x0.value);
const posicoesDoEixo = [0.08, 0.26, 0.4, 0.62, 0.84];
</script>

<template>
    <svg
        viewBox="0 0 120 80"
        class="block h-auto w-full"
        role="img"
        :aria-label="`Miniatura do tema ${tema.nome}: menu ${lateral ? 'lateral' : 'no topo'}`"
    >
        <rect width="120" height="80" :fill="fundo" />

        <template v-if="lateral">
            <rect width="24" height="80" :fill="menu" />
            <path d="M24 0V80" :stroke="linha" stroke-width="1" />
            <circle cx="8" cy="10" r="3" :fill="destaque" />
            <rect x="4" y="22" width="16" height="5" :rx="raio" :fill="destaque" />
            <rect v-for="indice in 3" :key="indice" x="5" :y="22 + indice * 9 + 1" width="14" height="3" rx="1.5" :fill="traco" opacity="0.5" />
            <circle cx="8" cy="70" r="3.5" :fill="destaque" />
            <rect x="14" y="69" width="6" height="2" rx="1" :fill="traco" opacity="0.5" />
        </template>
        <template v-else>
            <rect width="120" height="14" :fill="menu" />
            <path d="M0 14H120" :stroke="linha" stroke-width="1" />
            <circle cx="8" cy="7" r="3" :fill="destaque" />
            <rect v-for="(deslocamento, indice) in [20, 38, 56, 74]" :key="deslocamento" :x="deslocamento" y="5.5" width="12" height="3" rx="1.5" :fill="traco" :opacity="indice === 0 ? 1 : 0.5" />
            <rect x="19" y="12" width="14" height="2" rx="1" :fill="destaque" />
            <circle cx="112" cy="7" r="3.5" :fill="destaque" />
        </template>

        <rect :x="x0" :y="y0" width="24" height="4" rx="1" :fill="texto" />

        <!-- Talão: as duas listas empilhadas. -->
        <template v-if="painel === 'empilhado'">
            <template v-for="(bloco, indice) in [{ y: 17, altura: 25 }, { y: 46, altura: 26 }]" :key="indice">
                <rect :x="x0" :y="bloco.y" :width="largura" :height="bloco.altura" :rx="raio" :fill="SUPERFICIE" :stroke="linha" />
                <template v-for="linhaDaLista in 3" :key="linhaDaLista">
                    <rect :x="x0 + 4" :y="bloco.y + linhaDaLista * 6 - 1" :width="largura * (linhaDaLista === 2 ? 0.34 : 0.44)" height="2" rx="1" :fill="linha" />
                    <rect :x="x0 + largura - 14" :y="bloco.y + linhaDaLista * 6 - 1.5" width="10" height="3" rx="1.5" :fill="linhaDaLista === 1 ? destaque : linha" />
                </template>
            </template>
        </template>

        <!-- Régua: o mês numa linha do tempo e uma lista embaixo. -->
        <template v-else-if="painel === 'regua'">
            <path :d="`M${x0} 38H${x0 + largura}`" :stroke="linha" stroke-width="1.5" />
            <circle v-for="(posicao, indice) in posicoesDoEixo" :key="posicao" :cx="x0 + largura * posicao" cy="38" r="2.4" :fill="indice % 2 ? texto : destaque" />
            <rect :x="x0" y="48" :width="largura" height="24" :rx="raio" :fill="SUPERFICIE" :stroke="linha" />
            <rect v-for="linhaDaLista in 3" :key="linhaDaLista" :x="x0 + 4" :y="48 + linhaDaLista * 6 - 1" :width="largura * (linhaDaLista === 2 ? 0.3 : 0.4)" height="2" rx="1" :fill="linha" />
        </template>

        <!-- Almanaque (mosaico): blocos arredondados e, quando o tema tem, a linha do tempo. -->
        <template v-else>
            <rect :x="x0" y="17" :width="largura * 0.56" height="22" :rx="raio" :fill="SUPERFICIE" :stroke="linha" />
            <rect :x="x0 + 4" y="22" width="14" height="5" rx="1" :fill="destaque" />
            <rect :x="x0 + 4" y="31" :width="largura * 0.3" height="2" rx="1" :fill="linha" />
            <rect :x="x0 + largura * 0.6" y="17" :width="largura * 0.4" height="22" :rx="raio" :fill="SUPERFICIE" :stroke="linha" />
            <rect :x="x0 + largura * 0.6 + 4" y="22" width="10" height="5" rx="1" :fill="texto" />
            <rect :x="x0" y="43" :width="largura" height="29" :rx="raio" :fill="SUPERFICIE" :stroke="linha" />
            <template v-if="tema.linha_do_tempo">
                <path :d="`M${x0 + 5} 58H${x0 + largura - 5}`" :stroke="linha" stroke-width="1.5" />
                <circle v-for="(posicao, indice) in posicoesDoEixo" :key="posicao" :cx="x0 + 5 + (largura - 10) * posicao" cy="58" r="2.4" :fill="indice % 2 ? texto : destaque" />
            </template>
            <template v-else>
                <rect v-for="linhaDaLista in 3" :key="linhaDaLista" :x="x0 + 5" :y="43 + linhaDaLista * 7" :width="largura * 0.4" height="2" rx="1" :fill="linha" />
            </template>
        </template>
    </svg>
</template>
