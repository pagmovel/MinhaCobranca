<script setup>
import { computed } from 'vue';

const props = defineProps({ situacao: { type: String, required: true } });

const estilos = {
    a_vencer: ['A vencer', 'border-carbono text-carbono'],
    vencida: ['Vencido', 'border-tinta-vermelha text-tinta-vermelha'],
    paga: ['Pago', 'border-tinta-verde text-tinta-verde'],
    cancelada: ['Cancelado', 'border-grafite/50 text-grafite/60'],
};

const atual = computed(() => estilos[props.situacao]);
</script>

<template>
    <span class="carimbo inline-block -rotate-6 rounded-sm border-2 px-2 py-0.5 text-xs font-bold uppercase" :class="atual[1]">
        {{ atual[0] }}
    </span>
</template>
