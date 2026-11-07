<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { formatarCentavos } from '../../itens.js';

/**
 * Valor em reais que, ao mudar, conta até o número novo e pisca (`.destacar`), para o olho achar o que mudou. Com
 * `prefers-reduced-motion` troca na hora e sem piscar. Na primeira exibição não pisca. Com `inteiro` o valor é uma
 * contagem (sem "R$" nem vírgula) e `sufixo` vai depois dele (por exemplo "%").
 */
const props = defineProps({
    centavos: { type: Number, required: true },
    duracao: { type: Number, default: 900 },
    inteiro: { type: Boolean, default: false },
    sufixo: { type: String, default: '' },
});

const exibido = ref(props.centavos);
const mudou = ref(false);
let quadro = 0;

watch(() => props.centavos, (para) => {
    cancelAnimationFrame(quadro);
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        exibido.value = para;

        return;
    }
    mudou.value = true;
    const origem = exibido.value;
    const inicio = performance.now();
    const passo = (agora) => {
        const t = Math.min(1, (agora - inicio) / props.duracao);
        exibido.value = Math.round(origem + (para - origem) * (1 - (1 - t) ** 3));
        if (t < 1) {
            quadro = requestAnimationFrame(passo);
        }
    };
    quadro = requestAnimationFrame(passo);
});
onBeforeUnmount(() => cancelAnimationFrame(quadro));
</script>

<template>
    <!-- A chave refaz o elemento a cada valor novo, e é isso que reinicia o pisca. -->
    <span :key="centavos" class="rounded-sm px-0.5 tabular-nums" :class="{ destacar: mudou }">{{ inteiro ? `${exibido}${sufixo}` : `R$ ${formatarCentavos(exibido)}` }}</span>
</template>
