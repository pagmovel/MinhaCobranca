<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    cobrancaId: { type: Number, required: true },
    pdfUrl: { type: String, required: true },
    nomeArquivo: { type: String, required: true },
    rotulo: { type: String, default: 'Enviar' },
});

const preparando = ref(false);
const erro = ref('');

function baixar(arquivo) {
    const url = URL.createObjectURL(arquivo);
    Object.assign(document.createElement('a'), { href: url, download: arquivo.name }).click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

async function enviar() {
    preparando.value = true;
    erro.value = '';

    try {
        const resposta = await fetch(props.pdfUrl, { credentials: 'same-origin' });
        if (!resposta.ok || !(resposta.headers.get('content-type') ?? '').includes('pdf')) {
            throw new Error('PDF indisponível');
        }
        const arquivo = new File([await resposta.blob()], props.nomeArquivo, { type: 'application/pdf' });

        // Folha de compartilhamento nativa (celular, HTTPS); fora disso, download.
        if (navigator.canShare?.({ files: [arquivo] })) {
            try {
                await navigator.share({ files: [arquivo], title: props.nomeArquivo });
            } catch (falha) {
                if (falha.name === 'AbortError') {
                    return; // fechou a folha sem enviar: não marca como enviada
                }
                if (falha.name !== 'NotAllowedError') {
                    throw falha;
                }
                baixar(arquivo); // o navegador exigiu um toque novo depois do download do PDF
            }
        } else {
            baixar(arquivo);
        }

        router.post(`/cobrancas/${props.cobrancaId}/enviada`, {}, { preserveScroll: true });
    } catch {
        erro.value = 'Não foi possível preparar o PDF. Tente de novo.';
    } finally {
        preparando.value = false;
    }
}
</script>

<template>
    <span class="inline-flex flex-col">
        <button type="button" class="botao" :disabled="preparando" @click="enviar">
            {{ preparando ? 'Preparando PDF…' : rotulo }}
        </button>
        <span v-if="erro" role="alert" class="mt-1 text-xs text-tinta-vermelha">{{ erro }}</span>
    </span>
</template>
