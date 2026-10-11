<script setup>
import Carimbo from './Carimbo.vue';

defineProps({ cobranca: { type: Object, required: true } });
</script>

<template>
    <li class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
        <div class="min-w-0">
            <p class="font-semibold">{{ cobranca.cliente }}</p>
            <p v-if="cobranca.cliente_oficial" class="text-xs text-grafite/80">{{ cobranca.cliente_oficial }}</p>
            <p class="font-estreita text-sm text-grafite/80">
                <template v-if="cobranca.situacao === 'paga'">Pago em {{ cobranca.pago_em }}</template>
                <template v-else>Vence {{ cobranca.vencimento }}</template>
                <template v-if="cobranca.recibo"> · recibo {{ cobranca.recibo.numero }}</template>
            </p>
            <ul v-if="cobranca.historico?.length" class="mt-1 font-estreita text-xs text-grafite/70">
                <li v-for="(linha, indice) in cobranca.historico" :key="indice">{{ linha }}</li>
            </ul>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <span class="text-lg font-semibold tabular-nums">R$ {{ cobranca.valor }}</span>
            <Carimbo :situacao="cobranca.situacao" />
            <slot />
        </div>
    </li>
</template>
