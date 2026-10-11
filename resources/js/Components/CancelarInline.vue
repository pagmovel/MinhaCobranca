<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

// naFaixa: o primeiro clique foi o item do menu; a faixa já abre pedindo a confirmação.
const props = defineProps({ cobrancaId: { type: Number, required: true }, naFaixa: { type: Boolean, default: false } });
const emit = defineEmits(['fechar']);

const confirmando = ref(props.naFaixa);

const fechar = () => { confirmando.value = false; emit('fechar'); };
const cancelar = () => router.post(`/cobrancas/${props.cobrancaId}/cancelar`, {}, { preserveScroll: true, onFinish: fechar });
</script>

<template>
    <button v-if="!confirmando" type="button" class="botao-perigo" @click="confirmando = true">Cancelar cobrança</button>
    <span v-else class="inline-flex flex-wrap gap-2" @keydown.esc="fechar">
        <button type="button" class="botao-perigo-confirmar" @click="cancelar">Confirmar cancelamento</button>
        <button type="button" class="botao-secundario" @click="fechar">Manter cobrança</button>
    </span>
</template>
