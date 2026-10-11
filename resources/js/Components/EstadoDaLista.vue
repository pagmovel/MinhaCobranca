<script setup>
/**
 * Estados de uma lista por ajax antes de haver linhas: esqueleto na primeira carga (pedido do usuário, "como no
 * YouTube"), filtro recusado, falha com "Tentar de novo" e vazio. No estado "lista", mostra o slot.
 */
defineProps({
    estado: { type: String, required: true },
    falha: { type: String, default: '' },
    carregando: { type: String, required: true },
    vazio: { type: String, required: true },
    erroFiltro: { type: String, default: 'Corrija os filtros acima para ver a lista.' },
    linhas: { type: Number, default: 6 },
});
defineEmits(['tentar']);
</script>

<template>
    <div v-if="estado === 'carregando'" class="border border-linha bg-white" aria-busy="true">
        <p class="flex items-center gap-2 px-4 py-3 text-sm text-grafite/75" role="status">
            {{ carregando }}
            <span class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-carbono/25 border-t-carbono" aria-hidden="true" />
        </p>
        <div v-for="linha in linhas" :key="linha" class="flex items-center gap-4 border-t border-linha px-4 py-4 motion-safe:animate-pulse" aria-hidden="true">
            <span class="h-3 w-1/4 rounded-sm bg-linha" />
            <span class="hidden h-3 w-40 rounded-sm bg-linha xl:block" />
            <span class="hidden h-3 w-20 rounded-sm bg-linha xl:block" />
            <span class="ml-auto h-3 w-20 rounded-sm bg-linha" />
            <span class="hidden h-8 w-28 rounded-sm bg-linha sm:block" />
        </div>
    </div>
    <p v-else-if="estado === 'erro-filtro'" class="border border-carbono bg-white px-4 py-3 text-sm">{{ erroFiltro }}</p>
    <div v-else-if="estado === 'falha'" class="flex flex-wrap items-center gap-3 border border-tinta-vermelha bg-white px-4 py-3 text-sm">
        <span>{{ falha }}</span>
        <button type="button" class="botao-secundario" @click="$emit('tentar')">Tentar de novo</button>
    </div>
    <p v-else-if="estado === 'vazio'" class="border border-carbono bg-white px-4 py-3 text-sm">{{ vazio }}</p>
    <slot v-else />
</template>
