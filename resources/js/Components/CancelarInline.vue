<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({ cobrancaId: { type: Number, required: true } });

const confirmando = ref(false);

const cancelar = () => router.post(`/cobrancas/${props.cobrancaId}/cancelar`, {}, {
    preserveScroll: true,
    onFinish: () => { confirmando.value = false; },
});
</script>

<template>
    <button v-if="!confirmando" type="button" class="botao-perigo" @click="confirmando = true">Cancelar cobrança</button>
    <span v-else class="inline-flex flex-wrap gap-2">
        <button type="button" class="botao-perigo-confirmar" @click="cancelar">Confirmar cancelamento</button>
        <button type="button" class="botao-secundario" @click="confirmando = false">Manter cobrança</button>
    </span>
</template>
