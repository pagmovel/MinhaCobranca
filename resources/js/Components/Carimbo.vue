<script setup>
import { computed } from 'vue';

const props = defineProps({ situacao: { type: String, required: true } });

const estilos = {
    a_vencer: ['A vencer', 'border-carbono text-carbono'],
    vence_hoje: ['Vencendo hoje', 'border-tinta-laranja text-tinta-laranja'],
    vencida: ['Vencido', 'border-tinta-vermelha text-tinta-vermelha'],
    paga: ['Pago', 'border-tinta-verde text-tinta-verde'],
    cancelada: ['Cancelado', 'border-grafite/50 text-grafite/60'],
    // Ainda não é cobrança: cinza e tracejado, para não se confundir com "A vencer".
    prevista: ['Prevista', 'border-dashed border-grafite/50 text-grafite/70'],
};

const atual = computed(() => estilos[props.situacao]);
</script>

<template>
    <span class="carimbo inline-block min-w-32 -rotate-6 rounded-sm border-2 px-2 py-0.5 text-center text-xs font-bold whitespace-nowrap uppercase" :class="atual[1]">
        {{ atual[0] }}
    </span>
</template>
