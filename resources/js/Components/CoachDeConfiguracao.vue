<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    progresso: { type: Object, required: true },
});

const proxima = computed(() => props.progresso.pendencias.find((pendencia) => !pendencia.feito));
</script>

<template>
    <details
        v-if="!progresso.liberado"
        open
        class="relative mb-4 border border-linha bg-white p-4 shadow-lg sm:fixed sm:inset-x-auto sm:right-4 sm:top-auto sm:bottom-4 sm:z-40 sm:mb-0 sm:w-72"
    >
        <summary class="cursor-pointer text-sm font-semibold">Primeiros passos</summary>
        <ul class="mt-2 flex flex-col gap-1 text-sm">
            <li v-for="pendencia in progresso.pendencias" :key="pendencia.item">
                <Link
                    v-if="!pendencia.feito"
                    :href="pendencia.link"
                    :class="pendencia === proxima ? 'font-semibold text-carbono' : 'text-grafite/70'"
                >
                    → {{ pendencia.rotulo }}
                </Link>
                <span v-else class="text-tinta-verde">✓ {{ pendencia.rotulo }}</span>
            </li>
        </ul>
    </details>
</template>
