<script setup>
import { computed, ref } from 'vue';
import { camposFaltandoNoForm } from '../progressoHolofote.js';

const props = defineProps({
    pendencia: { type: Object, required: true }, // a pendência "dados_prestador" (tem campos_faltando) ou outra (tem seletor)
    form: { type: Object, default: null }, // só para dados_prestador — re-filtra contra o que já foi digitado
});

const pulado = ref(false);
const indice = ref(0); // posição em `campos` — um ref próprio, nunca mutar o array do computed diretamente

// Recalcula a cada mudança no form: o que a pessoa já digitou sai da lista sem precisar salvar.
const campos = computed(() => (props.form ? camposFaltandoNoForm(props.pendencia.campos_faltando ?? [], props.form) : []));
const atual = computed(() => campos.value[Math.min(indice.value, campos.value.length - 1)] ?? null);
const seletorAtual = computed(() => atual.value?.seletor ?? props.pendencia.seletor);
const textoAtual = computed(() => (atual.value
    ? `Passo ${indice.value + 1} de ${campos.value.length} — ${atual.value.rotulo}`
    : props.pendencia.rotulo));
const temHolofote = computed(() => !pulado.value && (props.form ? campos.value.length > 0 : true));

function proximoCampo() {
    indice.value = Math.min(indice.value + 1, campos.value.length - 1);
}
</script>

<template>
    <div v-if="temHolofote && seletorAtual" class="fixed inset-x-4 bottom-20 z-30 border border-carbono bg-white p-3 text-sm shadow-lg sm:max-w-xs" role="status">
        <p>{{ textoAtual }}</p>
        <div class="mt-2 flex gap-3">
            <button v-if="form && campos.length > 1" type="button" class="font-semibold underline" @click="proximoCampo">Próximo campo</button>
            <button type="button" class="text-grafite/70 underline" @click="pulado = true">Pular</button>
        </div>
    </div>
</template>
