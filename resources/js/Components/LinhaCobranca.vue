<script setup>
import Carimbo from './Carimbo.vue';

defineProps({ cobranca: { type: Object, required: true } });
</script>

<template>
    <li class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <p class="font-semibold">{{ cobranca.cliente }}</p>
            <p class="font-estreita text-sm text-grafite/80">
                <template v-if="cobranca.situacao === 'paga'">Pago em {{ cobranca.pago_em }}</template>
                <template v-else>Vence {{ cobranca.vencimento }}</template>
                <template v-if="cobranca.recibo"> · recibo {{ cobranca.recibo.numero }}</template>
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <span class="text-lg font-semibold tabular-nums">R$ {{ cobranca.valor }}</span>
            <Carimbo :situacao="cobranca.situacao" />
            <slot />
        </div>
    </li>
</template>
