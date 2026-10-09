<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

defineProps({ titulo: { type: String, required: true } });

const flash = computed(() => usePage().props.flash);
</script>

<template>
    <main class="mx-auto flex min-h-screen w-full max-w-sm flex-col justify-center px-4 py-10">
        <p class="font-estreita text-sm text-grafite/70">Gerenciador de Cobranças</p>
        <h1 class="mt-1 mb-6 text-3xl font-semibold">{{ titulo }}</h1>
        <p v-if="flash.sucesso" role="status" class="mb-4 border-l-4 border-tinta-verde bg-white px-4 py-2 text-sm">
            {{ flash.sucesso }}
        </p>
        <p v-if="flash.erro" role="alert" class="mb-4 border-l-4 border-tinta-vermelha bg-white px-4 py-2 text-sm">
            {{ flash.erro }}
        </p>
        <slot />
    </main>
</template>
