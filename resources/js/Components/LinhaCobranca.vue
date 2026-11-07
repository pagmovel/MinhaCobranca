<script setup>
import { inject } from 'vue';
import Carimbo from './Carimbo.vue';

defineProps({ cobranca: { type: Object, required: true } });

// Só a composição Régua provê isto (`PainelRegua`): passar o mouse ou o foco aqui acende o ponto da linha do tempo, e
// o ponto aceso acende esta linha. Fora dela é `null` e a linha não muda.
const destaque = inject('destaqueDaCobranca', null);
</script>

<template>
    <li
        class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between"
        :class="{ 'linha-acesa': destaque && destaque.id.value === cobranca.id }"
        @mouseenter="destaque?.ligar(cobranca.id)"
        @mouseleave="destaque?.desligar(cobranca.id)"
        @focusin="destaque?.ligar(cobranca.id)"
        @focusout="!$event.currentTarget.contains($event.relatedTarget) && destaque?.desligar(cobranca.id)"
    >
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
            <div>
                <span class="text-lg font-semibold tabular-nums">R$ {{ cobranca.valor }}</span>
                <p v-if="cobranca.pago_diferente" class="font-estreita text-xs whitespace-nowrap text-grafite/80 tabular-nums">
                    pago R$ {{ cobranca.valor_pago }}
                </p>
                <p v-if="cobranca.linha_do_dia" class="font-estreita text-xs whitespace-nowrap text-grafite/80 tabular-nums">
                    {{ cobranca.linha_do_dia }}
                </p>
            </div>
            <Carimbo :situacao="cobranca.situacao" />
            <slot />
        </div>
    </li>
</template>
