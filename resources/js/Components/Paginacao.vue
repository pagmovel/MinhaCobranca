<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({ paginacao: { type: Object, required: true } });
</script>

<template>
    <nav v-if="paginacao.ultima > 1" aria-label="Paginação" class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm">
        <Link v-if="paginacao.anterior" :href="paginacao.anterior" class="botao-secundario">Página anterior</Link>
        <span v-else />
        <span class="font-estreita text-grafite/80">Página {{ paginacao.atual }} de {{ paginacao.ultima }} ({{ paginacao.total }} no total)</span>
        <Link v-if="paginacao.proxima" :href="paginacao.proxima" class="botao-secundario">Próxima página</Link>
        <span v-else />
    </nav>
</template>
