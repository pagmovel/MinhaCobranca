<script setup>
import { Link } from '@inertiajs/vue3';
import CamadaApp from '../Layouts/CamadaApp.vue';

defineProps({
    mes: { type: String, required: true },
    perfilCompleto: { type: Boolean, required: true },
});
</script>

<template>
    <CamadaApp>
        <h1 class="text-2xl font-semibold">{{ mes }}</h1>
        <p v-if="!perfilCompleto" class="mt-4 border-l-4 border-carbono bg-white px-4 py-3 text-sm">
            Complete seu perfil para emitir recibos.
            <Link href="/perfil" class="font-semibold text-carbono underline">Completar perfil</Link>
        </p>
    </CamadaApp>
</template>
