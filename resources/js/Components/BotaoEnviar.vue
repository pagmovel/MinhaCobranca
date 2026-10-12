<script setup>
import { ref } from 'vue';
import { enviarRecibo } from '../enviarRecibo.js';

const props = defineProps({
    cobrancaId: { type: Number, required: true },
    pdfUrl: { type: String, required: true },
    nomeArquivo: { type: String, required: true },
    rotulo: { type: String, default: 'Enviar' },
    classeBotao: { type: String, default: 'botao' },
});

const preparando = ref(false);
const erro = ref('');

async function enviar() {
    preparando.value = true;
    erro.value = '';

    try {
        await enviarRecibo({ cobrancaId: props.cobrancaId, pdfUrl: props.pdfUrl, nomeArquivo: props.nomeArquivo });
    } catch {
        erro.value = 'Não foi possível preparar o PDF. Tente de novo.';
    } finally {
        preparando.value = false;
    }
}
</script>

<template>
    <span class="inline-flex flex-col">
        <button type="button" :class="classeBotao" :disabled="preparando" @click="enviar">
            {{ preparando ? 'Preparando PDF…' : rotulo }}
        </button>
        <span v-if="erro" role="alert" class="mt-1 text-xs text-tinta-vermelha">{{ erro }}</span>
    </span>
</template>
