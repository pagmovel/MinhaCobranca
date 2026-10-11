<script setup>
import { ref } from 'vue';

/** Menu "⋯" com as ações secundárias de uma linha. Fecha ao sair dele (clique fora ou Tab) e no Esc. */
defineProps({ rotulo: { type: String, default: 'Mais ações' } });

const aberto = ref(false);
const raiz = ref(null);
const botao = ref(null);

const fechar = () => { aberto.value = false; };
const saiu = (evento) => {
    if (!raiz.value?.contains(evento.relatedTarget)) {
        fechar();
    }
};
const esc = () => {
    fechar();
    botao.value?.focus();
};
</script>

<template>
    <div ref="raiz" class="relative" @focusout="saiu" @keydown.esc="esc">
        <button
            ref="botao"
            type="button"
            class="botao-secundario px-3"
            :aria-expanded="aberto"
            :aria-label="rotulo"
            :title="rotulo"
            @click="aberto = !aberto"
        >
            ⋯
        </button>
        <Transition name="surgir">
            <div v-if="aberto" class="absolute right-0 z-10 mt-1 flex w-56 flex-col border border-linha bg-white py-1 shadow-sm">
                <slot :fechar="fechar" />
            </div>
        </Transition>
    </div>
</template>
